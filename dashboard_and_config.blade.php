{{-- ============================================================ --}}
{{-- resources/views/dashboard/index.blade.php                   --}}
{{-- Tableau de bord principal HomeBase                          --}}
{{-- ============================================================ --}}

@extends('layouts.app')
@section('title', 'Tableau de bord')
@section('page-title', 'Tableau de bord')

@section('content')
<div class="space-y-6" x-data="dashboard()">

    {{-- ── Statistiques globales ───────────────────────────────── --}}
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
        @php
            $statsCards = [
                ['label' => 'Tâches totales',    'value' => $stats['total_tasks'],     'icon' => 'check-square', 'color' => 'indigo'],
                ['label' => 'Terminées aujourd\'hui', 'value' => $stats['completed_today'], 'icon' => 'zap',      'color' => 'green'],
                ['label' => 'Projets actifs',     'value' => $stats['active_projects'], 'icon' => 'folder',       'color' => 'blue'],
                ['label' => 'En retard',           'value' => $stats['overdue_tasks'],   'icon' => 'alert-circle', 'color' => 'red'],
            ];
        @endphp

        @foreach($statsCards as $card)
            <div class="bg-white dark:bg-gray-900 rounded-2xl border border-gray-200 dark:border-gray-800 p-5">
                <div class="flex items-start justify-between">
                    <div>
                        <p class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide">
                            {{ $card['label'] }}
                        </p>
                        <p class="mt-2 text-3xl font-bold text-gray-900 dark:text-white font-geist">
                            {{ $card['value'] }}
                        </p>
                    </div>
                    <div class="p-2 bg-{{ $card['color'] }}-50 dark:bg-{{ $card['color'] }}-900/20 rounded-xl">
                        @include('components.icon', ['name' => $card['icon'], 'class' => "w-5 h-5 text-{$card['color']}-600 dark:text-{$card['color']}-400"])
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

        {{-- ── Tâches du jour ──────────────────────────────────── --}}
        <div class="lg:col-span-2 bg-white dark:bg-gray-900 rounded-2xl border border-gray-200 dark:border-gray-800 overflow-hidden">
            <div class="flex items-center justify-between px-6 py-4 border-b border-gray-200 dark:border-gray-800">
                <h2 class="font-semibold text-gray-900 dark:text-white">Tâches du jour</h2>
                <a href="{{ route('tasks.index') }}" class="text-sm text-indigo-600 dark:text-indigo-400 hover:underline">Voir tout →</a>
            </div>
            <div class="divide-y divide-gray-100 dark:divide-gray-800">
                @forelse($todayTasks as $task)
                    <div class="flex items-center gap-4 px-6 py-3 hover:bg-gray-50 dark:hover:bg-gray-800/50 transition-colors group"
                         x-data="{ completed: {{ $task->is_completed ? 'true' : 'false' }} }">

                        {{-- Checkbox --}}
                        <button @click="toggleTask({{ $task->id }}, !completed); completed = !completed"
                                class="flex-shrink-0 w-5 h-5 rounded-full border-2 flex items-center justify-center transition-all"
                                :class="completed
                                    ? 'bg-green-500 border-green-500'
                                    : 'border-gray-300 dark:border-gray-600 hover:border-indigo-500'">
                            <svg x-show="completed" class="w-3 h-3 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>
                            </svg>
                        </button>

                        {{-- Contenu --}}
                        <div class="flex-1 min-w-0">
                            <p class="text-sm font-medium text-gray-900 dark:text-white truncate transition-all"
                               :class="completed ? 'line-through text-gray-400' : ''">
                                {{ $task->title }}
                            </p>
                            @if($task->project)
                                <p class="text-xs text-gray-500 dark:text-gray-400">{{ $task->project->name }}</p>
                            @endif
                        </div>

                        {{-- Badge priorité --}}
                        <span class="flex-shrink-0 px-2 py-0.5 rounded-full text-xs font-medium
                            @if($task->priority === 'urgent') bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400
                            @elseif($task->priority === 'high') bg-orange-100 text-orange-700 dark:bg-orange-900/30 dark:text-orange-400
                            @elseif($task->priority === 'medium') bg-yellow-100 text-yellow-700 dark:bg-yellow-900/30 dark:text-yellow-400
                            @else bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400
                            @endif">
                            {{ ucfirst($task->priority) }}
                        </span>
                    </div>
                @empty
                    <div class="px-6 py-10 text-center text-gray-400 dark:text-gray-600">
                        <p class="text-2xl mb-2">🎉</p>
                        <p class="text-sm">Aucune tâche pour aujourd'hui !</p>
                    </div>
                @endforelse
            </div>
        </div>

        {{-- ── Notes récentes ──────────────────────────────────── --}}
        <div class="bg-white dark:bg-gray-900 rounded-2xl border border-gray-200 dark:border-gray-800 overflow-hidden">
            <div class="flex items-center justify-between px-6 py-4 border-b border-gray-200 dark:border-gray-800">
                <h2 class="font-semibold text-gray-900 dark:text-white">Notes récentes</h2>
                <a href="{{ route('notes.index') }}" class="text-sm text-indigo-600 dark:text-indigo-400 hover:underline">Voir tout →</a>
            </div>
            <div class="p-4 space-y-3">
                @forelse($recentNotes->take(4) as $note)
                    <a href="{{ route('notes.show', $note) }}"
                       class="block p-3 rounded-xl hover:bg-gray-50 dark:hover:bg-gray-800 transition-colors"
                       style="border-left: 3px solid {{ $note->color }}">
                        <div class="flex items-center gap-2 mb-1">
                            @if($note->is_pinned)
                                <span class="text-xs">📌</span>
                            @endif
                            <p class="text-sm font-medium text-gray-900 dark:text-white truncate">{{ $note->title }}</p>
                        </div>
                        @if($note->content)
                            <p class="text-xs text-gray-500 dark:text-gray-400 line-clamp-2">{{ Str::limit(strip_tags($note->content), 80) }}</p>
                        @endif
                        @if($note->ai_summary)
                            <p class="text-xs text-indigo-600 dark:text-indigo-400 mt-1">✨ Résumé IA disponible</p>
                        @endif
                    </a>
                @empty
                    <div class="text-center py-6 text-gray-400 dark:text-gray-600">
                        <p class="text-sm">Aucune note créée</p>
                    </div>
                @endforelse
            </div>
        </div>
    </div>

    {{-- ── Projets actifs ───────────────────────────────────────── --}}
    @if($activeProjects->count() > 0)
    <div class="bg-white dark:bg-gray-900 rounded-2xl border border-gray-200 dark:border-gray-800 overflow-hidden">
        <div class="flex items-center justify-between px-6 py-4 border-b border-gray-200 dark:border-gray-800">
            <h2 class="font-semibold text-gray-900 dark:text-white">Projets actifs</h2>
            <a href="{{ route('projects.index') }}" class="text-sm text-indigo-600 dark:text-indigo-400 hover:underline">Voir tout →</a>
        </div>
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4 p-4">
            @foreach($activeProjects as $project)
                <a href="{{ route('projects.show', $project) }}"
                   class="block p-4 rounded-xl border border-gray-100 dark:border-gray-800 hover:border-indigo-200 dark:hover:border-indigo-700 hover:shadow-sm transition-all">
                    <div class="flex items-center gap-3 mb-3">
                        <div class="w-8 h-8 rounded-lg flex items-center justify-center text-sm" style="background-color: {{ $project->color }}20; color: {{ $project->color }}">
                            📁
                        </div>
                        <div class="flex-1 min-w-0">
                            <p class="font-medium text-gray-900 dark:text-white truncate text-sm">{{ $project->name }}</p>
                            <p class="text-xs text-gray-500 dark:text-gray-400">{{ $project->tasks_count }} tâches</p>
                        </div>
                    </div>
                    {{-- Barre de progression --}}
                    <div class="flex items-center gap-2">
                        <div class="flex-1 bg-gray-100 dark:bg-gray-800 rounded-full h-1.5">
                            <div class="h-1.5 rounded-full transition-all" style="width: {{ $project->progress }}%; background-color: {{ $project->color }}"></div>
                        </div>
                        <span class="text-xs font-medium text-gray-500 dark:text-gray-400">{{ $project->progress }}%</span>
                    </div>
                </a>
            @endforeach
        </div>
    </div>
    @endif
</div>
@endsection

@push('scripts')
<script>
function dashboard() {
    return {
        async toggleTask(taskId, completed) {
            try {
                await axios.patch(`/tasks/${taskId}`, { is_completed: completed });
            } catch (err) {
                console.error('Erreur toggle tâche:', err);
            }
        },
    };
}
</script>
@endpush


{{-- ============================================================ --}}
{{-- tailwind.config.js (racine du projet)                        --}}
{{-- ============================================================ --}}

/*
/** @type {import('tailwindcss').Config} */
module.exports = {
    // Active le mode dark via la classe CSS (contrôlé par Alpine.js)
    darkMode: 'class',

    content: [
        './resources/**/*.blade.php',
        './resources/**/*.js',
        './resources/**/*.vue',
    ],

    theme: {
        extend: {
            fontFamily: {
                inter:  ['Inter', 'sans-serif'],
                geist:  ['Geist', 'sans-serif'],
                mono:   ['JetBrains Mono', 'monospace'],
            },
            animation: {
                'bounce-slow': 'bounce 1.5s infinite',
                'pulse-slow':  'pulse 3s infinite',
            },
            borderRadius: {
                '2xl': '1rem',
                '3xl': '1.5rem',
            },
        },
    },

    plugins: [
        require('@tailwindcss/forms'),
        require('@tailwindcss/typography'),
    ],

    // IMPORTANT : Sécuriser les classes Tailwind générées dynamiquement
    // (couleurs de priorité dans Controllers.php)
    safelist: [
        { pattern: /bg-(red|orange|yellow|green|blue|indigo|purple)-(50|100|600|900)/ },
        { pattern: /text-(red|orange|yellow|green|blue|indigo|purple)-(400|600|700)/ },
        { pattern: /border-(red|orange|yellow|green|blue|indigo|purple)-(200|700|800)/ },
        { pattern: /dark:bg-(red|orange|yellow|green|blue|indigo|purple)-(900)/ },
    ],
};
*/


{{-- ============================================================ --}}
{{-- database/seeders/DatabaseSeeder.php                          --}}
{{-- ============================================================ --}}

/*
<?php
namespace Database\Seeders;

use App\Models\User;
use App\Models\Project;
use App\Models\Task;
use App\Models\Note;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // ── Utilisateur Admin ─────────────────────────────────
        $admin = User::create([
            'name'     => 'Administrateur',
            'email'    => 'admin@homebase.local',
            'password' => Hash::make('password'),
            'is_admin' => true,
            'timezone' => 'Indian/Reunion',
        ]);

        // ── Utilisateur Standard ──────────────────────────────
        $user = User::create([
            'name'     => 'Utilisateur Demo',
            'email'    => 'user@homebase.local',
            'password' => Hash::make('password'),
            'is_admin' => false,
        ]);

        // ── Projets de démo ────────────────────────────────────
        $project1 = Project::create([
            'user_id'     => $user->id,
            'name'        => 'Site Web Personnel',
            'description' => 'Développement du portfolio en ligne',
            'color'       => '#6366f1',
            'status'      => 'active',
            'deadline'    => now()->addMonths(2),
        ]);

        $project2 = Project::create([
            'user_id'     => $user->id,
            'name'        => 'Apprentissage IA',
            'description' => 'Cours et projets autour de l\'intelligence artificielle',
            'color'       => '#8b5cf6',
            'status'      => 'active',
        ]);

        // ── Tâches de démo ─────────────────────────────────────
        $tasks = [
            ['title' => 'Configurer Laragon et Laravel',  'priority' => 'urgent', 'status' => 'done',        'is_completed' => true,  'project_id' => $project1->id],
            ['title' => 'Créer la base de données',       'priority' => 'high',   'status' => 'done',        'is_completed' => true,  'project_id' => $project1->id],
            ['title' => 'Intégrer API Mistral',           'priority' => 'high',   'status' => 'in_progress', 'is_completed' => false, 'project_id' => $project1->id],
            ['title' => 'Créer l\'interface dashboard',   'priority' => 'medium', 'status' => 'todo',        'is_completed' => false, 'project_id' => $project1->id, 'due_date' => today()],
            ['title' => 'Lire le cours sur les LLMs',     'priority' => 'low',    'status' => 'todo',        'is_completed' => false, 'project_id' => $project2->id, 'due_date' => today()],
            ['title' => 'Pratiquer le fine-tuning',       'priority' => 'medium', 'status' => 'todo',        'is_completed' => false, 'project_id' => $project2->id],
        ];

        foreach ($tasks as $task) {
            Task::create(array_merge($task, ['user_id' => $user->id]));
        }

        // ── Notes de démo ──────────────────────────────────────
        Note::create([
            'user_id'   => $user->id,
            'title'     => 'Idées pour HomeBase',
            'content'   => "- Ajouter un module de suivi des habitudes\n- Intégrer Google Calendar\n- Créer une PWA mobile\n- Ajouter des statistiques d'utilisation IA",
            'color'     => '#fbbf24',
            'is_pinned' => true,
            'category'  => 'Projets',
        ]);

        Note::create([
            'user_id'  => $user->id,
            'title'    => 'Commandes Laravel utiles',
            'content'  => "php artisan migrate:fresh --seed\nphp artisan queue:work\nphp artisan optimize:clear\nphp artisan route:list",
            'color'    => '#34d399',
            'category' => 'Développement',
        ]);

        $this->command->info('✅ Données de démo créées avec succès !');
        $this->command->info('👤 Admin : admin@homebase.local / password');
        $this->command->info('👤 User  : user@homebase.local / password');
    }
}
*/


{{-- ============================================================ --}}
{{-- app/Http/Middleware/AdminMiddleware.php                       --}}
{{-- ============================================================ --}}

/*
<?php
namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AdminMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        if (!auth()->check() || !auth()->user()->is_admin) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Accès refusé — Admin requis.'], 403);
            }
            abort(403, 'Accès refusé. Zone réservée aux administrateurs.');
        }

        return $next($request);
    }
}
*/

{{-- ============================================================ --}}
{{-- app/Exceptions/MistralApiException.php                       --}}
{{-- app/Exceptions/MistralRateLimitException.php                 --}}
{{-- ============================================================ --}}

/*
<?php
namespace App\Exceptions;

class MistralApiException extends \RuntimeException {}

class MistralRateLimitException extends MistralApiException {}
*/

{{-- ============================================================ --}}
{{-- resources/js/app.js — Point d'entrée JavaScript              --}}
{{-- ============================================================ --}}

/*
import './bootstrap'; // Axios + CSRF token

// Alpine.js pour la réactivité légère
import Alpine from 'alpinejs';
window.Alpine = Alpine;
Alpine.start();

// Configuration Axios globale
import axios from 'axios';
window.axios = axios;
axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';
axios.defaults.headers.common['X-CSRF-TOKEN'] = document.querySelector('meta[name="csrf-token"]')?.content;

// Intercepteur global — log des erreurs 429 / 500
axios.interceptors.response.use(
    response => response,
    error => {
        if (error.response?.status === 429) {
            console.warn('Rate limit atteint');
        }
        return Promise.reject(error);
    }
);
*/

{{-- ============================================================ --}}
{{-- resources/css/app.css — TailwindCSS + styles personnalisés   --}}
{{-- ============================================================ --}}

/*
@import 'tailwindcss/base';
@import 'tailwindcss/components';
@import 'tailwindcss/utilities';

@layer base {
    :root {
        --font-inter: 'Inter', sans-serif;
        --font-geist: 'Geist', sans-serif;
    }

    * {
        @apply border-border;
    }

    body {
        @apply font-inter;
    }

    ::selection {
        @apply bg-indigo-100 text-indigo-900 dark:bg-indigo-800 dark:text-indigo-100;
    }

    ::-webkit-scrollbar        { @apply w-1.5; }
    ::-webkit-scrollbar-track  { @apply bg-transparent; }
    ::-webkit-scrollbar-thumb  { @apply bg-gray-200 dark:bg-gray-700 rounded-full; }
}

@layer components {
    /* Bouton primaire réutilisable */
    .btn-primary {
        @apply px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded-xl font-medium text-sm transition-colors;
    }

    /* Bouton secondaire */
    .btn-secondary {
        @apply px-4 py-2 bg-gray-100 hover:bg-gray-200 dark:bg-gray-800 dark:hover:bg-gray-700 text-gray-700 dark:text-gray-300 rounded-xl font-medium text-sm transition-colors;
    }

    /* Carte standard */
    .card {
        @apply bg-white dark:bg-gray-900 rounded-2xl border border-gray-200 dark:border-gray-800;
    }

    /* Input standard */
    .input {
        @apply w-full px-3 py-2 bg-gray-50 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl text-sm text-gray-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-indigo-500 transition-colors;
    }
}
*/
