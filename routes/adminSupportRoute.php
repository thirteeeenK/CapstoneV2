<?php

use App\Http\Controllers\Admin\SupportQueueController;
use Illuminate\Support\Facades\Route;

Route::prefix('admin')->group(function () {
    Route::middleware(['auth:admin', 'no.cache', 'throttle:admin'])->group(function () {
        Route::get('/support', [SupportQueueController::class, 'index'])->name('admin.support.index');
        Route::get('/support/poll', [SupportQueueController::class, 'poll'])->name('admin.support.poll');
        Route::get('/support/users/search', [SupportQueueController::class, 'searchUsers'])->name('admin.support.users.search');
        Route::post('/support/initiate', [SupportQueueController::class, 'initiate'])->name('admin.support.initiate');
        Route::get('/support/inquiries/{id}/messages', [SupportQueueController::class, 'messages'])->name('admin.support.messages');
        Route::post('/support/inquiries/{id}/claim', [SupportQueueController::class, 'claim'])->name('admin.support.claim');
        Route::post('/support/inquiries/{id}/reply', [SupportQueueController::class, 'reply'])->name('admin.support.reply');
        Route::post('/support/inquiries/{id}/resume-ai', [SupportQueueController::class, 'resumeAi'])->name('admin.support.resume-ai');
        Route::post('/support/inquiries/{id}/resolve', [SupportQueueController::class, 'resolve'])->name('admin.support.resolve');
    });
});
