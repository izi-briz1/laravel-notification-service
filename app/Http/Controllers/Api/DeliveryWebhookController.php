<?php

namespace App\Http\Controllers\Api;

use App\Enums\ConfirmationResult;
use App\Http\Controllers\Controller;
use App\Http\Requests\DeliveryWebhookRequest;
use App\Services\DeliveryConfirmationService;
use Illuminate\Http\JsonResponse;
use OpenApi\Attributes as OA;

class DeliveryWebhookController extends Controller
{
    #[OA\Post(
        path: '/api/v1/webhooks/delivery',
        summary: 'DLR-колбэк провайдера: подтверждение или отклонение доставки',
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['provider_message_id', 'status'],
                properties: [
                    new OA\Property(property: 'provider_message_id', type: 'string', format: 'uuid'),
                    new OA\Property(property: 'status', type: 'string', enum: ['delivered', 'failed']),
                    new OA\Property(property: 'info', type: 'string', nullable: true, example: 'handset unreachable'),
                ],
            ),
        ),
        tags: ['Webhooks'],
        responses: [
            new OA\Response(response: 200, description: 'Статус обновлён'),
            new OA\Response(response: 404, description: 'Сообщение с таким provider_message_id не найдено'),
            new OA\Response(response: 409, description: 'Переход статуса невозможен (уже в финальном статусе)'),
        ],
    )]
    public function store(DeliveryWebhookRequest $request, DeliveryConfirmationService $confirmations): JsonResponse
    {
        $result = $confirmations->confirm(
            providerMessageId: $request->validated('provider_message_id'),
            delivered: $request->validated('status') === 'delivered',
            info: $request->validated('info'),
        );

        return match ($result) {
            ConfirmationResult::Confirmed => response()->json(['message' => 'status updated']),
            ConfirmationResult::NotFound => response()->json(['message' => 'unknown provider_message_id'], 404),
            ConfirmationResult::Conflict => response()->json(['message' => 'status transition not allowed'], 409),
        };
    }
}
