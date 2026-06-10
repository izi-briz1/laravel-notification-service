<?php

namespace Tests\Feature;

use App\Enums\NotificationStatus;
use App\Models\Notification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DeliveryWebhookTest extends TestCase
{
    use RefreshDatabase;

    public function test_delivered_callback_moves_notification_to_delivered(): void
    {
        $notification = Notification::factory()->sent()->create();

        $this->postJson('/api/v1/webhooks/delivery', [
            'provider_message_id' => $notification->provider_message_id,
            'status' => 'delivered',
        ])->assertOk();

        $notification->refresh();
        $this->assertSame(NotificationStatus::Delivered, $notification->status);
        $this->assertNotNull($notification->delivered_at);
        $this->assertSame(NotificationStatus::Delivered, $notification->statusHistory->last()->status);
    }

    public function test_failed_callback_moves_notification_to_failed(): void
    {
        $notification = Notification::factory()->sent()->create();

        $this->postJson('/api/v1/webhooks/delivery', [
            'provider_message_id' => $notification->provider_message_id,
            'status' => 'failed',
            'info' => 'handset unreachable',
        ])->assertOk();

        $notification->refresh();
        $this->assertSame(NotificationStatus::Failed, $notification->status);
        $this->assertSame('handset unreachable', $notification->error_message);
    }

    public function test_unknown_provider_message_id_returns_404(): void
    {
        $this->postJson('/api/v1/webhooks/delivery', [
            'provider_message_id' => 'does-not-exist',
            'status' => 'delivered',
        ])->assertNotFound();
    }

    public function test_repeated_callback_for_final_status_returns_409(): void
    {
        $notification = Notification::factory()->sent()->create();

        $payload = [
            'provider_message_id' => $notification->provider_message_id,
            'status' => 'delivered',
        ];

        $this->postJson('/api/v1/webhooks/delivery', $payload)->assertOk();
        $this->postJson('/api/v1/webhooks/delivery', $payload)->assertConflict();

        $this->assertSame(NotificationStatus::Delivered, $notification->refresh()->status);
    }
}
