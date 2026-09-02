<?php

use App\Http\Controllers\AdminController;
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\PlatformController;
use Illuminate\Support\Facades\Route;

// Public routes
Route::get('/', fn () => redirect('/login'));
Route::get('/login', [AuthController::class, 'loginForm'])->name('login');
Route::post('/login', [AuthController::class, 'login']);
Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

// Admin panel (auth required)
Route::middleware(['auth', 'organization'])->prefix('admin')->group(function () {
    // Dashboard
    Route::get('/', [AdminController::class, 'dashboard']);

    // Donors
    Route::get('/donadores', [AdminController::class, 'donadores']);
    Route::get('/export/donadores', [AdminController::class, 'exportDonadores']);

    // Contacts
    Route::get('/contacts', [AdminController::class, 'contacts']);

    // Donations/Receipts
    Route::get('/donations', [AdminController::class, 'donations']);
    Route::get('/donations/{id}/receipt', [AdminController::class, 'donationReceipt']);
    Route::post('/donations/{id}/verify', [AdminController::class, 'verifyDonation']);
    Route::post('/donations/{id}/reject', [AdminController::class, 'rejectDonation']);

    // Conversation
    Route::get('/contacts/{id}/chat', [AdminController::class, 'conversation']);
    Route::post('/contacts/{id}/send', [AdminController::class, 'sendMessage']);
    Route::post('/contacts/{id}/status', [AdminController::class, 'changeStatus']);

    // Campaigns
    Route::get('/campaigns', [AdminController::class, 'campaigns']);
    Route::get('/campaign/create', [AdminController::class, 'campaignCreate']);
    Route::post('/campaign/launch', [AdminController::class, 'campaignLaunch']);
    Route::get('/campaign/{id}', [AdminController::class, 'campaignDetail']);

    // Import
    Route::get('/import', [AdminController::class, 'importForm']);
    Route::post('/import', [AdminController::class, 'import']);

    Route::get('/onboarding', [PlatformController::class, 'onboarding'])->name('onboarding');
    Route::get('/integrations', [PlatformController::class, 'integrations'])->name('integrations');
    Route::post('/integrations/whatsapp', [PlatformController::class, 'saveWhatsApp'])->name('whatsapp.save');
    Route::get('/templates', [PlatformController::class, 'templates'])->name('templates');
    Route::post('/templates', [PlatformController::class, 'saveTemplate'])->name('templates.save');
    Route::post('/templates/{template}/publish', [PlatformController::class, 'publishTemplate'])->name('templates.publish');
    Route::get('/flows', [PlatformController::class, 'flows'])->name('flows');
    Route::post('/flows', [PlatformController::class, 'saveFlow'])->name('flows.save');
    Route::get('/billing', [PlatformController::class, 'billing'])->name('billing');
    Route::post('/billing/setup', [PlatformController::class, 'startBillingSetup'])->name('billing.setup');
    Route::get('/billing/complete', [PlatformController::class, 'completeBilling'])->name('billing.complete');
    Route::get('/clients', [PlatformController::class, 'clients'])->name('clients');
    Route::post('/clients', [PlatformController::class, 'createClient'])->name('clients.create');
});
