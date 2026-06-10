<?php

namespace App\Models;

use App\Enums\Channel;
use App\Enums\NotificationStatus;
use App\Enums\Priority;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Notification extends Model
{
    use HasUuids;

    protected $fillable = [
        'batch_id',
        'recipient_id',
        'channel',
        'priority',
        'body',
        'status',
        'attempts',
        'provider_message_id',
        'error_message',
        'dispatched_at',
        'queued_at',
        'sent_at',
        'delivered_at',
        'failed_at',
    ];

    protected function casts(): array
    {
        return [
            'channel' => Channel::class,
            'priority' => Priority::class,
            'status' => NotificationStatus::class,
            'attempts' => 'integer',
            'dispatched_at' => 'datetime',
            'queued_at' => 'datetime',
            'sent_at' => 'datetime',
            'delivered_at' => 'datetime',
            'failed_at' => 'datetime',
        ];
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(NotificationBatch::class, 'batch_id');
    }

    public function statusHistory(): HasMany
    {
        return $this->hasMany(NotificationStatusHistory::class)->orderBy('id');
    }
}
