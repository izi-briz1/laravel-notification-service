<?php

namespace App\Models;

use App\Enums\Channel;
use App\Enums\Priority;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class NotificationBatch extends Model
{
    use HasUuids;

    protected $fillable = [
        'idempotency_key',
        'channel',
        'priority',
        'body',
        'total',
    ];

    protected function casts(): array
    {
        return [
            'channel' => Channel::class,
            'priority' => Priority::class,
            'total' => 'integer',
        ];
    }

    public function notifications(): HasMany
    {
        return $this->hasMany(Notification::class, 'batch_id');
    }
}
