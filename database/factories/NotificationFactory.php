<?php

namespace Database\Factories;

use App\Enums\Channel;
use App\Enums\NotificationStatus;
use App\Enums\Priority;
use App\Models\Notification;
use App\Models\NotificationBatch;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<Notification> */
class NotificationFactory extends Factory
{
    protected $model = Notification::class;

    public function definition(): array
    {
        return [
            'batch_id' => NotificationBatch::factory(),
            'recipient_id' => 'user-'.$this->faker->unique()->numberBetween(1, 100000),
            'channel' => Channel::Sms,
            'priority' => Priority::Transactional,
            'body' => $this->faker->sentence(),
            'status' => NotificationStatus::Queued,
            'queued_at' => now(),
        ];
    }

    public function sending(): static
    {
        return $this->state(fn () => [
            'status' => NotificationStatus::Sending,
            'attempts' => 1,
            'dispatched_at' => now(),
        ]);
    }

    public function sent(): static
    {
        return $this->state(fn () => [
            'status' => NotificationStatus::Sent,
            'attempts' => 1,
            'provider_message_id' => (string) Str::uuid(),
            'dispatched_at' => now(),
            'sent_at' => now(),
        ]);
    }

    public function dispatched(): static
    {
        return $this->state(fn () => ['dispatched_at' => now()]);
    }
}
