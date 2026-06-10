<?php

namespace Tests\Support;

use App\Gateways\GatewayResponse;
use App\Gateways\NotificationGatewayInterface;
use App\Models\Notification;
use Illuminate\Support\Str;
use Throwable;

/**
 * Тестовый двойник шлюза: считает вызовы и бросает заранее
 * подготовленные исключения (по одному на вызов).
 */
class SpyGateway implements NotificationGatewayInterface
{
    public int $calls = 0;

    /** @var list<Throwable> */
    public array $throwQueue = [];

    public ?string $lastProviderMessageId = null;

    public function send(Notification $notification): GatewayResponse
    {
        $this->calls++;

        $exception = array_shift($this->throwQueue);
        if ($exception !== null) {
            throw $exception;
        }

        $this->lastProviderMessageId = (string) Str::uuid();

        return new GatewayResponse($this->lastProviderMessageId);
    }
}
