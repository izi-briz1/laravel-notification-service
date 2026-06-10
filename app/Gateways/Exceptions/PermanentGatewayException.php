<?php

namespace App\Gateways\Exceptions;

use RuntimeException;

/** Неустранимая ошибка (несуществующий номер/email, невалидный адресат) — ретраи бесполезны */
class PermanentGatewayException extends RuntimeException {}
