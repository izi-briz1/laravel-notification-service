<?php

namespace Tests\Feature;

use App\Enums\Channel;
use App\Enums\NotificationStatus;
use App\Gateways\Exceptions\PermanentGatewayException;
use App\Gateways\Exceptions\TransientGatewayException;
use App\Gateways\FakeSmsGateway;
use App\Gateways\GatewayFactory;
use App\Jobs\SendNotificationJob;
use App\Models\Notification;
use App\Services\NotificationStatusService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\SpyGateway;
use Tests\TestCase;

/**
 * Интеграционные тесты полной цепочки: получение сообщения (job) ->
 * вызов провайдера -> изменение статуса и истории в БД.
 */
class SendNotificationJobTest extends TestCase
{
    use RefreshDatabase;

    private SpyGateway $gateway;

    protected function setUp(): void
    {
        parent::setUp();

        $this->gateway = new SpyGateway;
        $this->app->instance(FakeSmsGateway::class, $this->gateway);
    }

    private function runJob(Notification $notification): void
    {
        (new SendNotificationJob($notification->id, Channel::Sms))->handle(
            app(NotificationStatusService::class),
            app(GatewayFactory::class),
        );
    }

    public function test_full_chain_queued_to_sent_with_single_provider_call(): void
    {
        $notification = Notification::factory()->dispatched()->create();
        // queued-историю пишет batch-action при создании рассылки
        $notification->statusHistory()->create(['status' => NotificationStatus::Queued]);

        $this->runJob($notification);

        $notification->refresh();
        $this->assertSame(1, $this->gateway->calls);
        $this->assertSame(NotificationStatus::Sent, $notification->status);
        $this->assertSame($this->gateway->lastProviderMessageId, $notification->provider_message_id);
        $this->assertSame(1, $notification->attempts);
        $this->assertNotNull($notification->sent_at);

        $this->assertSame(
            ['queued', 'sending', 'sent'],
            $notification->statusHistory->map(fn ($h) => $h->status->value)->all(),
        );
    }

    public function test_redelivery_of_processed_message_does_not_call_provider_again(): void
    {
        $notification = Notification::factory()->dispatched()->create();
        // queued-история пишется при создании batch'а; фабрика её не создаёт,
        // поэтому добавляем вручную для полноты картины
        $notification->statusHistory()->create(['status' => NotificationStatus::Queued]);

        // At-least-once: брокер доставил одно и то же сообщение дважды
        $this->runJob($notification);
        $this->runJob($notification);

        $this->assertSame(1, $this->gateway->calls, 'exactly-once: провайдер должен быть вызван ровно один раз');
        $this->assertSame(NotificationStatus::Sent, $notification->refresh()->status);
    }

    public function test_transient_failure_returns_message_to_queue_for_retry(): void
    {
        $this->gateway->throwQueue[] = new TransientGatewayException('gateway timeout');

        $notification = Notification::factory()->dispatched()->create();
        $this->runJob($notification);

        $notification->refresh();
        $this->assertSame(NotificationStatus::Queued, $notification->status);
        $this->assertSame(1, $notification->attempts);
        $this->assertSame('gateway timeout', $notification->error_message);

        // Следующая попытка успешна
        $this->runJob($notification);
        $this->assertSame(NotificationStatus::Sent, $notification->refresh()->status);
        $this->assertSame(2, $notification->attempts);
        $this->assertSame(2, $this->gateway->calls);
    }

    public function test_permanent_failure_marks_notification_failed_immediately(): void
    {
        $this->gateway->throwQueue[] = new PermanentGatewayException('recipient does not exist');

        $notification = Notification::factory()->dispatched()->create();
        $this->runJob($notification);

        $notification->refresh();
        $this->assertSame(NotificationStatus::Failed, $notification->status);
        $this->assertSame('recipient does not exist', $notification->error_message);
        $this->assertNotNull($notification->failed_at);
        $this->assertSame(1, $this->gateway->calls);

        // Финальный статус: повторная доставка ничего не меняет
        $this->runJob($notification);
        $this->assertSame(1, $this->gateway->calls);
    }

    public function test_exhausted_attempts_mark_notification_failed(): void
    {
        $notification = Notification::factory()->dispatched()->create([
            'attempts' => SendNotificationJob::MAX_SEND_ATTEMPTS - 1,
        ]);
        $this->gateway->throwQueue[] = new TransientGatewayException('still down');

        $this->runJob($notification);

        $notification->refresh();
        $this->assertSame(NotificationStatus::Failed, $notification->status);
        $this->assertStringContainsString('max send attempts exceeded', $notification->error_message);
    }
}
