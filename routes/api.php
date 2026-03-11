<?php

use App\Http\Controllers\Api\ChatController;
use App\Http\Controllers\Api\ContactController;
use App\Http\Controllers\Api\DemoPaymentController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

// Public API endpoints
Route::post('/chat', [ChatController::class, 'send']);
Route::post('/contact', [ContactController::class, 'send']);

// Demo 付款（綠界測試環境）
Route::post('/demo/checkout',        [DemoPaymentController::class, 'checkout']);
Route::post('/demo/payment-return',  [DemoPaymentController::class, 'paymentReturn']);
Route::post('/demo/payment-result',  [DemoPaymentController::class, 'paymentResult']);
