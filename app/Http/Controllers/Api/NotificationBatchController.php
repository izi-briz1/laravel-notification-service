<?php

namespace App\Http\Controllers\Api;

use App\Actions\CreateNotificationBatchAction;
use App\DataTransferObjects\CreateNotificationBatchData;
use App\Enums\Channel;
use App\Enums\Priority;
use App\Http\Controllers\Controller;
use App\Http\Requests\CreateNotificationBatchRequest;
use App\Http\Resources\NotificationBatchResource;
use App\Models\NotificationBatch;
use Illuminate\Http\JsonResponse;
use OpenApi\Attributes as OA;

class NotificationBatchController extends Controller
{
    #[OA\Post(
        path: '/api/v1/notifications',
        summary: 'Запустить массовую рассылку SMS или Email',
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['channel', 'priority', 'text', 'recipient_ids'],
                properties: [
                    new OA\Property(property: 'channel', type: 'string', enum: ['sms', 'email']),
                    new OA\Property(property: 'priority', type: 'string', enum: ['transactional', 'marketing'], description: 'Транзакционные сообщения обрабатываются выделенными воркерами вне очереди маркетинговых'),
                    new OA\Property(property: 'text', type: 'string', example: 'Ваш код доступа: 1234'),
                    new OA\Property(property: 'recipient_ids', type: 'array', items: new OA\Items(type: 'string'), example: ['user-1', 'user-2']),
                ],
            ),
        ),
        tags: ['Notifications'],
        parameters: [
            new OA\Parameter(name: 'Idempotency-Key', in: 'header', required: true, description: 'Ключ дедубликации: повторный запрос с тем же ключом не создаёт новую рассылку', schema: new OA\Schema(type: 'string')),
        ],
        responses: [
            new OA\Response(response: 201, description: 'Рассылка принята в обработку'),
            new OA\Response(response: 200, description: 'Дубликат запроса: возвращена ранее созданная рассылка'),
            new OA\Response(response: 422, description: 'Ошибка валидации'),
            new OA\Response(response: 429, description: 'Превышен лимит запросов к API'),
        ],
    )]
    public function store(CreateNotificationBatchRequest $request, CreateNotificationBatchAction $action): JsonResponse
    {
        $result = $action->execute(new CreateNotificationBatchData(
            channel: Channel::from($request->validated('channel')),
            priority: Priority::from($request->validated('priority')),
            body: $request->validated('text'),
            recipientIds: $request->validated('recipient_ids'),
            idempotencyKey: $request->validated('idempotency_key'),
        ));

        return (new NotificationBatchResource($result['batch']))
            ->response()
            ->setStatusCode($result['created'] ? 201 : 200);
    }

    #[OA\Get(
        path: '/api/v1/notifications/batches/{id}',
        summary: 'Сводка по рассылке со счётчиками статусов',
        tags: ['Notifications'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Сводка по рассылке'),
            new OA\Response(response: 404, description: 'Рассылка не найдена'),
        ],
    )]
    public function show(NotificationBatch $batch): JsonResponse
    {
        $statusCounts = $batch->notifications()
            ->selectRaw('status, count(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status');

        return (new NotificationBatchResource($batch))
            ->additional(['status_counts' => $statusCounts])
            ->response();
    }
}
