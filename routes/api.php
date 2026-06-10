<?php

use App\Http\Controllers\Api\DeliveryWebhookController;
use App\Http\Controllers\Api\NotificationBatchController;
use App\Http\Controllers\Api\SubscriberNotificationController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->middleware('throttle:api')->group(function () {
    Route::post('/notifications', [NotificationBatchController::class, 'store']);
    Route::get('/notifications/batches/{batch}', [NotificationBatchController::class, 'show']);
    Route::get('/subscribers/{subscriberId}/notifications', [SubscriberNotificationController::class, 'index']);
    Route::post('/webhooks/delivery', [DeliveryWebhookController::class, 'store']);
});
