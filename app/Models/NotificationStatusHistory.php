<?php

namespace App\Models;

use App\Enums\NotificationStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NotificationStatusHistory extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'notification_status_history';

    protected $fillable = [
        'notification_id',
        'status',
        'info',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => NotificationStatus::class,
            'created_at' => 'datetime',
        ];
    }

    public function notification(): BelongsTo
    {
        return $this->belongsTo(Notification::class);
    }
}
