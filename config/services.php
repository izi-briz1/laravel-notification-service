<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'gateways' => [
        // Лимиты исходящей отправки (сообщений в секунду), общие на все
        // воркеры — координируются через Redis::throttle
        'rate_limits' => [
            'sms' => (int) env('SMS_RATE_LIMIT_PER_SECOND', 10),
            'email' => (int) env('EMAIL_RATE_LIMIT_PER_SECOND', 30),
        ],

        'fake' => [
            // Вероятности ошибок заглушек провайдеров, проценты 0..100
            'transient_failure_percent' => (int) env('FAKE_PROVIDER_TRANSIENT_FAILURE_PERCENT', 0),
            'permanent_failure_percent' => (int) env('FAKE_PROVIDER_PERMANENT_FAILURE_PERCENT', 0),
            // Имитация DLR-колбэка: отложенное автоподтверждение доставки
            'auto_confirm' => (bool) env('FAKE_PROVIDER_AUTO_CONFIRM', true),
            'auto_confirm_delay_seconds' => (int) env('FAKE_PROVIDER_AUTO_CONFIRM_DELAY', 3),
        ],
    ],

];
