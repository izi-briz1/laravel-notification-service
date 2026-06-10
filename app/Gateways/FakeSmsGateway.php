<?php

namespace App\Gateways;

class FakeSmsGateway extends FakeGateway
{
    protected function gatewayName(): string
    {
        return 'fake-sms-gateway';
    }
}
