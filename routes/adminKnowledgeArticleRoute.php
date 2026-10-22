<?php

use App\Http\Controllers\Admin\AdminKnowledgeArticleController;
use Illuminate\Support\Facades\Route;

Route::prefix('admin')->group(function () {
    Route::middleware(['auth:admin', 'no.cache', 'throttle:admin'])->group(function () {
        Route::get('/knowledge', [AdminKnowledgeArticleController::class, 'index'])->name('admin.knowledge.index');
        Route::get('/knowledge/create', [AdminKnowledgeArticleController::class, 'create'])->name('admin.knowledge.create');
        Route::post('/knowledge', [AdminKnowledgeArticleController::class, 'store'])->name('admin.knowledge.store');
        Route::get('/knowledge/{knowledgeArticle}/edit', [AdminKnowledgeArticleController::class, 'edit'])->name('admin.knowledge.edit');
        Route::put('/knowledge/{knowledgeArticle}', [AdminKnowledgeArticleController::class, 'update'])->name('admin.knowledge.update');
        Route::delete('/knowledge/{knowledgeArticle}', [AdminKnowledgeArticleController::class, 'destroy'])->name('admin.knowledge.destroy');
    });
});
