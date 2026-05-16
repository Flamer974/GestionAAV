<?php

namespace App\Http\Controllers;

use App\Models\ChatMessage;
use App\Models\ChatSession;
use App\Services\MistralService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Throwable;

class ChatbotController extends Controller
{
    public function __construct(
        private readonly MistralService $mistralService
    ) {
        $this->middleware('auth');
        $this->middleware('throttle:30,1')->only(['sendMessage']);
    }

    public function index(): View
    {
        $sessions = ChatSession::where('user_id', Auth::id())
            ->orderByDesc('created_at')
            ->limit(20)
            ->get(['id', 'title', 'total_tokens', 'created_at']);

        $usageStats = $this->mistralService->getUsageStats();

        return view('chatbot.index', compact('sessions', 'usageStats'));
    }

    public function createSession(Request $request): JsonResponse
    {
        $session = ChatSession::create([
            'user_id' => Auth::id(),
            'title' => $request->input('title', 'Nouvelle conversation'),
            'model' => config('mistral.models.default'),
        ]);

        return response()->json([
            'success' => true,
            'session_id' => $session->id,
            'title' => $session->title,
        ]);
    }

    public function sendMessage(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'session_id' => 'required|integer|exists:chat_sessions,id',
            'message' => 'required|string|min:1|max:4000',
            'model' => 'sometimes|string|in:mistral-small-latest,mistral-medium-latest,mistral-large-latest',
        ]);

        $session = ChatSession::where('id', $validated['session_id'])
            ->where('user_id', Auth::id())
            ->firstOrFail();

        try {
            ChatMessage::create([
                'chat_session_id' => $session->id,
                'user_id' => Auth::id(),
                'role' => 'user',
                'content' => $validated['message'],
            ]);

            $history = $this->getSessionHistory($session->id);

            $aiResponse = $this->mistralService->chat(
                userMessage: $validated['message'],
                history: $history,
                model: $validated['model'] ?? null
            );

            $assistantMessage = ChatMessage::create([
                'chat_session_id' => $session->id,
                'user_id' => Auth::id(),
                'role' => 'assistant',
                'content' => $aiResponse['content'],
                'tokens_used' => $aiResponse['tokens'],
                'model' => $aiResponse['model'],
            ]);

            $session->increment('total_tokens', $aiResponse['tokens']);

            $messageCount = ChatMessage::where('chat_session_id', $session->id)->count();
            if ($messageCount === 2 && $session->title === 'Nouvelle conversation') {
                $session->update([
                    'title' => Str::limit($validated['message'], 40),
                ]);
            }

            return response()->json([
                'success' => true,
                'message' => [
                    'id' => $assistantMessage->id,
                    'role' => 'assistant',
                    'content' => $aiResponse['content'],
                    'tokens' => $aiResponse['tokens'],
                    'model' => $aiResponse['model'],
                    'time' => now()->format('H:i'),
                ],
                'session' => [
                    'id' => $session->id,
                    'title' => $session->fresh()->title,
                    'total_tokens' => $session->total_tokens + $aiResponse['tokens'],
                ],
            ]);

        } catch (\App\Exceptions\MistralRateLimitException $e) {
            return response()->json([
                'success' => false,
                'error' => 'rate_limit',
                'message' => $e->getMessage(),
            ], 429);

        } catch (\App\Exceptions\MistralApiException $e) {
            Log::error('Mistral API error in ChatbotController', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'error' => 'api_error',
                'message' => 'L\'IA est temporairement indisponible. Réessayez dans quelques secondes.',
            ], 503);

        } catch (Throwable $e) {
            Log::error('ChatbotController unexpected error', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'error' => 'unexpected',
                'message' => 'Une erreur inattendue s\'est produite.',
            ], 500);
        }
    }

    public function loadSession(int $sessionId): JsonResponse
    {
        $session = ChatSession::where('id', $sessionId)
            ->where('user_id', Auth::id())
            ->with(['messages' => fn($q) => $q->orderBy('created_at')])
            ->firstOrFail();

        $messages = $session->messages->map(fn($msg) => [
            'id' => $msg->id,
            'role' => $msg->role,
            'content' => $msg->content,
            'tokens' => $msg->tokens_used,
            'time' => $msg->created_at->format('H:i'),
        ]);

        return response()->json([
            'success' => true,
            'session' => [
                'id' => $session->id,
                'title' => $session->title,
                'total_tokens' => $session->total_tokens,
                'created_at' => $session->created_at->diffForHumans(),
            ],
            'messages' => $messages,
        ]);
    }

    public function deleteSession(int $sessionId): JsonResponse
    {
        $session = ChatSession::where('id', $sessionId)
            ->where('user_id', Auth::id())
            ->firstOrFail();

        $session->delete();

        return response()->json(['success' => true]);
    }

    public function getUsageStats(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'stats' => $this->mistralService->getUsageStats(),
        ]);
    }

    private function getSessionHistory(int $sessionId): array
    {
        return ChatMessage::where('chat_session_id', $sessionId)
            ->whereIn('role', ['user', 'assistant'])
            ->orderBy('created_at')
            ->limit(20)
            ->get(['role', 'content'])
            ->map(fn($msg) => ['role' => $msg->role, 'content' => $msg->content])
            ->toArray();
    }
}