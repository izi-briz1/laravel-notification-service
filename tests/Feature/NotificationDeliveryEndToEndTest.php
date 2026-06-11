<?php

namespace Tests\Feature;

use App\Enums\NotificationStatus;
use App\Gateways\FakeSmsGateway;
use App\Models\Notification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Redis;
use Tests\Support\SpyGateway;
use Tests\TestCase;

/**
 * Сквозной тест всей цепочки одним куском: HTTP-запрос -> постановка
 * в очередь -> получение сообщения из очереди (sync-драйвер исполняет
 * job сразу при dispatch, через реальный pipeline c middleware) ->
 * вызов провайдера -> статусы и история в БД -> ответ API подписчика.
 */
class NotificationDeliveryEndToEndTest extends TestCase
{
    use RefreshDatabase;

    private SpyGateway $gateway;

    protected function setUp(): void
    {
        parent::setUp();

        // Слоты Redis::throttle живут в default-соединении, ключи
        // идемпотентности — в cache-соединении; чистим оба
        Redis::connection()->client()->flushdb();
        Cache::flush();

        $this->gateway = new SpyGateway;
        $this->app->instance(FakeSmsGateway::class, $this->gateway);
    }

    public function test_full_chain_from_api_request_to_provider_call_and_db_status(): void
    {
        // Очередь намеренно НЕ фейкается
        $response = $this->postJson('/api/v1/notifications', [
            'channel' => 'sms',
            'priority' => 'transactional',
            'text' => 'Ваш код доступа: 1234',
            'recipient_ids' => ['user-1', 'user-2', 'user-3'],
        ], ['Idempotency-Key' => 'e2e-key-1']);

        $response->assertCreated();

        $this->assertSame(3, $this->gateway->calls, 'провайдер вызван ровно по одному разу на получателя');

        $this->assertSame(3, Notification::count());
        Notification::all()->each(function (Notification $notification) {
            $this->assertSame(NotificationStatus::Sent, $notification->status);
            $this->assertSame(1, $notification->attempts);
            $this->assertNotNull($notification->provider_message_id);
            $this->assertNotNull($notification->dispatched_at, 'outbox-маркер публикации проставлен');
            $this->assertNotNull($notification->sent_at);

            $this->assertSame(
                ['queued', 'sending', 'sent'],
                $notification->statusHistory->map(fn ($h) => $h->status->value)->all(),
            );
        });

        // Изменение статуса видно через API истории подписчика
        $history = $this->getJson('/api/v1/subscribers/user-1/notifications')->assertOk();
        $this->assertSame('sent', $history->json('data.0.status'));
    }
}
