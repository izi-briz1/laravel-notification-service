<?php

namespace Tests\Feature;

use App\Enums\NotificationStatus;
use App\Jobs\SendNotificationJob;
use App\Models\Notification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class StuckSendingSweeperTest extends TestCase
{
    use RefreshDatabase;

    public function test_stuck_sending_notification_is_requeued_and_republished(): void
    {
        Queue::fake();

        // Воркер захватил сообщение и умер жёстко, не доведя до финального статуса
        $stuck = Notification::factory()->sending()->create();
        $this->ageInSending($stuck, minutes: 10);

        $this->artisan('notifications:requeue-stuck-sending', ['--age' => 300])
            ->expectsOutputToContain('Requeued 1')
            ->assertSuccessful();

        Queue::assertPushedOn('transactional', SendNotificationJob::class);

        $stuck->refresh();
        $this->assertSame(NotificationStatus::Queued, $stuck->status);
        $this->assertNotNull($stuck->dispatched_at);
        $this->assertSame(NotificationStatus::Queued, $stuck->statusHistory->last()->status);
    }

    public function test_fresh_sending_notification_is_not_touched(): void
    {
        Queue::fake();

        // Отправка, возможно, ещё идёт — живой воркер не трогаем
        $active = Notification::factory()->sending()->create();

        $this->artisan('notifications:requeue-stuck-sending', ['--age' => 300])
            ->expectsOutputToContain('Requeued 0')
            ->assertSuccessful();

        Queue::assertNothingPushed();
        $this->assertSame(NotificationStatus::Sending, $active->refresh()->status);
    }

    public function test_exhausted_notification_is_failed_instead_of_requeued(): void
    {
        Queue::fake();

        $exhausted = Notification::factory()->sending()->create([
            'attempts' => SendNotificationJob::MAX_SEND_ATTEMPTS,
        ]);
        $this->ageInSending($exhausted, minutes: 10);

        $this->artisan('notifications:requeue-stuck-sending', ['--age' => 300])
            ->expectsOutputToContain('failed 1')
            ->assertSuccessful();

        Queue::assertNothingPushed();
        $this->assertSame(NotificationStatus::Failed, $exhausted->refresh()->status);
    }

    /** Состарить запись в sending: update мимо модели, чтобы не затронуть updated_at автоматически */
    private function ageInSending(Notification $notification, int $minutes): void
    {
        Notification::query()
            ->whereKey($notification->getKey())
            ->update(['updated_at' => now()->subMinutes($minutes)]);
    }
}
