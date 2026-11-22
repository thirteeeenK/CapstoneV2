<?php

use App\Http\Controllers\Admin\AdminEmailCampaignController;
use Illuminate\Support\Facades\Route;

Route::prefix('admin')->middleware(['auth:admin', 'admin.2fa', 'no.cache', 'throttle:admin'])->name('admin.email-campaigns.')->group(function () {
    Route::get('/email-campaigns', [AdminEmailCampaignController::class, 'index'])->name('index');
    Route::get('/email-campaigns/create', [AdminEmailCampaignController::class, 'create'])->name('create');
    Route::post('/email-campaigns', [AdminEmailCampaignController::class, 'store'])->name('store');
    Route::post('/email-campaigns/preview', [AdminEmailCampaignController::class, 'preview'])->name('preview');
    Route::post('/email-campaigns/audience-count', [AdminEmailCampaignController::class, 'audienceCount'])->name('audience-count');
    Route::get('/email-campaigns/{campaign}', [AdminEmailCampaignController::class, 'show'])->name('show');
    Route::get('/email-campaigns/{campaign}/edit', [AdminEmailCampaignController::class, 'edit'])->name('edit');
    Route::put('/email-campaigns/{campaign}', [AdminEmailCampaignController::class, 'update'])->name('update');
    Route::post('/email-campaigns/{campaign}/test', [AdminEmailCampaignController::class, 'sendTest'])->middleware('throttle:5,1')->name('test');
    Route::post('/email-campaigns/{campaign}/send', [AdminEmailCampaignController::class, 'send'])->name('send');
    Route::post('/email-campaigns/{campaign}/cancel', [AdminEmailCampaignController::class, 'cancel'])->name('cancel');
    Route::post('/email-campaigns/{campaign}/toggle', [AdminEmailCampaignController::class, 'toggle'])->name('toggle');
});
