<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ChatbotController;
use App\Http\Controllers\TaskController;
use App\Http\Controllers\NoteController;

// ... routes existantes ...

Route::middleware(['auth', 'verified'])->group(function () {
    
    // ... dashboard, chatbot, profile ...

    // Tasks - avec routes additionnelles
    Route::prefix('tasks')->name('tasks.')->group(function () {
        Route::get('/', [TaskController::class, 'index'])->name('index');
        Route::post('/', [TaskController::class, 'store'])->name('store');
        Route::get('/{task}', [TaskController::class, 'show'])->name('show');
        Route::put('/{task}', [TaskController::class, 'update'])->name('update');
        Route::delete('/{task}', [TaskController::class, 'destroy'])->name('destroy');
        
        // Routes personnalisées
        Route::patch('/{task}/toggle', [TaskController::class, 'toggleComplete'])->name('toggle');
        Route::post('/ai-suggest', [TaskController::class, 'aiSuggest'])->name('ai.suggest');
        Route::post('/reorder', [TaskController::class, 'reorder'])->name('reorder');
    });

    // Notes - avec routes additionnelles
    Route::prefix('notes')->name('notes.')->group(function () {
        Route::get('/', [NoteController::class, 'index'])->name('index');
        Route::post('/', [NoteController::class, 'store'])->name('store');
        Route::get('/{note}', [NoteController::class, 'show'])->name('show');
        Route::put('/{note}', [NoteController::class, 'update'])->name('update');
        Route::delete('/{note}', [NoteController::class, 'destroy'])->name('destroy');
        
        // Routes personnalisées
        Route::patch('/{note}/toggle-pin', [NoteController::class, 'togglePin'])->name('toggle-pin');
        Route::patch('/{note}/toggle-archive', [NoteController::class, 'toggleArchive'])->name('toggle-archive');
        Route::post('/{note}/ai-summarize', [NoteController::class, 'aiSummarize'])->name('ai.summarize');
        Route::post('/{note}/ai-enhance', [NoteController::class, 'aiEnhance'])->name('ai.enhance');
        Route::get('/search', [NoteController::class, 'search'])->name('search');
        Route::get('/export', [NoteController::class, 'export'])->name('export');
    });
});