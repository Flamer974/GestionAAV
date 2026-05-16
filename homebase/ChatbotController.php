<?php

namespace App\Http\Controllers;

/**
 * ============================================================
 * app/Http/Controllers/ChatbotController.php
 * ============================================================
 * Gère le chatbot IA : sessions, messages, historique.
 *
 * Routes associées :
 *   GET  /chatbot              → index (interface)
 *   POST /chatbot/session      → Créer une session
 *   POST /chatbot/message      → Envoyer un message
 *   GET  /chatbot/session/{id} → Charger une session
 *   DELETE /chatbot/session/{id} → Supprimer une session
 *   GET  /api/chatbot/usage    → Stats d'utilisation
 * ============================================================
 */

use App\Models\ChatSession;
use App\Models\ChatMessage;
use App\Services\MistralService;
use App\Exceptions\MistralRateLimitException;
use App\Exceptions\MistralApiException;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class ChatbotController extends Controller
{
    public function __construct(
        private readonly MistralService $mistralService
    ) {
        // Toutes les routes nécessitent une authentification
        $this->middleware('auth');
        // Rate limiting Laravel natif (en plus du rate limit interne du service)
        $this->middleware('throttle:30,1')->only(['sendMessage']);
    }

    /**
     * ──────────────────────────────────────────────────────────
     * Afficher la page chatbot
     * ──────────────────────────────────────────────────────────
     */
    public function index(): \Illuminate\View\View
    {
        // Récupérer les sessions récentes de l'utilisateur
        $sessions = ChatSession::where('user_id', Auth::id())
            ->orderByDesc('created_at')
            ->limit(20)
            ->get(['id', 'title', 'total_tokens', 'created_at']);

        // Stats d'utilisation de l'API
        $usageStats = $this->mistralService->getUsageStats();

        return view('chatbot.index', compact('sessions', 'usageStats'));
    }

    /**
     * ──────────────────────────────────────────────────────────
     * Créer une nouvelle session de chat
     * ──────────────────────────────────────────────────────────
     */
    public function createSession(Request $request): JsonResponse
    {
        $session = ChatSession::create([
            'user_id' => Auth::id(),
            'title'   => $request->input('title', 'Nouvelle conversation'),
            'model'   => config('mistral.models.default'),
        ]);

        return response()->json([
            'success'    => true,
            'session_id' => $session->id,
            'title'      => $session->title,
        ]);
    }

    /**
     * ──────────────────────────────────────────────────────────
     * Envoyer un message et obtenir la réponse IA
     * ──────────────────────────────────────────────────────────
     *
     * Body JSON attendu :
     * {
     *   "session_id": 1,
     *   "message": "Comment ça va ?",
     *   "model": "mistral-small-latest"  (optionnel)
     * }
     */
    public function sendMessage(Request $request): JsonResponse
    {
        // Validation
        $validated = $request->validate([
            'session_id' => 'required|integer|exists:chat_sessions,id',
            'message'    => 'required|string|min:1|max:4000',
            'model'      => 'sometimes|string|in:mistral-small-latest,mistral-medium-latest,mistral-large-latest',
        ]);

        // Vérifier que la session appartient à l'utilisateur connecté
        $session = ChatSession::where('id', $validated['session_id'])
            ->where('user_id', Auth::id())
            ->firstOrFail();

        try {
            // 1. Sauvegarder le message utilisateur en base
            ChatMessage::create([
                'chat_session_id' => $session->id,
                'user_id'         => Auth::id(),
                'role'            => 'user',
                'content'         => $validated['message'],
            ]);

            // 2. Récupérer l'historique de la session (pour le contexte)
            $history = $this->getSessionHistory($session->id);

            // 3. Appeler Mistral AI
            $aiResponse = $this->mistralService->chat(
                userMessage: $validated['message'],
                history:     $history,
                model:       $validated['model'] ?? null
            );

            // 4. Sauvegarder la réponse IA
            $assistantMessage = ChatMessage::create([
                'chat_session_id' => $session->id,
                'user_id'         => Auth::id(),
                'role'            => 'assistant',
                'content'         => $aiResponse['content'],
                'tokens_used'     => $aiResponse['tokens'],
                'model'           => $aiResponse['model'],
            ]);

            // 5. Mettre à jour le total de tokens de la session
            $session->increment('total_tokens', $aiResponse['tokens']);

            // 6. Mettre à jour le titre de la session si c'est le premier message
            $messageCount = ChatMessage::where('chat_session_id', $session->id)->count();
            if ($messageCount === 2 && $session->title === 'Nouvelle conversation') {
                // Titre automatique basé sur le premier message (tronqué)
                $session->update([
                    'title' => \Str::limit($validated['message'], 40),
                ]);
            }

            return response()->json([
                'success'  => true,
                'message'  => [
                    'id'      => $assistantMessage->id,
                    'role'    => 'assistant',
                    'content' => $aiResponse['content'],
                    'tokens'  => $aiResponse['tokens'],
                    'model'   => $aiResponse['model'],
                    'time'    => now()->format('H:i'),
                ],
                'session'  => [
                    'id'           => $session->id,
                    'title'        => $session->fresh()->title,
                    'total_tokens' => $session->total_tokens + $aiResponse['tokens'],
                ],
            ]);

        } catch (MistralRateLimitException $e) {
            // Rate limit local — réponse 429
            return response()->json([
                'success' => false,
                'error'   => 'rate_limit',
                'message' => $e->getMessage(),
            ], 429);

        } catch (MistralApiException $e) {
            // Erreur API — réponse 503
            Log::error('Mistral API error in ChatbotController', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'error'   => 'api_error',
                'message' => 'L\'IA est temporairement indisponible. Réessayez dans quelques secondes.',
            ], 503);

        } catch (\Exception $e) {
            // Erreur générique
            Log::error('ChatbotController unexpected error', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'error'   => 'unexpected',
                'message' => 'Une erreur inattendue s\'est produite.',
            ], 500);
        }
    }

    /**
     * ──────────────────────────────────────────────────────────
     * Charger les messages d'une session existante
     * ──────────────────────────────────────────────────────────
     */
    public function loadSession(int $sessionId): JsonResponse
    {
        $session = ChatSession::where('id', $sessionId)
            ->where('user_id', Auth::id())
            ->with(['messages' => fn($q) => $q->orderBy('created_at')])
            ->firstOrFail();

        $messages = $session->messages->map(fn($msg) => [
            'id'      => $msg->id,
            'role'    => $msg->role,
            'content' => $msg->content,
            'tokens'  => $msg->tokens_used,
            'time'    => $msg->created_at->format('H:i'),
        ]);

        return response()->json([
            'success'  => true,
            'session'  => [
                'id'           => $session->id,
                'title'        => $session->title,
                'total_tokens' => $session->total_tokens,
                'created_at'   => $session->created_at->diffForHumans(),
            ],
            'messages' => $messages,
        ]);
    }

    /**
     * ──────────────────────────────────────────────────────────
     * Supprimer une session et ses messages
     * ──────────────────────────────────────────────────────────
     */
    public function deleteSession(int $sessionId): JsonResponse
    {
        $session = ChatSession::where('id', $sessionId)
            ->where('user_id', Auth::id())
            ->firstOrFail();

        $session->delete(); // Soft delete (messages conservés en base)

        return response()->json(['success' => true]);
    }

    /**
     * ──────────────────────────────────────────────────────────
     * Retourner les statistiques d'utilisation de l'API
     * ──────────────────────────────────────────────────────────
     */
    public function getUsageStats(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'stats'   => $this->mistralService->getUsageStats(),
        ]);
    }

    /**
     * ──────────────────────────────────────────────────────────
     * Récupérer l'historique d'une session pour le contexte IA
     * ──────────────────────────────────────────────────────────
     * Retourne uniquement les rôles user/assistant (pas system)
     * Les 10 derniers messages pour limiter la taille du contexte
     */
    private function getSessionHistory(int $sessionId): array
    {
        return ChatMessage::where('chat_session_id', $sessionId)
            ->whereIn('role', ['user', 'assistant'])
            ->orderBy('created_at')
            ->limit(20) // 20 messages = 10 échanges
            ->get(['role', 'content'])
            ->map(fn($msg) => ['role' => $msg->role, 'content' => $msg->content])
            ->toArray();
    }
}
