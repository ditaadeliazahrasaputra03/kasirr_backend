<?php

use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\WebhookController;
use Illuminate\Support\Facades\Route;

Route::get('/health', fn () => response()->json([
    'success' => true,
    'message' => 'POS backend OK',
]));

Route::post('/payments', [PaymentController::class, 'store']);
Route::get('/payments', [PaymentController::class, 'index']);
Route::get('/payments/{reference}', [PaymentController::class, 'show']);
Route::get('/payments/{reference}/status', [PaymentController::class, 'status']);

Route::post('/webhooks/miomipay', [WebhookController::class, 'handle']);
