<?php

namespace App\Console\Commands;

use App\Enums\NotificationStatus;
use App\Jobs\SendNotificationJob;
use App\Models\Notification;
use App\Services\NotificationStatusService;
use Illuminate\Console\Command;

/**
 * Возвращает в очередь уведомления, зависшие в sending: воркер захватил
 * сообщение (queued -> sending) и умер жёстко (kill -9, OOM), не доведя его
 * до финального статуса. Redelivery от брокера такой случай не лечит -
 * exactly-once гард отбрасывает повторную доставку как дубль.
 *
 * Для аварийного пути семантика честно деградирует до at-least-once:
 * если воркер умер между ответом шлюза и markSent, получатель может
 * получить дубль - для уведомлений это дешевле потери.
 */
class RequeueStuckSendingNotifications extends Command
{
    protected $signature = 'notifications:requeue-stuck-sending {--age=300 : Минимальное время в статусе sending в секундах}';

    protected $description = 'Requeue notifications stuck in sending after a hard worker crash (dead-worker sweeper)';

    public function handle(NotificationStatusService $statuses): int
    {
        $age = (int) $this->option('age');
        $reason = "stuck in sending for over {$age}s, worker presumed dead";

        $requeued = 0;
        $failed = 0;

        Notification::query()
            ->where('status', NotificationStatus::Sending)
            // updated_at выставляется условным UPDATE при захвате, т.е. это
            // момент начала отправки; живой воркер укладывается в таймаут шлюза
            ->where('updated_at', '<', now()->subSeconds($age))
            ->chunkById(512, function ($notifications) use ($statuses, $reason, &$requeued, &$failed) {
                foreach ($notifications as $notification) {
                    if ($notification->attempts >= SendNotificationJob::MAX_SEND_ATTEMPTS) {
                        $failed += (int) $statuses->markFailed(
                            $notification,
                            "max send attempts exceeded ({$notification->attempts}): {$reason}",
                        );

                        continue;
                    }

                    // dispatched_at сбрасывается в той же транзакции: если упадём
                    // до dispatch'а ниже, запись доберёт outbox-свипер
                    $released = $statuses->transition(
                        $notification,
                        from: NotificationStatus::Sending,
                        to: NotificationStatus::Queued,
                        info: $reason,
                        extra: ['error_message' => $reason, 'dispatched_at' => null],
                    );

                    if (! $released) {
                        continue;
                    }

                    SendNotificationJob::dispatch($notification->id, $notification->channel)
                        ->onQueue($notification->priority->queue());

                    Notification::query()
                        ->whereKey($notification->getKey())
                        ->update(['dispatched_at' => now()]);

                    $requeued++;
                }
            });

        $this->info("Requeued {$requeued} stuck notification(s), failed {$failed} exhausted one(s).");

        return self::SUCCESS;
    }
}
