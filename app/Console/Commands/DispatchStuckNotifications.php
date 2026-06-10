<?php

namespace App\Console\Commands;

use App\Enums\NotificationStatus;
use App\Jobs\SendNotificationJob;
use App\Models\Notification;
use Illuminate\Console\Command;

/**
 * Переотправляет уведомления, закоммиченные в БД, но не
 * опубликованные в брокер (упали между коммитом транзакции и dispatch'ем).
 * Возможные дубликаты публикации безопасны - exactly-once гард на consumer
 * отбрасывает повторную обработку.
 */
class DispatchStuckNotifications extends Command
{
    protected $signature = 'notifications:dispatch-stuck {--age=60 : Минимальный возраст записи в секундах}';

    protected $description = 'Republish committed but undispatched notifications to the broker (transactional outbox sweeper)';

    public function handle(): int
    {
        $dispatched = 0;

        Notification::query()
            ->where('status', NotificationStatus::Queued)
            ->whereNull('dispatched_at')
            ->where('created_at', '<', now()->subSeconds((int) $this->option('age')))
            ->select(['id', 'channel', 'priority'])
            ->chunkById(500, function ($notifications) use (&$dispatched) {
                foreach ($notifications as $notification) {
                    SendNotificationJob::dispatch($notification->id, $notification->channel)
                        ->onQueue($notification->priority->queue());
                }

                Notification::query()
                    ->whereIn('id', $notifications->pluck('id'))
                    ->update(['dispatched_at' => now()]);

                $dispatched += $notifications->count();
            });

        $this->info("Republished {$dispatched} stuck notification(s).");

        return self::SUCCESS;
    }
}
