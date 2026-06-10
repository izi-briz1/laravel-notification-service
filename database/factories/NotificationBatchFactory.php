<?php

namespace Database\Factories;

use App\Enums\Channel;
use App\Enums\Priority;
use App\Models\NotificationBatch;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<NotificationBatch> */
class NotificationBatchFactory extends Factory
{
    protected $model = NotificationBatch::class;

    public function definition(): array
    {
        return [
            'idempotency_key' => (string) Str::uuid(),
            'channel' => Channel::Sms,
            'priority' => Priority::Transactional,
            'body' => $this->faker->sentence(),
            'total' => 1,
        ];
    }
}
