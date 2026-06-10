<?php

namespace App\Services;

use App\Enums\ConfirmationResult;
use App\Enums\NotificationStatus;
use App\Models\Notification;

/**
 * Обработка подтверждений доставки (DLR) от провайдера.
 * Используется и webhook-контроллером, и имитацией колбэка fake-провайдера.
 */
class DeliveryConfirmationService
{
    public function __construct(private NotificationStatusService $statuses) {}

    public function confirm(string $providerMessageId, bool $delivered, ?string $info = null): ConfirmationResult
    {
        $notification = Notification::query()
            ->where('provider_message_id', $providerMessageId)
            ->first();

        if ($notification === null) {
            return ConfirmationResult::NotFound;
        }

        $transitioned = $delivered
            ? $this->statuses->markDelivered($notification, $info)
            : $this->statuses->markFailed($notification, $info ?? 'rejected by provider', from: NotificationStatus::Sent);

        return $transitioned ? ConfirmationResult::Confirmed : ConfirmationResult::Conflict;
    }
}
