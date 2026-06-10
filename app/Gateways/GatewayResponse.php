<?php

namespace App\Gateways;

final readonly class GatewayResponse
{
    public function __construct(
        public string $providerMessageId,
    ) {}
}
