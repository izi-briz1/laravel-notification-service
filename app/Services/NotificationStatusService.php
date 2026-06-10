<?php

namespace App\Services;

use App\Enums\NotificationStatus;
use App\Models\Notification;
use App\Models\NotificationStatusHistory;
use Illuminate\Support\Facades\DB;

/**
 * Единственная точка смены статуса уведомления.
 *
 * Переход выполняется условным UPDATE (... WHERE status = :from), поэтому при
 * конкурентной обработке одного сообщения (redelivery от брокера, два воркера)
 * выигрывает ровно один процесс — это основа exactly-once на бизнес-логике.
 * Запись в notification_status_history делается в той же транзакции.
 */
class NotificationStatusService
{
    /**
     * Захватить сообщение для отправки (queued -> sending).
     *
     * @return bool false — сообщение уже захвачено/обработано другим процессом
     */
    public function claimForSending(Notification $notification): bool
    {
        return $this->transition(
            $notification,
            from: NotificationStatus::Queued,
            to: NotificationStatus::Sending,
            extra: ['attempts' => DB::raw('attempts + 1')],
        );
    }

    public function markSent(Notification $notification, string $providerMessageId): bool
    {
        return $this->transition(
            $notification,
            from: NotificationStatus::Sending,
            to: NotificationStatus::Sent,
            info: "provider_message_id={$providerMessageId}",
            extra: ['provider_message_id' => $providerMessageId, 'sent_at' => now()],
        );
    }

    /** Вернуть в очередь для ретрая после transient-ошибки */
    public function releaseForRetry(Notification $notification, string $reason): bool
    {
        return $this->transition(
            $notification,
            from: NotificationStatus::Sending,
            to: NotificationStatus::Queued,
            info: $reason,
            extra: ['error_message' => $reason],
        );
    }

    public function markFailed(Notification $notification, string $reason, NotificationStatus $from = NotificationStatus::Sending): bool
    {
        return $this->transition(
            $notification,
            from: $from,
            to: NotificationStatus::Failed,
            info: $reason,
            extra: ['error_message' => $reason, 'failed_at' => now()],
        );
    }

    public function markDelivered(Notification $notification, ?string $info = null): bool
    {
        return $this->transition(
            $notification,
            from: NotificationStatus::Sent,
            to: NotificationStatus::Delivered,
            info: $info,
            extra: ['delivered_at' => now()],
        );
    }

    public function transition(
        Notification $notification,
        NotificationStatus $from,
        NotificationStatus $to,
        ?string $info = null,
        array $extra = [],
    ): bool {
        if (! $from->canTransitionTo($to)) {
            return false;
        }

        return DB::transaction(function () use ($notification, $from, $to, $info, $extra) {
            $updated = Notification::query()
                ->whereKey($notification->getKey())
                ->where('status', $from)
                ->update(['status' => $to, 'updated_at' => now(), ...$extra]);

            if ($updated === 0) {
                return false;
            }

            NotificationStatusHistory::query()->create([
                'notification_id' => $notification->getKey(),
                'status' => $to,
                'info' => $info,
            ]);

            $notification->refresh();

            return true;
        });
    }
}
