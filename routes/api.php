<?php

use App\Http\Controllers\ParcelBotController;
use App\Http\Controllers\HttpSmsWebhookController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// Route::get('/user', function (Request $request) {
//     return $request->user();
// })->middleware('auth:sanctum');

Route::post('/whatsapp/webhook', [ParcelBotController::class, 'handleWebhook']);
Route::post('/webhooks/httpsms', [HttpSmsWebhookController::class, 'handle']);