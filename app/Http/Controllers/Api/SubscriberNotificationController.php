<?php

namespace App\Http\Controllers\Api;

use App\Enums\Channel;
use App\Enums\NotificationStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\NotificationResource;
use App\Models\Notification;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;
use OpenApi\Attributes as OA;

class SubscriberNotificationController extends Controller
{
    #[OA\Get(
        path: '/api/v1/subscribers/{subscriberId}/notifications',
        summary: 'История и текущий статус всех уведомлений подписчика',
        tags: ['Subscribers'],
        parameters: [
            new OA\Parameter(name: 'subscriberId', in: 'path', required: true, description: 'Идентификатор получателя, как он был передан при создании рассылки', schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'status', in: 'query', required: false, schema: new OA\Schema(type: 'string', enum: ['queued', 'sending', 'sent', 'delivered', 'failed'])),
            new OA\Parameter(name: 'channel', in: 'query', required: false, schema: new OA\Schema(type: 'string', enum: ['sms', 'email'])),
            new OA\Parameter(name: 'per_page', in: 'query', required: false, schema: new OA\Schema(type: 'integer', default: 25, maximum: 100)),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Пагинированный список уведомлений с историей переходов статусов'),
            new OA\Response(response: 422, description: 'Некорректные параметры фильтра'),
        ],
    )]
    public function index(Request $request, string $subscriberId): AnonymousResourceCollection
    {
        $filters = $request->validate([
            'status' => ['sometimes', Rule::enum(NotificationStatus::class)],
            'channel' => ['sometimes', Rule::enum(Channel::class)],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);

        $notifications = Notification::query()
            ->where('recipient_id', $subscriberId)
            ->when($filters['status'] ?? null, fn ($q, $status) => $q->where('status', $status))
            ->when($filters['channel'] ?? null, fn ($q, $channel) => $q->where('channel', $channel))
            ->with('statusHistory')
            ->orderByDesc('created_at')
            ->paginate((int) ($filters['per_page'] ?? 25));

        return NotificationResource::collection($notifications);
    }
}
