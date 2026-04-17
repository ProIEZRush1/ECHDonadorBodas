<?php

use App\Http\Controllers\AdminController;
use App\Http\Controllers\Auth\AuthController;
use Illuminate\Support\Facades\Route;

// Public routes
Route::get('/', fn() => redirect('/login'));
Route::get('/login', [AuthController::class, 'loginForm'])->name('login');
Route::post('/login', [AuthController::class, 'login']);
Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

// Admin panel (auth required)
Route::middleware('auth')->prefix('admin')->group(function () {
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
});
