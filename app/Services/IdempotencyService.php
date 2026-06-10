<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;

/**
 * Дедубликация входящих запросов по Idempotency-Key.
 *
 * Redis (Cache::add = SETNX) — быстрый путь; источником истины остаётся
 * unique-constraint на notification_batches.idempotency_key, который
 * закрывает гонки и потерю ключей в Redis.
 */
class IdempotencyService
{
    private const TTL_SECONDS = 86400;

    /** @return bool true — ключ захвачен этим запросом, false — уже занят */
    public function claim(string $key): bool
    {
        return Cache::add($this->cacheKey($key), 1, self::TTL_SECONDS);
    }

    /** Освободить ключ, если создание batch'а откатилось */
    public function release(string $key): void
    {
        Cache::forget($this->cacheKey($key));
    }

    private function cacheKey(string $key): string
    {
        return "idempotency:{$key}";
    }
}
