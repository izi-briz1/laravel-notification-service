<?php

namespace Tests\Feature;

use App\Enums\NotificationStatus;
use App\Jobs\SendNotificationJob;
use App\Models\Notification;
use App\Models\NotificationBatch;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class CreateNotificationBatchTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'channel' => 'sms',
            'priority' => 'transactional',
            'text' => 'Ваш код доступа: 1234',
            'recipient_ids' => ['user-1', 'user-2', 'user-3'],
        ], $overrides);
    }

    public function test_batch_is_created_with_queued_notifications_and_history(): void
    {
        Queue::fake();

        $response = $this->postJson('/api/v1/notifications', $this->payload(), [
            'Idempotency-Key' => 'key-1',
        ]);

        $response->assertCreated();

        $batch = NotificationBatch::sole();
        $this->assertSame('key-1', $batch->idempotency_key);
        $this->assertSame(3, $batch->total);

        $this->assertSame(3, Notification::count());
        Notification::all()->each(function (Notification $notification) {
            $this->assertSame(NotificationStatus::Queued, $notification->status);
            $this->assertNotNull($notification->dispatched_at, 'outbox-маркер публикации должен быть проставлен');
            $this->assertCount(1, $notification->statusHistory);
            $this->assertSame(NotificationStatus::Queued, $notification->statusHistory->first()->status);
        });
    }

    public function test_transactional_jobs_are_routed_to_transactional_queue(): void
    {
        Queue::fake();

        $this->postJson('/api/v1/notifications', $this->payload(), ['Idempotency-Key' => 'key-1'])
            ->assertCreated();

        Queue::assertPushedOn('transactional', SendNotificationJob::class);
        Queue::assertPushed(SendNotificationJob::class, 3);
    }

    public function test_marketing_jobs_are_routed_to_marketing_queue(): void
    {
        Queue::fake();

        $this->postJson('/api/v1/notifications', $this->payload(['priority' => 'marketing']), [
            'Idempotency-Key' => 'key-1',
        ])->assertCreated();

        Queue::assertPushedOn('marketing', SendNotificationJob::class);
    }

    public function test_duplicate_idempotency_key_does_not_create_second_batch(): void
    {
        Queue::fake();

        $first = $this->postJson('/api/v1/notifications', $this->payload(), ['Idempotency-Key' => 'key-1']);
        $first->assertCreated();

        $second = $this->postJson('/api/v1/notifications', $this->payload(), ['Idempotency-Key' => 'key-1']);
        $second->assertOk();

        $this->assertSame($first->json('data.id'), $second->json('data.id'));
        $this->assertSame(1, NotificationBatch::count());
        $this->assertSame(3, Notification::count());
        Queue::assertPushed(SendNotificationJob::class, 3);
    }

    public function test_duplicate_is_detected_even_without_redis_key(): void
    {
        Queue::fake();

        $this->postJson('/api/v1/notifications', $this->payload(), ['Idempotency-Key' => 'key-1'])
            ->assertCreated();

        // Имитация потери ключа в Redis: источником истины остаётся БД
        Cache::flush();

        $this->postJson('/api/v1/notifications', $this->payload(), ['Idempotency-Key' => 'key-1'])
            ->assertOk();

        $this->assertSame(1, NotificationBatch::count());
    }

    public function test_idempotency_key_header_is_required(): void
    {
        $this->postJson('/api/v1/notifications', $this->payload())
            ->assertUnprocessable()
            ->assertJsonValidationErrors('idempotency_key');
    }

    public function test_structural_validation(): void
    {
        $this->postJson('/api/v1/notifications', $this->payload(['channel' => 'pigeon']), ['Idempotency-Key' => 'k'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('channel');

        $this->postJson('/api/v1/notifications', $this->payload(['recipient_ids' => []]), ['Idempotency-Key' => 'k'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('recipient_ids');

        $this->postJson('/api/v1/notifications', $this->payload(['priority' => 'urgent']), ['Idempotency-Key' => 'k'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('priority');
    }

    public function test_recipient_ids_are_accepted_as_is_without_format_validation(): void
    {
        Queue::fake();

        $this->postJson('/api/v1/notifications', $this->payload([
            'recipient_ids' => ['42', 'not-a-phone', 'абонент@кириллица'],
        ]), ['Idempotency-Key' => 'key-1'])->assertCreated();

        $this->assertSame(
            ['42', 'not-a-phone', 'абонент@кириллица'],
            Notification::orderBy('id')->pluck('recipient_id')->sort()->values()->all(),
        );
    }
}
