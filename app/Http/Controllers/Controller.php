<?php

namespace App\Http\Controllers;

use OpenApi\Attributes as OA;

#[OA\Info(
    version: '1.0.0',
    title: 'Notification Service API',
    description: 'Микросервис массовой рассылки SMS/Email-уведомлений с приоритизацией трафика, гарантиями доставки и детализацией статусов.',
)]
abstract class Controller
{
    //
}
