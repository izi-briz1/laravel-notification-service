<?php

namespace App\Gateways;

use App\Gateways\Exceptions\PermanentGatewayException;
use App\Gateways\Exceptions\TransientGatewayException;
use App\Models\Notification;

interface NotificationGatewayInterface
{
    /**
     * Передать сообщение внешнему шлюзу.
     *
     * @throws TransientGatewayException временная недоступность шлюза — имеет смысл ретраить
     * @throws PermanentGatewayException неустранимая ошибка (несуществующий номер/email) — ретраи бесполезны
     */
    public function send(Notification $notification): GatewayResponse;
}
