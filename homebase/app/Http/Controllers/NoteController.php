<?php

namespace App\Http\Controllers;

use App\Models\Note;
use App\Services\MistralService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\View\View;

class NoteController extends Controller
{
    public function __construct(
        private readonly MistralService $mistralService
    ) {
        $this->middleware('auth');
    }

    /**
     * Affiche la liste des notes avec filtres
     */
    public function index(Request $request): View
    {
        $user = Auth::user();
        $query = Note::where('user_id', $user->id);

        // Filtres
        if ($request->filled('category')) {
            $query->where('category', $request->category);
        }
        if ($request->boolean('archived')) {
            $query->where('is_archived', true);
        } else {
            $query->where('is_archived', false);
        }
        if ($request->boolean('pinned')) {
            $query->where('is_pinned', true);
        }
        if ($request->filled('search')) {
            $query->where(function ($q) use ($request) {
                $q->where('title', 'like', "%{$request->search}%")
                  ->orWhere('content', 'like', "%{$request->search}%");
            });
        }

        $notes = $query->orderByDesc('is_pinned')
                       ->orderByDesc('updated_at')
                       ->paginate(20)
                       ->withQueryString();

        $categories = Note::where('user_id', $user->id)
                          ->whereNotNull('category')
                          ->where('is_archived', false)
                          ->distinct()
                          ->pluck('category')
                          ->sort()
                          ->values();

        $stats = [
            'total' => Note::where('user_id', $user->id)->where('is_archived', false)->count(),
            'pinned' => Note::where('user_id', $user->id)->where('is_pinned', true)->where('is_archived', false)->count(),
            'archived' => Note::where('user_id', $user->id)->where('is_archived', true)->count(),
            'ai_processed' => Note::where('user_id', $user->id)->where('ai_processed', true)->count(),
        ];

        return view('notes.index', compact('notes', 'categories', 'stats'));
    }

    /**
     * Stocke une nouvelle note
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'content' => 'nullable|string|max:65000',
            'category' => 'nullable|string|max:50',
            'tags' => 'nullable|array',
            'tags.*' => 'string|max:50',
            'color' => 'nullable|regex:/^#[0-9A-F]{6}$/i',
            'is_pinned' => 'sometimes|boolean',
        ]);

        $note = Note::create([
            ...$validated,
            'user_id' => Auth::id(),
            'ai_processed' => false,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Note créée avec succès',
            'note' => $note,
        ], 201);
    }

    /**
     * Affiche une note spécifique
     */
    public function show(Note $note): JsonResponse
    {
        abort_if($note->user_id !== Auth::id(), 403);
        
        return response()->json([
            'success' => true,
            'note' => $note,
        ]);
    }

    /**
     * Met à jour une note existante
     */
    public function update(Request $request, Note $note): JsonResponse
    {
        abort_if($note->user_id !== Auth::id(), 403);

        $validated = $request->validate([
            'title' => 'sometimes|required|string|max:255',
            'content' => 'nullable|string|max:65000',
            'category' => 'nullable|string|max:50',
            'tags' => 'nullable|array',
            'tags.*' => 'string|max:50',
            'color' => 'nullable|regex:/^#[0-9A-F]{6}$/i',
            'is_pinned' => 'sometimes|boolean',
            'is_archived' => 'sometimes|boolean',
        ]);

        $note->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Note mise à jour',
            'note' => $note->fresh(),
        ]);
    }

    /**
     * Supprime (soft delete) une note
     */
    public function destroy(Note $note): JsonResponse
    {
        abort_if($note->user_id !== Auth::id(), 403);
        
        $note->delete();

        return response()->json([
            'success' => true,
            'message' => 'Note supprimée',
        ]);
    }

    /**
     * Toggle le statut épinglé
     */
    public function togglePin(Note $note): JsonResponse
    {
        abort_if($note->user_id !== Auth::id(), 403);

        $note->update(['is_pinned' => !$note->is_pinned]);

        return response()->json([
            'success' => true,
            'is_pinned' => $note->is_pinned,
            'note' => $note,
        ]);
    }

    /**
     * Toggle le statut archivé
     */
    public function toggleArchive(Note $note): JsonResponse
    {
        abort_if($note->user_id !== Auth::id(), 403);

        $note->update(['is_archived' => !$note->is_archived]);

        return response()->json([
            'success' => true,
            'is_archived' => $note->is_archived,
            'note' => $note,
        ]);
    }

    /**
     * Génère un résumé IA de la note
     */
    public function aiSummarize(Note $note): JsonResponse
    {
        abort_if($note->user_id !== Auth::id(), 403);

        if (empty($note->content)) {
            return response()->json([
                'success' => false,
                'message' => 'La note est vide, impossible de résumer.',
            ], 400);
        }

        try {
            $summary = $this->mistralService->summarize($note->content);
            
            $note->update([
                'ai_summary' => $summary,
                'ai_processed' => true,
            ]);

            return response()->json([
                'success' => true,
                'summary' => $summary,
                'note' => $note,
            ]);

        } catch (\App\Exceptions\MistralRateLimitException $e) {
            return response()->json([
                'success' => false,
                'error' => 'rate_limit',
                'message' => $e->getMessage(),
            ], 429);

        } catch (\Exception $e) {
            Log::error('Note AI summarize failed', ['note_id' => $note->id, 'error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'error' => 'ai_error',
                'message' => 'L\'IA n\'a pas pu générer de résumé pour le moment.',
            ], 503);
        }
    }

    /**
     * Améliore/rédige la note avec l'IA
     */
    public function aiEnhance(Note $note): JsonResponse
    {
        abort_if($note->user_id !== Auth::id(), 403);

        if (empty($note->content)) {
            return response()->json([
                'success' => false,
                'message' => 'La note est vide, impossible d\'améliorer.',
            ], 400);
        }

        try {
            $enhanced = $this->mistralService->enhanceText($note->content);
            
            $note->update([
                'ai_enhanced' => $enhanced,
                'ai_processed' => true,
            ]);

            return response()->json([
                'success' => true,
                'enhanced' => $enhanced,
                'note' => $note,
            ]);

        } catch (\App\Exceptions\MistralRateLimitException $e) {
            return response()->json([
                'success' => false,
                'error' => 'rate_limit',
                'message' => $e->getMessage(),
            ], 429);

        } catch (\Exception $e) {
            Log::error('Note AI enhance failed', ['note_id' => $note->id, 'error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'error' => 'ai_error',
                'message' => 'L\'IA n\'a pas pu améliorer la note pour le moment.',
            ], 503);
        }
    }

    /**
     * Recherche full-text dans les notes
     */
    public function search(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'query' => 'required|string|min:2|max:100',
        ]);

        $results = Note::where('user_id', Auth::id())
                       ->where('is_archived', false)
                       ->where(function ($q) use ($validated) {
                           $q->where('title', 'like', "%{$validated['query']}%")
                             ->orWhere('content', 'like', "%{$validated['query']}%");
                       })
                       ->orderByDesc('is_pinned')
                       ->orderByDesc('updated_at')
                       ->limit(10)
                       ->get(['id', 'title', 'content', 'category', 'updated_at']);

        // Highlight simple des résultats
        $highlighted = $results->map(function ($note) use ($validated) {
            $query = preg_quote($validated['query'], '/');
            $note->title_highlighted = preg_replace(
                "/($query)/i", 
                '<mark class="bg-yellow-200">$1</mark>', 
                $note->title
            );
            $note->content_preview = Str::limit(strip_tags($note->content), 150);
            return $note;
        });

        return response()->json([
            'success' => true,
            'query' => $validated['query'],
            'results' => $highlighted,
            'count' => $highlighted->count(),
        ]);
    }

    /**
     * Exporte les notes en format texte/JSON
     */
    public function export(Request $request): JsonResponse
    {
        $format = $request->get('format', 'json');
        $archived = $request->boolean('include_archived');

        $query = Note::where('user_id', Auth::id());
        if (!$archived) {
            $query->where('is_archived', false);
        }

        $notes = $query->orderBy('category')->orderBy('title')->get();

        if ($format === 'text') {
            $content = $notes->map(function ($note) {
                $pin = $note->is_pinned ? '📌 ' : '';
                $archived = $note->is_archived ? '[ARCHIVÉE] ' : '';
                return "{$pin}{$archived}{$note->title}\n" .
                       "Catégorie: {$note->category}\n" .
                       "Créée le: {$note->created_at->format('d/m/Y H:i')}\n" .
                       str_repeat('-', 40) . "\n" .
                       "{$note->content}\n\n";
            })->join("\n");

            return response()->streamDownload(function () use ($content) {
                echo $content;
            }, 'notes-export-' . now()->format('Y-m-d') . '.txt', [
                'Content-Type' => 'text/plain; charset=utf-8',
            ]);
        }

        // Format JSON par défaut
        return response()->json([
            'success' => true,
            'exported_at' => now()->toISOString(),
            'count' => $notes->count(),
            'notes' => $notes,
        ]);
    }
}