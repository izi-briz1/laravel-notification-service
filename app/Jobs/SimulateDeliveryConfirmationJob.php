<?php

namespace App\Jobs;

use App\Services\DeliveryConfirmationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Имитация DLR-колбэка реального провайдера: с задержкой подтверждает
 * доставку (sent -> delivered). В реальной системе это делал бы сам
 * провайдер через POST /api/v1/webhooks/delivery.
 */
class SimulateDeliveryConfirmationJob implements ShouldQueue
{
    use Queueable;

    public function __construct(public string $providerMessageId) {}

    public function handle(DeliveryConfirmationService $confirmations): void
    {
        $confirmations->confirm($this->providerMessageId, delivered: true, info: 'auto-confirmed by fake provider');
    }
}
