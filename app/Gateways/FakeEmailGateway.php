<?php

namespace App\Gateways;

class FakeEmailGateway extends FakeGateway
{
    protected function gatewayName(): string
    {
        return 'fake-email-gateway';
    }
}
