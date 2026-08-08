<?php

use App\Http\Controllers\ChatbotController;
use App\Http\Middleware\EnforceGuestChatLimits;
use Illuminate\Support\Facades\Route;

Route::post('/chat', [ChatbotController::class, 'chat'])
    ->name('chat.send')
    ->middleware(['throttle:ai', EnforceGuestChatLimits::class]);

Route::get('/chat/history', [ChatbotController::class, 'history'])
    ->name('chat.history')
    ->middleware(['throttle:chat-poll']);

Route::post('/chat/handoff', [ChatbotController::class, 'handoff'])
    ->name('chat.handoff')
    ->middleware(['throttle:ai']);

Route::post('/chat/handoff/cancel', [ChatbotController::class, 'cancelHandoff'])
    ->name('chat.handoff.cancel')
    ->middleware(['throttle:ai']);

Route::post('/chat/handoff/return', [ChatbotController::class, 'returnToBot'])
    ->name('chat.handoff.return')
    ->middleware(['throttle:ai']);

Route::get('/chat/poll', [ChatbotController::class, 'poll'])
    ->name('chat.poll')
    ->middleware(['throttle:chat-poll']);
