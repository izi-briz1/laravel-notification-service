<?php

namespace App\DataTransferObjects;

use App\Enums\Channel;
use App\Enums\Priority;

final readonly class CreateNotificationBatchData
{
    /**
     * @param list<string> $recipientIds
     */
    public function __construct(
        public Channel $channel,
        public Priority $priority,
        public string $body,
        public array $recipientIds,
        public string $idempotencyKey,
    ) {}
}
