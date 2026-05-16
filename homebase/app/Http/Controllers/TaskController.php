<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\Task;
use App\Services\MistralService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

class TaskController extends Controller
{
    public function __construct(
        private readonly MistralService $mistralService
    ) {
        $this->middleware('auth');
    }

    /**
     * Affiche la liste des tâches avec filtres
     */
    public function index(Request $request): View
    {
        $user = Auth::user();
        $query = Task::where('user_id', $user->id)->with('project');

        // Filtres
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('priority')) {
            $query->where('priority', $request->priority);
        }
        if ($request->boolean('overdue')) {
            $query->where('is_completed', false)
                  ->where('due_date', '<', today());
        }
        if ($request->filled('project_id')) {
            $query->where('project_id', $request->project_id);
        }
        if ($request->filled('search')) {
            $query->where(function ($q) use ($request) {
                $q->where('title', 'like', "%{$request->search}%")
                  ->orWhere('description', 'like', "%{$request->search}%");
            });
        }

        $tasks = $query->orderBy('sort_order')
                       ->orderBy('due_date')
                       ->orderBy('priority')
                       ->paginate(20)
                       ->withQueryString();

        $projects = Project::where('user_id', $user->id)
                           ->where('status', 'active')
                           ->orderBy('name')
                           ->get(['id', 'name', 'color']);

        $stats = [
            'total' => Task::where('user_id', $user->id)->count(),
            'completed' => Task::where('user_id', $user->id)->where('is_completed', true)->count(),
            'pending' => Task::where('user_id', $user->id)->where('is_completed', false)->count(),
            'overdue' => Task::where('user_id', $user->id)
                             ->where('is_completed', false)
                             ->where('due_date', '<', today())
                             ->count(),
        ];

        return view('tasks.index', compact('tasks', 'projects', 'stats'));
    }

    /**
     * Stocke une nouvelle tâche
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string|max:2000',
            'project_id' => 'nullable|exists:projects,id',
            'priority' => 'sometimes|in:low,medium,high,urgent',
            'status' => 'sometimes|in:todo,in_progress,review,done',
            'due_date' => 'nullable|date|after_or_equal:today',
            'estimated_minutes' => 'nullable|integer|min:1|max:1440',
            'tags' => 'nullable|array',
            'tags.*' => 'string|max:50',
            'subtasks' => 'nullable|array',
        ]);

        $task = Task::create([
            ...$validated,
            'user_id' => Auth::id(),
            'sort_order' => Task::where('user_id', Auth::id())->max('sort_order') + 1,
        ]);

        // IA : suggestion d'amélioration si description fournie
        if ($validated['description'] ?? false) {
            try {
                $suggestion = $this->mistralService->breakdownTask(
                    $validated['title'],
                    $validated['description']
                );
                $task->update(['ai_suggestion' => $suggestion]);
            } catch (\Exception $e) {
                Log::warning('AI suggestion failed for task', ['task_id' => $task->id, 'error' => $e->getMessage()]);
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'Tâche créée avec succès',
            'task' => $task->load('project'),
        ], 201);
    }

    /**
     * Met à jour une tâche existante
     */
    public function update(Request $request, Task $task): JsonResponse
    {
        // Vérification d'appartenance
        abort_if($task->user_id !== Auth::id(), 403);

        $validated = $request->validate([
            'title' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string|max:2000',
            'project_id' => 'nullable|exists:projects,id',
            'priority' => 'sometimes|in:low,medium,high,urgent',
            'status' => 'sometimes|in:todo,in_progress,review,done',
            'due_date' => 'nullable|date',
            'is_completed' => 'sometimes|boolean',
            'estimated_minutes' => 'nullable|integer|min:1|max:1440',
            'actual_minutes' => 'nullable|integer|min:1',
            'sort_order' => 'nullable|integer',
            'tags' => 'nullable|array',
            'tags.*' => 'string|max:50',
            'subtasks' => 'nullable|array',
        ]);

        // Si la tâche vient d'être complétée
        if (isset($validated['is_completed']) && $validated['is_completed'] && !$task->is_completed) {
            $validated['completed_at'] = now();
        }

        $task->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Tâche mise à jour',
            'task' => $task->fresh()->load('project'),
        ]);
    }

    /**
     * Supprime (soft delete) une tâche
     */
    public function destroy(Task $task): JsonResponse
    {
        abort_if($task->user_id !== Auth::id(), 403);
        
        $task->delete();

        return response()->json([
            'success' => true,
            'message' => 'Tâche supprimée',
        ]);
    }

    /**
     * Toggle le statut complété/non-complété
     */
    public function toggleComplete(Task $task): JsonResponse
    {
        abort_if($task->user_id !== Auth::id(), 403);

        $task->update([
            'is_completed' => !$task->is_completed,
            'completed_at' => $task->is_completed ? null : now(),
        ]);

        return response()->json([
            'success' => true,
            'is_completed' => $task->is_completed,
            'task' => $task,
        ]);
    }

    /**
     * Demande une suggestion IA pour améliorer les tâches
     */
    public function aiSuggest(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'task_ids' => 'nullable|array',
            'task_ids.*' => 'integer|exists:tasks,id',
        ]);

        $user = Auth::user();
        
        // Récupérer les tâches concernées
        $tasksQuery = Task::where('user_id', $user->id)->where('is_completed', false);
        
        if (!empty($validated['task_ids'])) {
            $tasksQuery->whereIn('id', $validated['task_ids']);
        }
        
        $tasks = $tasksQuery->orderBy('priority')->get(['id', 'title', 'priority', 'due_date']);

        if ($tasks->isEmpty()) {
            return response()->json([
                'success' => true,
                'suggestion' => 'Aucune tâche en attente à analyser.',
            ]);
        }

        try {
            $suggestion = $this->mistralService->suggestTaskImprovements(
                $tasks->map(fn($t) => [
                    'title' => $t->title,
                    'priority' => $t->priority,
                    'due_date' => $t->due_date?->format('d/m/Y'),
                ])->toArray()
            );

            return response()->json([
                'success' => true,
                'suggestion' => $suggestion,
                'tasks_count' => $tasks->count(),
            ]);

        } catch (\App\Exceptions\MistralRateLimitException $e) {
            return response()->json([
                'success' => false,
                'error' => 'rate_limit',
                'message' => $e->getMessage(),
            ], 429);

        } catch (\Exception $e) {
            Log::error('Task AI suggestion failed', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'error' => 'ai_error',
                'message' => 'L\'IA n\'a pas pu générer de suggestions pour le moment.',
            ], 503);
        }
    }

    /**
     * Réordonne les tâches (drag & drop)
     */
    public function reorder(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'tasks' => 'required|array',
            'tasks.*.id' => 'required|integer|exists:tasks,id',
            'tasks.*.sort_order' => 'required|integer|min:0',
        ]);

        foreach ($validated['tasks'] as $taskData) {
            Task::where('id', $taskData['id'])
                ->where('user_id', Auth::id())
                ->update(['sort_order' => $taskData['sort_order']]);
        }

        return response()->json(['success' => true]);
    }
}