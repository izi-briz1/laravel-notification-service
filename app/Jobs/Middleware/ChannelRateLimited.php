<?php

namespace App\Jobs\Middleware;

use Closure;
use Illuminate\Support\Facades\Redis;

/**
 * Глобальный (на все воркеры) лимит исходящей отправки к шлюзу канала.
 * Координируется через Redis::throttle; при отсутствии слота job
 * возвращается в очередь — это НЕ расходует бизнес-попытки отправки
 * (attempts инкрементируется только при захвате статуса в БД).
 */
class ChannelRateLimited
{
    public function handle(object $job, Closure $next): void
    {
        $channel = $job->channel;
        $limit = config("services.gateways.rate_limits.{$channel->value}");

        Redis::throttle("gateway-rate-limit:{$channel->value}")
            ->allow($limit)
            ->every(1)
            ->block(0)
            ->then(
                fn () => $next($job),
                fn () => $job->release(1),
            );
    }
}
