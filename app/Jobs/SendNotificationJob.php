<?php

namespace App\Jobs;

use App\Enums\Channel;
use App\Enums\NotificationStatus;
use App\Gateways\Exceptions\PermanentGatewayException;
use App\Gateways\Exceptions\TransientGatewayException;
use App\Gateways\GatewayFactory;
use App\Jobs\Middleware\ChannelRateLimited;
use App\Models\Notification;
use App\Services\NotificationStatusService;
use DateTime;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class SendNotificationJob implements ShouldQueue
{
    use Queueable;

    /** Максимум бизнес-попыток отправки (считается по notifications.attempts в БД) */
    public const MAX_SEND_ATTEMPTS = 5;

    /**
     * Инфраструктурные возвраты в очередь (rate limit) не должны убивать job,
     * поэтому вместо $tries — дедлайн по времени; бизнес-попытки ограничены
     * MAX_SEND_ATTEMPTS вручную.
     */
    public function retryUntil(): DateTime
    {
        return now()->addHour();
    }

    public function __construct(
        public string $notificationId,
        public Channel $channel,
    ) {}

    public function middleware(): array
    {
        return [new ChannelRateLimited];
    }

    public function handle(NotificationStatusService $statuses, GatewayFactory $gateways): void
    {
        $notification = Notification::find($this->notificationId);

        if ($notification === null) {
            return;
        }

        // Exactly-once гард: при redelivery того же сообщения (at-least-once
        // от брокера) условный UPDATE выиграет только один раз, провайдер
        // повторно не вызывается
        if (! $statuses->claimForSending($notification)) {
            return;
        }

        try {
            $response = $gateways->for($notification->channel)->send($notification);
        } catch (PermanentGatewayException $e) {
            $statuses->markFailed($notification, $e->getMessage());

            return;
        } catch (TransientGatewayException $e) {
            $statuses->releaseForRetry($notification, $e->getMessage());

            if ($notification->attempts >= self::MAX_SEND_ATTEMPTS) {
                $statuses->markFailed(
                    $notification,
                    "max send attempts exceeded ({$notification->attempts}): {$e->getMessage()}",
                    from: NotificationStatus::Queued,
                );

                return;
            }

            $this->release($this->backoffDelay($notification->attempts));

            return;
        }

        $statuses->markSent($notification, $response->providerMessageId);
    }

    /**
     * Экспоненциальная задержка между попытками: 5с, 10с, 20с, 40с...
     *
     * @param int $attempt
     * @return int
     */
    private function backoffDelay(int $attempt): int
    {
        return 5 * (2 ** max(0, $attempt - 1));
    }

    /**
     * Вызывается после retryUntil-дедлайна или при неожиданном исключении,
     * фиксируем отброшенный статус, из какого бы состояния ни пришли
     *
     * @param Throwable|null $exception
     * @return void
     */
    public function failed(?Throwable $exception): void
    {
        $notification = Notification::find($this->notificationId);

        if ($notification === null || $notification->status->isFinal()) {
            return;
        }

        if ($exception !== null) {
            $reason = 'job failed: ' . $exception->getMessage();
        } else {
            $reason = 'job failed: retry deadline exceeded';
        }

        $statuses = app(NotificationStatusService::class);

        $statuses->markFailed($notification, $reason, from: NotificationStatus::Sending)
            || $statuses->markFailed($notification, $reason, from: NotificationStatus::Queued);
    }
}
