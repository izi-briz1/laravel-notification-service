# Notification Service

Микросервис массовой рассылки SMS/Email-уведомлений с приоритизацией трафика, гарантиями доставки и детализацией статусов.

**Стек:** PHP 8.4 / Laravel 12, PostgreSQL 17, RabbitMQ 4, Redis 7, Docker Compose.

## Быстрый старт

Требуется только Docker (Compose v2+).

```bash
git clone <repo-url>
cd laravel-notification-service
docker-compose up -d --build
```

Одной командой поднимается весь стек; миграции применяются автоматически при старте.

| Сервис | Адрес |
|---|---|
| API | http://localhost:8080/api/v1 |
| Swagger UI | http://localhost:8080/api/documentation |
| RabbitMQ Management | http://localhost:15672 (guest/guest) |

Запуск тестов (интеграционные, против реальных PostgreSQL и Redis):

```bash
docker-compose run --rm -e RUN_MIGRATIONS=false app php artisan test
```

## Архитектура

```
Клиент ──POST /api/v1/notifications (Idempotency-Key)──▶ API (nginx + php-fpm)
   │            дедубликация: Redis SETNX + UNIQUE(idempotency_key) в PG
   │            batch + N notifications (status=queued) — одна транзакция PG
   │            после коммита: публикация jobs в RabbitMQ + пометка dispatched_at
   │            (scheduler раз в минуту добирает неопубликованные — outbox-свипер)
   │
   │   RabbitMQ: очередь `transactional`   очередь `marketing`
   │                  │                          │
   │     worker-transactional          worker-marketing
   │                  └──────────┬───────────────┘
   │                    SendNotificationJob:
   │                    1. атомарный захват queued→sending (exactly-once гард)
   │                    2. rate limit канала (Redis::throttle, общий на воркеры)
   │                    3. вызов шлюза (mock SMS/Email провайдера)
   │                    4. sent | ретрай с backoff | failed
   │
   │   Провайдер ──DLR──▶ POST /api/v1/webhooks/delivery ──▶ delivered | failed
   │
   └──GET /api/v1/subscribers/{id}/notifications──▶ история + статусы
```

### Сервисы docker-compose

| Сервис | Роль |
|---|---|
| `app` (php-fpm) + `nginx` | HTTP API |
| `worker-transactional` | Выделенный consumer очереди `transactional` |
| `worker-marketing` | Consumer очереди `marketing` |
| `scheduler` | Laravel Scheduler: outbox-свипер `notifications:dispatch-stuck`, dead-worker-свипер `notifications:requeue-stuck-sending` |
| `postgres`, `rabbitmq`, `redis` | Хранилище, брокер, кэш/лимиты |

### Статусы уведомления

`queued` (принято, ждёт отправки) → `sending` (внутренний: воркер вызывает провайдера) → `sent` (передано шлюзу) → `delivered` (подтверждено провайдером) / `failed` (отброшено: ошибка доставки, несуществующий адресат, исчерпаны ретраи).

Каждый переход фиксируется в `notification_status_history` (когда, куда, детали) — полная хронология доступна через API.

## Гарантии доставки

**At-least-once.** Durable-очереди RabbitMQ, persistent-сообщения, ack только после обработки. Падение воркера в любой точке приводит к redelivery, а не к потере.

**Transactional Outbox (dual-write между PG и RabbitMQ).** Batch и уведомления коммитятся одной транзакцией; публикация в брокер происходит после коммита с пометкой `dispatched_at`. Если процесс упал между коммитом и публикацией, scheduler раз в минуту переотправляет «зависшие» записи (`status=queued AND dispatched_at IS NULL`, частичный индекс). Дубликаты публикации безопасны (см. ниже).

**Exactly-once на уровне бизнес-логики.** Воркер захватывает сообщение атомарным условным переходом `UPDATE ... SET status='sending' WHERE id=? AND status='queued'`. При redelivery того же сообщения UPDATE вернёт 0 строк — провайдер повторно не вызывается. Вся смена статусов идёт через одну точку (`NotificationStatusService`) с контролем допустимых переходов.

**Восстановление после жёсткого падения воркера (dead-worker sweeper).** Если воркер умер некорректно (kill -9, OOM) после захвата `queued→sending`, redelivery от брокера не помогает — exactly-once гард отбрасывает повтор как дубль, и уведомление зависает в `sending`. Scheduler раз в минуту возвращает такие записи (`status=sending` дольше 5 минут) обратно в `queued` и переотправляет в брокер; исчерпавшие попытки переводятся в `failed`. Для этого аварийного пути семантика — at-least-once: если воркер умер между ответом шлюза и фиксацией `sent`, возможен дубль — для уведомлений это дешевле потери.

**Идемпотентность API.** Обязательный заголовок `Idempotency-Key`: быстрый путь — Redis `SET NX` (TTL 24 ч), источник истины — UNIQUE-констрейнт в PostgreSQL. Повторный запрос возвращает `200` с ранее созданной рассылкой вместо `201`, ничего не дублируя.

**Ретраи.** Временные ошибки шлюза (`TransientGatewayException`) — возврат в очередь с экспоненциальным backoff (5с → 10с → 20с → ...), максимум 5 бизнес-попыток (считаются в БД, колонка `attempts`). Неустранимые ошибки (`PermanentGatewayException`, несуществующий номер/email) — сразу `failed` без ретраев.

**Контроль лимитов (Redis).**
- *Исходящий*: общий на все воркеры лимит отправки в шлюз по каналу (`Redis::throttle`, `SMS_RATE_LIMIT_PER_SECOND` / `EMAIL_RATE_LIMIT_PER_SECOND`). При превышении job возвращается в очередь, не расходуя бизнес-попытки.
- *Входящий*: rate limiter API (`API_RATE_LIMIT_PER_MINUTE`, по умолчанию 120 req/min с IP).

**Приоритизация.** Очереди `transactional` и `marketing` разнесены, у транзакционной — выделенные воркеры: критичные сообщения (коды доступа, изменения маршрута) не ждут за маркетинговой рассылкой даже при бэклоге. Воркеры масштабируются независимо: `docker-compose up -d --scale worker-marketing=4`.

## API

Полное описание — в Swagger UI: http://localhost:8080/api/documentation (генерируется автоматически при старте).

### Запустить рассылку

```bash
curl -X POST http://localhost:8080/api/v1/notifications \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -H "Idempotency-Key: demo-key-1" \
  -d '{
    "channel": "sms",
    "priority": "transactional",
    "text": "Ваш код доступа: 1234",
    "recipient_ids": ["user-1", "user-2", "user-3"]
  }'
```

`201` — принято; повтор с тем же `Idempotency-Key` → `200` и та же рассылка. Идентификаторы получателей принимаются как есть (валидация формата — зона ответственности вызывающего сервиса).

### Статусы уведомлений подписчика

```bash
curl "http://localhost:8080/api/v1/subscribers/user-1/notifications?status=delivered&channel=sms&per_page=25" \
  -H "Accept: application/json"
```

Возвращает пагинированный список с текущим статусом и полной историей переходов (`status_history`).

### Сводка по рассылке

```bash
curl http://localhost:8080/api/v1/notifications/batches/{batch_id} -H "Accept: application/json"
# → {"data": {...}, "status_counts": {"delivered": 3}}
```

### DLR-webhook провайдера

```bash
curl -X POST http://localhost:8080/api/v1/webhooks/delivery \
  -H "Content-Type: application/json" \
  -d '{"provider_message_id": "<uuid из sent>", "status": "delivered"}'
```

`200` — статус обновлён, `404` — неизвестный id, `409` — уведомление уже в финальном статусе.

## Провайдеры-заглушки

Внешние шлюзы имитируются классами `FakeSmsGateway` / `FakeEmailGateway`:

- генерируют `provider_message_id`, пишут «отправку» в лог;
- по умолчанию через несколько секунд сами подтверждают доставку (имитация DLR-колбэка) — статусы доходят до `delivered` без ручных действий;
- управляются переменными окружения (см. `docker-compose.yml`):

| Переменная | Значение |
|---|---|
| `FAKE_PROVIDER_TRANSIENT_FAILURE_PERCENT` | % временных ошибок шлюза (проверка ретраев) |
| `FAKE_PROVIDER_PERMANENT_FAILURE_PERCENT` | % неустранимых ошибок (несуществующий адресат) |
| `FAKE_PROVIDER_AUTO_CONFIRM` | автоподтверждение доставки (`true` по умолчанию) |

## Тесты

`php artisan test` — 29 интеграционных и unit-тестов (131 assertion) против реальных PostgreSQL/Redis: создание рассылки и маршрутизация по приоритетам, идемпотентность (включая потерю ключа Redis), полная цепочка job → провайдер → статус в БД, exactly-once при redelivery, transient/permanent-ошибки и исчерпание попыток, rate limit канала, outbox-свипер, dead-worker-свипер для зависших `sending`, webhook, история статусов, фильтры и пагинация.

## Демонстрация приоритизации

```bash
# 1. Забить marketing-очередь
curl -X POST http://localhost:8080/api/v1/notifications \
  -H "Content-Type: application/json" -H "Idempotency-Key: demo-marketing" \
  -d "{\"channel\":\"sms\",\"priority\":\"marketing\",\"text\":\"Скидки!\",\"recipient_ids\":[$(seq -s, -f '\"u-%g\"' 1 500)]}"

# 2. Сразу следом — транзакционное сообщение
curl -X POST http://localhost:8080/api/v1/notifications \
  -H "Content-Type: application/json" -H "Idempotency-Key: demo-urgent" \
  -d '{"channel":"sms","priority":"transactional","text":"Код: 9999","recipient_ids":["vip-user"]}'

# 3. Транзакционное уйдёт немедленно, не дожидаясь маркетинговых:
curl "http://localhost:8080/api/v1/subscribers/vip-user/notifications" -H "Accept: application/json"
```
