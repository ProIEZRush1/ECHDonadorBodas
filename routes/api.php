<?php

use App\Http\Controllers\StripeWebhookController;
use App\Http\Controllers\WebhookController;
use Illuminate\Support\Facades\Route;

// WhatsApp webhook
Route::get('/webhook', [WebhookController::class, 'verify']);
Route::post('/webhook', [WebhookController::class, 'handle']);

// Stripe webhook
Route::post('/stripe/webhook', [StripeWebhookController::class, 'handle']);
