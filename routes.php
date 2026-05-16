<?php

/**
 * ============================================================
 * routes/web.php
 * ============================================================
 * Routes principales de l'application (session-based auth).
 * Toutes les routes protégées nécessitent :
 *   - Authentification (middleware 'auth')
 *   - Vérification email (middleware 'verified') — optionnel en local
 *
 * Convention de nommage :
 *   resource.action → ex: tasks.index, notes.store
 * ============================================================
 */

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\{
    DashboardController,
    TaskController,
    NoteController,
    ProjectController,
    ChatbotController,
    AdminController,
    ToolsController,
    ProfileController,
};

// ─────────────────────────────────────────────────────────────
// ROUTES PUBLIQUES
// ─────────────────────────────────────────────────────────────

// Redirection racine vers login ou dashboard
Route::get('/', function () {
    return auth()->check()
        ? redirect()->route('dashboard')
        : redirect()->route('login');
});

// ─────────────────────────────────────────────────────────────
// AUTHENTIFICATION (gérée par Laravel Breeze/Fortify)
// php artisan breeze:install blade
// ─────────────────────────────────────────────────────────────
require __DIR__.'/auth.php';  // Login, register, password reset, etc.

// ─────────────────────────────────────────────────────────────
// ROUTES PROTÉGÉES (utilisateurs authentifiés)
// ─────────────────────────────────────────────────────────────
Route::middleware(['auth', 'verified'])->group(function () {

    // ── Dashboard ─────────────────────────────────────────────
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    // ── Profil utilisateur ────────────────────────────────────
    Route::prefix('profile')->name('profile.')->group(function () {
        Route::get('/',       [ProfileController::class, 'index'])->name('index');
        Route::patch('/',     [ProfileController::class, 'update'])->name('update');
        Route::delete('/',    [ProfileController::class, 'destroy'])->name('destroy');
        Route::post('/theme', [ProfileController::class, 'updateTheme'])->name('theme');
    });

    // ── Projets ───────────────────────────────────────────────
    Route::prefix('projects')->name('projects.')->group(function () {
        Route::get('/',             [ProjectController::class, 'index'])->name('index');
        Route::post('/',            [ProjectController::class, 'store'])->name('store');
        Route::get('/{project}',    [ProjectController::class, 'show'])->name('show');
        Route::patch('/{project}',  [ProjectController::class, 'update'])->name('update');
        Route::delete('/{project}', [ProjectController::class, 'destroy'])->name('destroy');
    });

    // ── Tâches ────────────────────────────────────────────────
    Route::prefix('tasks')->name('tasks.')->group(function () {
        Route::get('/',                      [TaskController::class, 'index'])->name('index');
        Route::post('/',                     [TaskController::class, 'store'])->name('store');
        Route::patch('/{task}',              [TaskController::class, 'update'])->name('update');
        Route::delete('/{task}',             [TaskController::class, 'destroy'])->name('destroy');

        // Fonctionnalités IA (rate limited dans le contrôleur)
        Route::post('/ai-suggest',           [TaskController::class, 'aiSuggest'])->name('ai.suggest');
        Route::post('/{task}/ai-breakdown',  [TaskController::class, 'aiBreakdown'])->name('ai.breakdown');
    });

    // ── Notes ─────────────────────────────────────────────────
    Route::prefix('notes')->name('notes.')->group(function () {
        Route::get('/',                   [NoteController::class, 'index'])->name('index');
        Route::post('/',                  [NoteController::class, 'store'])->name('store');
        Route::get('/{note}',             [NoteController::class, 'show'])->name('show');
        Route::patch('/{note}',           [NoteController::class, 'update'])->name('update');
        Route::delete('/{note}',          [NoteController::class, 'destroy'])->name('destroy');

        // Fonctionnalités IA
        Route::post('/{note}/summarize',  [NoteController::class, 'aiSummarize'])->name('ai.summarize');
        Route::post('/{note}/enhance',    [NoteController::class, 'aiEnhance'])->name('ai.enhance');
    });

    // ── Chatbot ───────────────────────────────────────────────
    Route::prefix('chatbot')->name('chatbot.')->group(function () {
        Route::get('/',                    [ChatbotController::class, 'index'])->name('index');
        Route::post('/session',            [ChatbotController::class, 'createSession'])->name('session.create');
        Route::post('/message',            [ChatbotController::class, 'sendMessage'])->name('message.send');
        Route::get('/session/{id}',        [ChatbotController::class, 'loadSession'])->name('session.load');
        Route::delete('/session/{id}',     [ChatbotController::class, 'deleteSession'])->name('session.delete');
        Route::get('/usage',               [ChatbotController::class, 'getUsageStats'])->name('usage');
    });

    // ── Outils du quotidien ───────────────────────────────────
    Route::prefix('tools')->name('tools.')->group(function () {
        Route::get('/',              [ToolsController::class, 'index'])->name('index');
        Route::get('/converter',     [ToolsController::class, 'converter'])->name('converter');
        Route::get('/pomodoro',      [ToolsController::class, 'pomodoro'])->name('pomodoro');
        Route::get('/calculator',    [ToolsController::class, 'calculator'])->name('calculator');
        Route::get('/password',      [ToolsController::class, 'passwordGenerator'])->name('password');
        Route::get('/markdown',      [ToolsController::class, 'markdownEditor'])->name('markdown');
        Route::post('/ai-quick',     [ToolsController::class, 'aiQuickAssist'])->name('ai.quick');
    });

    // ── Administration (réservé aux admins) ──────────────────
    Route::prefix('admin')->name('admin.')->middleware('admin')->group(function () {
        Route::get('/',          [AdminController::class, 'index'])->name('index');
        Route::get('/users',     [AdminController::class, 'users'])->name('users');
        Route::get('/logs',      [AdminController::class, 'logs'])->name('logs');
        Route::get('/ai-stats',  [AdminController::class, 'aiStats'])->name('ai-stats');

        // Gestion des utilisateurs
        Route::patch('/users/{user}/toggle', [AdminController::class, 'toggleUser'])->name('users.toggle');
        Route::delete('/users/{user}',       [AdminController::class, 'destroyUser'])->name('users.destroy');
    });
});


// ============================================================
// routes/api.php
// ============================================================
// API JSON pour les appels AJAX (Axios depuis le frontend).
// Protection : Laravel Sanctum (tokens + cookie-based session).
// ============================================================

/*
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\{TaskController, NoteController, ChatbotController};

// API authentifiée via Sanctum (session cookies pour SPA)
Route::middleware('auth:sanctum')->group(function () {

    // ── Tâches (API JSON) ─────────────────────────────────────
    Route::apiResource('tasks', TaskController::class)->except(['index', 'show']);
    Route::post('tasks/ai-suggest',          [TaskController::class, 'aiSuggest']);
    Route::post('tasks/{task}/ai-breakdown', [TaskController::class, 'aiBreakdown']);

    // ── Notes (API JSON) ──────────────────────────────────────
    Route::apiResource('notes', NoteController::class)->except(['index', 'show']);
    Route::post('notes/{note}/summarize', [NoteController::class, 'aiSummarize']);
    Route::post('notes/{note}/enhance',   [NoteController::class, 'aiEnhance']);

    // ── Chatbot (API JSON) ────────────────────────────────────
    Route::post('chatbot/session',        [ChatbotController::class, 'createSession']);
    Route::post('chatbot/message',        [ChatbotController::class, 'sendMessage']);
    Route::get('chatbot/session/{id}',    [ChatbotController::class, 'loadSession']);
    Route::delete('chatbot/session/{id}', [ChatbotController::class, 'deleteSession']);
    Route::get('chatbot/usage',           [ChatbotController::class, 'getUsageStats']);
});
*/
