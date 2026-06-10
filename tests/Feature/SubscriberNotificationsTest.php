<?php

namespace Tests\Feature;

use App\Enums\Channel;
use App\Enums\NotificationStatus;
use App\Models\Notification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SubscriberNotificationsTest extends TestCase
{
    use RefreshDatabase;

    public function test_returns_only_subscriber_notifications_with_status_history(): void
    {
        Notification::factory()->count(2)->create(['recipient_id' => 'user-42']);
        Notification::factory()->create(['recipient_id' => 'user-42'])
            ->statusHistory()->create(['status' => NotificationStatus::Queued]);
        Notification::factory()->create(['recipient_id' => 'someone-else']);

        $response = $this->getJson('/api/v1/subscribers/user-42/notifications')
            ->assertOk()
            ->assertJsonCount(3, 'data');

        foreach ($response->json('data') as $item) {
            $this->assertSame('user-42', $item['recipient_id']);
            $this->assertArrayHasKey('status_history', $item);
        }
    }

    public function test_filters_by_status_and_channel(): void
    {
        Notification::factory()->create(['recipient_id' => 'user-42']);
        Notification::factory()->sent()->create(['recipient_id' => 'user-42']);
        Notification::factory()->sent()->create([
            'recipient_id' => 'user-42',
            'channel' => Channel::Email,
        ]);

        $this->getJson('/api/v1/subscribers/user-42/notifications?status=sent')
            ->assertOk()
            ->assertJsonCount(2, 'data');

        $this->getJson('/api/v1/subscribers/user-42/notifications?status=sent&channel=email')
            ->assertOk()
            ->assertJsonCount(1, 'data');

        $this->getJson('/api/v1/subscribers/user-42/notifications?status=bogus')
            ->assertUnprocessable();
    }

    public function test_pagination(): void
    {
        Notification::factory()->count(3)->create(['recipient_id' => 'user-42']);

        $response = $this->getJson('/api/v1/subscribers/user-42/notifications?per_page=2')
            ->assertOk()
            ->assertJsonCount(2, 'data');

        $this->assertSame(3, $response->json('meta.total'));
        $this->assertSame(2, $response->json('meta.last_page'));
    }
}
