<?php

namespace App\Services;

use App\Enums\NotificationStatus;
use App\Models\Notification;

/**
 * Обработка подтверждений доставки (DLR) от провайдера.
 * Используется и webhook-контроллером, и имитацией колбэка fake-провайдера.
 */
class DeliveryConfirmationService
{
    public function __construct(private NotificationStatusService $statuses) {}

    /**
     * @return bool false — уведомление не найдено или переход невозможен
     */
    public function confirm(string $providerMessageId, bool $delivered, ?string $info = null): bool
    {
        $notification = Notification::query()
            ->where('provider_message_id', $providerMessageId)
            ->first();

        if ($notification === null) {
            return false;
        }

        return $delivered
            ? $this->statuses->markDelivered($notification, $info)
            : $this->statuses->markFailed($notification, $info ?? 'rejected by provider', from: NotificationStatus::Sent);
    }
}
