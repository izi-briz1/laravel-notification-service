<?php

namespace App\Gateways;

use App\Gateways\Exceptions\PermanentGatewayException;
use App\Gateways\Exceptions\TransientGatewayException;
use App\Jobs\SimulateDeliveryConfirmationJob;
use App\Models\Notification;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Заглушка внешнего шлюза. Имитирует реального провайдера:
 *  - генерирует provider_message_id;
 *  - по настройкам случайно «падает» transient/permanent-ошибками;
 *  - при auto_confirm планирует отложенный DLR-колбэк (подтверждение доставки),
 *    как это делает реальный провайдер через webhook.
 */
abstract class FakeGateway implements NotificationGatewayInterface
{
    abstract protected function gatewayName(): string;

    public function send(Notification $notification): GatewayResponse
    {
        $config = config('services.gateways.fake');

        $roll = random_int(1, 100);
        if ($roll <= $config['permanent_failure_percent']) {
            throw new PermanentGatewayException(
                "{$this->gatewayName()}: recipient '{$notification->recipient_id}' rejected (simulated permanent failure)"
            );
        }
        if ($roll <= $config['permanent_failure_percent'] + $config['transient_failure_percent']) {
            throw new TransientGatewayException(
                "{$this->gatewayName()}: gateway temporarily unavailable (simulated transient failure)"
            );
        }

        $providerMessageId = (string) Str::uuid();

        Log::info("{$this->gatewayName()}: message accepted by provider", [
            'notification_id' => $notification->id,
            'recipient_id' => $notification->recipient_id,
            'provider_message_id' => $providerMessageId,
        ]);

        if ($config['auto_confirm']) {
            SimulateDeliveryConfirmationJob::dispatch($providerMessageId)
                ->onQueue($notification->priority->queue())
                ->delay($config['auto_confirm_delay_seconds']);
        }

        return new GatewayResponse($providerMessageId);
    }
}
