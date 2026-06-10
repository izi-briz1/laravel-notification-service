<?php

namespace Tests\Unit;

use App\Enums\NotificationStatus;
use PHPUnit\Framework\TestCase;

class NotificationStatusTest extends TestCase
{
    public function test_allowed_transitions(): void
    {
        $this->assertTrue(NotificationStatus::Queued->canTransitionTo(NotificationStatus::Sending));
        $this->assertTrue(NotificationStatus::Sending->canTransitionTo(NotificationStatus::Sent));
        $this->assertTrue(NotificationStatus::Sending->canTransitionTo(NotificationStatus::Queued));
        $this->assertTrue(NotificationStatus::Sent->canTransitionTo(NotificationStatus::Delivered));
        $this->assertTrue(NotificationStatus::Sent->canTransitionTo(NotificationStatus::Failed));
    }

    public function test_forbidden_transitions(): void
    {
        $this->assertFalse(NotificationStatus::Queued->canTransitionTo(NotificationStatus::Sent));
        $this->assertFalse(NotificationStatus::Queued->canTransitionTo(NotificationStatus::Delivered));
        $this->assertFalse(NotificationStatus::Delivered->canTransitionTo(NotificationStatus::Failed));
        $this->assertFalse(NotificationStatus::Failed->canTransitionTo(NotificationStatus::Queued));
    }

    public function test_final_statuses(): void
    {
        $this->assertTrue(NotificationStatus::Delivered->isFinal());
        $this->assertTrue(NotificationStatus::Failed->isFinal());
        $this->assertFalse(NotificationStatus::Queued->isFinal());
        $this->assertFalse(NotificationStatus::Sent->isFinal());
    }
}
