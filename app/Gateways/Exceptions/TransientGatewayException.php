<?php

namespace App\Gateways\Exceptions;

use RuntimeException;

/** Временная ошибка шлюза (недоступность, таймаут, 5xx) — отправку стоит повторить */
class TransientGatewayException extends RuntimeException {}
