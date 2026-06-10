<?php

namespace App\Gateways;

use App\Enums\Channel;
use Illuminate\Contracts\Container\Container;

class GatewayFactory
{
    public function __construct(private Container $container) {}

    public function for(Channel $channel): NotificationGatewayInterface
    {
        return match ($channel) {
            Channel::Sms => $this->container->make(FakeSmsGateway::class),
            Channel::Email => $this->container->make(FakeEmailGateway::class),
        };
    }
}
