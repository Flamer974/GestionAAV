<?php

/**
 * ============================================================
 * app/Http/Controllers/DashboardController.php
 * ============================================================
 */

namespace App\Http\Controllers;

use App\Models\Task;
use App\Models\Note;
use App\Models\Project;
use App\Models\ChatSession;
use App\Services\MistralService;
use Illuminate\Support\Facades\Auth;

class DashboardController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    /**
     * Tableau de bord principal — agrège toutes les données
     */
    public function index(): \Illuminate\View\View
    {
        $user = Auth::user();

        // Tâches urgentes / en retard
        $urgentTasks = Task::where('user_id', $user->id)
            ->where('is_completed', false)
            ->whereIn('priority', ['high', 'urgent'])
            ->orderBy('due_date')
            ->limit(5)
            ->get();

        // Tâches du jour
        $todayTasks = Task::where('user_id', $user->id)
            ->where('is_completed', false)
            ->whereDate('due_date', today())
            ->orderBy('priority')
            ->get();

        // Projets actifs
        $activeProjects = Project::where('user_id', $user->id)
            ->where('status', 'active')
            ->withCount(['tasks', 'tasks as completed_tasks_count' => fn($q) => $q->where('is_completed', true)])
            ->orderByDesc('updated_at')
            ->limit(6)
            ->get()
            ->each(function ($project) {
                // Calculer le pourcentage de progression
                $project->progress = $project->tasks_count > 0
                    ? round(($project->completed_tasks_count / $project->tasks_count) * 100)
                    : 0;
            });

        // Notes récentes (épinglées en premier)
        $recentNotes = Note::where('user_id', $user->id)
            ->where('is_archived', false)
            ->orderByDesc('is_pinned')
            ->orderByDesc('updated_at')
            ->limit(6)
            ->get();

        // Statistiques globales
        $stats = [
            'total_tasks'       => Task::where('user_id', $user->id)->count(),
            'completed_today'   => Task::where('user_id', $user->id)
                                    ->where('is_completed', true)
                                    ->whereDate('completed_at', today())
                                    ->count(),
            'active_projects'   => Project::where('user_id', $user->id)
                                    ->where('status', 'active')->count(),
            'total_notes'       => Note::where('user_id', $user->id)->count(),
            'chat_sessions'     => ChatSession::where('user_id', $user->id)->count(),
            'overdue_tasks'     => Task::where('user_id', $user->id)
                                    ->where('is_completed', false)
                                    ->where('due_date', '<', today())
                                    ->count(),
        ];

        return view('dashboard.index', compact(
            'urgentTasks', 'todayTasks', 'activeProjects', 'recentNotes', 'stats'
        ));
    }
}


/**
 * ============================================================
 * app/Http/Controllers/TaskController.php
 * ============================================================
 */

namespace App\Http\Controllers;

use App\Models\Task;
use App\Models\Project;
use App\Services\MistralService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class TaskController extends Controller
{
    public function __construct(
        private readonly MistralService $mistralService
    ) {
        $this->middleware('auth');
    }

    /** Liste toutes les tâches */
    public function index(Request $request): \Illuminate\View\View
    {
        $query = Task::where('user_id', Auth::id())
            ->with('project')
            ->orderBy('sort_order');

        // Filtres
        if ($request->filled('status'))   $query->where('status', $request->status);
        if ($request->filled('priority')) $query->where('priority', $request->priority);
        if ($request->filled('project'))  $query->where('project_id', $request->project);
        if ($request->filled('search'))   $query->where('title', 'like', "%{$request->search}%");

        $tasks    = $query->paginate(20);
        $projects = Project::where('user_id', Auth::id())->get(['id', 'name', 'color']);

        return view('tasks.index', compact('tasks', 'projects'));
    }

    /** Créer une nouvelle tâche */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title'      => 'required|string|max:255',
            'description'=> 'nullable|string|max:2000',
            'priority'   => 'in:low,medium,high,urgent',
            'status'     => 'in:todo,in_progress,review,done',
            'project_id' => 'nullable|integer|exists:projects,id',
            'due_date'   => 'nullable|date|after_or_equal:today',
            'tags'       => 'nullable|array',
        ]);

        $task = Task::create(array_merge($validated, [
            'user_id'    => Auth::id(),
            'sort_order' => Task::where('user_id', Auth::id())->max('sort_order') + 1,
        ]));

        return response()->json(['success' => true, 'task' => $task->load('project')]);
    }

    /** Mettre à jour une tâche */
    public function update(Request $request, Task $task): JsonResponse
    {
        $this->authorize('update', $task);

        $validated = $request->validate([
            'title'       => 'sometimes|string|max:255',
            'description' => 'nullable|string|max:2000',
            'priority'    => 'sometimes|in:low,medium,high,urgent',
            'status'      => 'sometimes|in:todo,in_progress,review,done',
            'is_completed'=> 'sometimes|boolean',
            'due_date'    => 'nullable|date',
        ]);

        // Si on marque comme complète, enregistrer la date
        if (isset($validated['is_completed']) && $validated['is_completed']) {
            $validated['completed_at'] = now();
            $validated['status']       = 'done';
        }

        $task->update($validated);

        return response()->json(['success' => true, 'task' => $task->fresh()->load('project')]);
    }

    /** Supprimer une tâche */
    public function destroy(Task $task): JsonResponse
    {
        $this->authorize('delete', $task);
        $task->delete();
        return response()->json(['success' => true]);
    }

    /**
     * Demander des suggestions IA pour les tâches actuelles
     * POST /tasks/ai-suggest
     */
    public function aiSuggest(): JsonResponse
    {
        $tasks = Task::where('user_id', Auth::id())
            ->where('is_completed', false)
            ->orderBy('priority', 'desc')
            ->limit(15)
            ->get(['title', 'priority', 'due_date', 'status'])
            ->toArray();

        if (empty($tasks)) {
            return response()->json([
                'success'    => true,
                'suggestion' => 'Aucune tâche en cours. C\'est le moment d\'en créer !',
            ]);
        }

        $suggestion = $this->mistralService->suggestTaskImprovements($tasks);

        return response()->json(['success' => true, 'suggestion' => $suggestion]);
    }

    /**
     * Décomposer une tâche complexe en sous-tâches
     * POST /tasks/{task}/breakdown
     */
    public function aiBreakdown(Task $task): JsonResponse
    {
        $this->authorize('update', $task);

        $breakdown = $this->mistralService->breakdownTask(
            $task->title,
            $task->description ?? ''
        );

        // Optionnel : sauvegarder en base
        $task->update(['ai_suggestion' => $breakdown]);

        return response()->json(['success' => true, 'breakdown' => $breakdown]);
    }
}


/**
 * ============================================================
 * app/Http/Controllers/NoteController.php
 * ============================================================
 */

namespace App\Http\Controllers;

use App\Models\Note;
use App\Services\MistralService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class NoteController extends Controller
{
    public function __construct(
        private readonly MistralService $mistralService
    ) {
        $this->middleware('auth');
    }

    /** Liste les notes */
    public function index(Request $request): \Illuminate\View\View
    {
        $query = Note::where('user_id', Auth::id())
            ->where('is_archived', false)
            ->orderByDesc('is_pinned')
            ->orderByDesc('updated_at');

        if ($request->filled('search')) {
            $query->whereFullText(['title', 'content'], $request->search);
        }

        if ($request->filled('category')) {
            $query->where('category', $request->category);
        }

        $notes      = $query->paginate(12);
        $categories = Note::where('user_id', Auth::id())->distinct()->pluck('category')->filter();

        return view('notes.index', compact('notes', 'categories'));
    }

    /** Créer une note */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title'    => 'required|string|max:255',
            'content'  => 'nullable|string',
            'category' => 'nullable|string|max:50',
            'tags'     => 'nullable|array',
            'color'    => 'nullable|string|max:7',
        ]);

        $note = Note::create(array_merge($validated, ['user_id' => Auth::id()]));

        return response()->json(['success' => true, 'note' => $note]);
    }

    /** Mettre à jour une note */
    public function update(Request $request, Note $note): JsonResponse
    {
        $this->authorize('update', $note);

        $note->update($request->validate([
            'title'      => 'sometimes|string|max:255',
            'content'    => 'nullable|string',
            'is_pinned'  => 'sometimes|boolean',
            'is_archived'=> 'sometimes|boolean',
            'category'   => 'nullable|string|max:50',
            'color'      => 'nullable|string|max:7',
        ]));

        return response()->json(['success' => true, 'note' => $note->fresh()]);
    }

    /** Supprimer une note */
    public function destroy(Note $note): JsonResponse
    {
        $this->authorize('delete', $note);
        $note->delete();
        return response()->json(['success' => true]);
    }

    /**
     * Résumer une note avec l'IA
     * POST /notes/{note}/summarize
     */
    public function aiSummarize(Note $note): JsonResponse
    {
        $this->authorize('update', $note);

        if (empty(trim($note->content ?? ''))) {
            return response()->json([
                'success' => false,
                'message' => 'La note est vide, impossible de la résumer.',
            ], 422);
        }

        $summary = $this->mistralService->summarize($note->content);

        $note->update([
            'ai_summary'   => $summary,
            'ai_processed' => true,
        ]);

        return response()->json(['success' => true, 'summary' => $summary]);
    }

    /**
     * Améliorer une note avec l'IA
     * POST /notes/{note}/enhance
     */
    public function aiEnhance(Note $note): JsonResponse
    {
        $this->authorize('update', $note);

        if (empty(trim($note->content ?? ''))) {
            return response()->json([
                'success' => false,
                'message' => 'La note est vide, impossible de l\'améliorer.',
            ], 422);
        }

        $enhanced = $this->mistralService->enhanceText($note->content);

        $note->update([
            'ai_enhanced'  => $enhanced,
            'ai_processed' => true,
        ]);

        return response()->json(['success' => true, 'enhanced' => $enhanced]);
    }
}
