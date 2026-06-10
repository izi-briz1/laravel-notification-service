<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Notification */
class NotificationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'batch_id' => $this->batch_id,
            'recipient_id' => $this->recipient_id,
            'channel' => $this->channel,
            'priority' => $this->priority,
            'body' => $this->body,
            'status' => $this->status,
            'attempts' => $this->attempts,
            'provider_message_id' => $this->provider_message_id,
            'error_message' => $this->error_message,
            'queued_at' => $this->queued_at?->toIso8601String(),
            'sent_at' => $this->sent_at?->toIso8601String(),
            'delivered_at' => $this->delivered_at?->toIso8601String(),
            'failed_at' => $this->failed_at?->toIso8601String(),
            'status_history' => NotificationStatusHistoryResource::collection($this->whenLoaded('statusHistory')),
        ];
    }
}
