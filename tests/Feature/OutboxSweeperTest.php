<?php

namespace Tests\Feature;

use App\Jobs\SendNotificationJob;
use App\Models\Notification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class OutboxSweeperTest extends TestCase
{
    use RefreshDatabase;

    public function test_committed_but_undispatched_notifications_are_republished(): void
    {
        Queue::fake();

        // Упали между коммитом транзакции и публикацией в брокер
        $stuck = Notification::factory()->create();
        $stuck->forceFill(['created_at' => now()->subMinutes(2)])->save();

        $this->artisan('notifications:dispatch-stuck', ['--age' => 60])
            ->expectsOutputToContain('Republished 1')
            ->assertSuccessful();

        Queue::assertPushedOn('transactional', SendNotificationJob::class);
        $this->assertNotNull($stuck->refresh()->dispatched_at);
    }

    public function test_fresh_and_already_dispatched_notifications_are_skipped(): void
    {
        Queue::fake();

        // Свежая запись: возможно, dispatch ещё выполняется — не трогаем
        Notification::factory()->create();

        // Уже опубликованная
        $dispatched = Notification::factory()->dispatched()->create();
        $dispatched->forceFill(['created_at' => now()->subMinutes(2)])->save();

        $this->artisan('notifications:dispatch-stuck', ['--age' => 60])
            ->expectsOutputToContain('Republished 0')
            ->assertSuccessful();

        Queue::assertNothingPushed();
    }
}
