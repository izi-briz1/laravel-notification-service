<?php

namespace App\Actions;

use App\DataTransferObjects\CreateNotificationBatchData;
use App\Enums\NotificationStatus;
use App\Jobs\SendNotificationJob;
use App\Models\Notification;
use App\Models\NotificationBatch;
use App\Models\NotificationStatusHistory;
use App\Services\IdempotencyService;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Создание массовой рассылки.
 *
 * Гарантии:
 *  - идемпотентность: Redis (быстрый путь) + unique на idempotency_key (истина);
 *  - transactional outbox: batch и уведомления (status=queued) коммитятся
 *    одной транзакцией, публикация в RabbitMQ — после коммита с пометкой
 *    dispatched_at; упавшую между коммитом и публикацией пачку доберёт
 *    outbox-свипер (notifications:dispatch-stuck).
 */
class CreateNotificationBatchAction
{
    private const INSERT_CHUNK_SIZE = 500;

    public function __construct(private IdempotencyService $idempotency) {}

    /**
     * @return array{batch: NotificationBatch, created: bool}
     */
    public function execute(CreateNotificationBatchData $data): array
    {
        $existing = $this->findExisting($data->idempotencyKey);
        if ($existing !== null) {
            return ['batch' => $existing, 'created' => false];
        }

        if (! $this->idempotency->claim($data->idempotencyKey)) {
            // Ключ держит параллельный запрос: ждать его коммита нельзя,
            // поэтому либо batch уже виден, либо отдаём дубликат как not-created
            $existing = $this->findExisting($data->idempotencyKey);
            if ($existing !== null) {
                return ['batch' => $existing, 'created' => false];
            }
        }

        try {
            $batch = DB::transaction(fn () => $this->createBatch($data));
        } catch (UniqueConstraintViolationException) {
            // Гонка двух запросов с одним ключом: победителя читаем из БД
            return ['batch' => $this->findExisting($data->idempotencyKey), 'created' => false];
        } catch (\Throwable $e) {
            $this->idempotency->release($data->idempotencyKey);

            throw $e;
        }

        $this->dispatchBatch($batch);

        return ['batch' => $batch, 'created' => true];
    }

    private function findExisting(string $idempotencyKey): ?NotificationBatch
    {
        return NotificationBatch::query()->where('idempotency_key', $idempotencyKey)->first();
    }

    private function createBatch(CreateNotificationBatchData $data): NotificationBatch
    {
        $batch = NotificationBatch::query()->create([
            'idempotency_key' => $data->idempotencyKey,
            'channel' => $data->channel,
            'priority' => $data->priority,
            'body' => $data->body,
            'total' => count($data->recipientIds),
        ]);

        $now = now();

        foreach (array_chunk($data->recipientIds, self::INSERT_CHUNK_SIZE) as $chunk) {
            $rows = [];
            $historyRows = [];

            foreach ($chunk as $recipientId) {
                $id = (string) Str::uuid7();
                $rows[] = [
                    'id' => $id,
                    'batch_id' => $batch->id,
                    'recipient_id' => (string) $recipientId,
                    'channel' => $data->channel->value,
                    'priority' => $data->priority->value,
                    'body' => $data->body,
                    'status' => NotificationStatus::Queued->value,
                    'queued_at' => $now,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
                $historyRows[] = [
                    'notification_id' => $id,
                    'status' => NotificationStatus::Queued->value,
                    'created_at' => $now,
                ];
            }

            Notification::query()->insert($rows);
            NotificationStatusHistory::query()->insert($historyRows);
        }

        return $batch;
    }

    /** Outbox fast-path: публикация после коммита + пометка dispatched_at */
    private function dispatchBatch(NotificationBatch $batch): void
    {
        $batch->notifications()
            ->where('status', NotificationStatus::Queued)
            ->whereNull('dispatched_at')
            ->select(['id', 'channel', 'priority'])
            ->chunkById(self::INSERT_CHUNK_SIZE, function ($notifications) {
                foreach ($notifications as $notification) {
                    SendNotificationJob::dispatch($notification->id, $notification->channel)
                        ->onQueue($notification->priority->queue());
                }

                Notification::query()
                    ->whereIn('id', $notifications->pluck('id'))
                    ->update(['dispatched_at' => now()]);
            });
    }
}
