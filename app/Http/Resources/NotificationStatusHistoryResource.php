<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\NotificationStatusHistory */
class NotificationStatusHistoryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'status' => $this->status,
            'info' => $this->info,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
