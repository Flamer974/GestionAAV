<?php

namespace App\Http\Controllers;

use App\Models\Note;
use App\Models\Project;
use App\Models\Task;
use App\Models\ChatSession;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    public function index(): View
    {
        $user = Auth::user();

        $urgentTasks = Task::where('user_id', $user->id)
            ->where('is_completed', false)
            ->whereIn('priority', ['high', 'urgent'])
            ->orderBy('due_date')
            ->limit(5)
            ->get();

        $todayTasks = Task::where('user_id', $user->id)
            ->where('is_completed', false)
            ->whereDate('due_date', today())
            ->orderBy('priority')
            ->get();

        $activeProjects = Project::where('user_id', $user->id)
            ->where('status', 'active')
            ->withCount(['tasks', 'tasks as completed_tasks_count' => fn($q) => $q->where('is_completed', true)])
            ->orderByDesc('updated_at')
            ->limit(6)
            ->get()
            ->each(function ($project) {
                $project->progress = $project->tasks_count > 0
                    ? round(($project->completed_tasks_count / $project->tasks_count) * 100)
                    : 0;
            });

        $recentNotes = Note::where('user_id', $user->id)
            ->where('is_archived', false)
            ->orderByDesc('is_pinned')
            ->orderByDesc('updated_at')
            ->limit(6)
            ->get();

        $stats = [
            'total_tasks' => Task::where('user_id', $user->id)->count(),
            'completed_today' => Task::where('user_id', $user->id)
                ->where('is_completed', true)
                ->whereDate('completed_at', today())
                ->count(),
            'active_projects' => Project::where('user_id', $user->id)
                ->where('status', 'active')->count(),
            'total_notes' => Note::where('user_id', $user->id)->count(),
            'chat_sessions' => ChatSession::where('user_id', $user->id)->count(),
            'overdue_tasks' => Task::where('user_id', $user->id)
                ->where('is_completed', false)
                ->where('due_date', '<', today())
                ->count(),
        ];

        return view('dashboard.index', compact(
            'urgentTasks', 'todayTasks', 'activeProjects', 'recentNotes', 'stats'
        ));
    }
}