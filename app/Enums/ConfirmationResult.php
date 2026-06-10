<?php

namespace App\Enums;

enum ConfirmationResult
{
    case Confirmed;

    /** Уведомление с таким provider_message_id не найдено */
    case NotFound;

    /** Переход статуса невозможен (например, уже delivered/failed) */
    case Conflict;
}
