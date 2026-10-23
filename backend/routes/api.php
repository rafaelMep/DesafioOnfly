<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\DestinationController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\TravelOrderController;
use Illuminate\Support\Facades\Route;

Route::post('auth/login', [AuthController::class, 'login'])->middleware('throttle:5,1');

Route::middleware(['auth:api', 'throttle:60,1'])->group(function () {
    Route::get('auth/me', [AuthController::class, 'me']);
    Route::get('destinations', DestinationController::class);
    Route::post('auth/logout', [AuthController::class, 'logout']);
    Route::get('notifications', [NotificationController::class, 'index']);
    Route::get('notifications/unread-count', [NotificationController::class, 'unreadCount']);
    Route::patch('notifications/read-all', [NotificationController::class, 'markAllRead']);
    Route::patch('notifications/{notification}/read', [NotificationController::class, 'markRead'])->whereUuid('notification');
    Route::get('travel-orders', [TravelOrderController::class, 'index']);
    Route::get('travel-orders/summary', [TravelOrderController::class, 'summary']);
    Route::post('travel-orders', [TravelOrderController::class, 'store']);
    Route::get('travel-orders/{travelOrder}', [TravelOrderController::class, 'show']);
    Route::patch('travel-orders/{travelOrder}/status', [TravelOrderController::class, 'updateStatus']);
});
