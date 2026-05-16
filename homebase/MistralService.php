<?php

namespace App\Services;

/**
 * ============================================================
 * app/Services/MistralService.php
 * ============================================================
 * Service principal d'intégration avec l'API Mistral AI.
 *
 * Fonctionnalités :
 *  - Envoi de messages (chat completion)
 *  - Gestion du contexte de conversation
 *  - Rate limiting local (protection quota gratuit)
 *  - Retry automatique sur erreur réseau
 *  - Logging des tokens consommés
 *  - Streaming (optionnel)
 * ============================================================
 */

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\Client\RequestException;
use App\Exceptions\MistralRateLimitException;
use App\Exceptions\MistralApiException;

class MistralService
{
    /** URL de base de l'API */
    private string $apiUrl;

    /** Clé API */
    private string $apiKey;

    /** Modèle utilisé par défaut */
    private string $defaultModel;

    /** Nombre max de tokens par réponse */
    private int $maxTokens;

    /** Température de génération (créativité) */
    private float $temperature;

    public function __construct()
    {
        $this->apiUrl       = config('mistral.api_url', 'https://api.mistral.ai/v1');
        $this->apiKey       = config('mistral.api_key');
        $this->defaultModel = config('mistral.models.default', 'mistral-small-latest');
        $this->maxTokens    = config('mistral.max_tokens', 1024);
        $this->temperature  = config('mistral.temperature', 0.7);
    }

    /**
     * ──────────────────────────────────────────────────────────
     * Envoyer un message et recevoir une réponse
     * ──────────────────────────────────────────────────────────
     *
     * @param  string $userMessage     Message de l'utilisateur
     * @param  array  $history         Historique [{role, content}, ...]
     * @param  string|null $systemPrompt  Prompt système (override)
     * @param  string|null $model         Modèle (override)
     * @return array  ['content' => string, 'tokens' => int, 'model' => string]
     *
     * @throws MistralRateLimitException  Si le quota local est dépassé
     * @throws MistralApiException        Si l'API retourne une erreur
     */
    public function chat(
        string  $userMessage,
        array   $history      = [],
        ?string $systemPrompt = null,
        ?string $model        = null
    ): array {
        // Vérifier le rate limiting local
        $this->checkRateLimit();

        // Construire les messages
        $messages = $this->buildMessages($userMessage, $history, $systemPrompt);

        // Choisir le modèle
        $selectedModel = $model ?? $this->defaultModel;

        // Appel API avec retry (3 tentatives, délai exponentiel)
        $response = $this->callApiWithRetry('/chat/completions', [
            'model'       => $selectedModel,
            'messages'    => $messages,
            'max_tokens'  => $this->maxTokens,
            'temperature' => $this->temperature,
        ]);

        // Extraire la réponse
        $content   = $response['choices'][0]['message']['content'] ?? '';
        $tokensIn  = $response['usage']['prompt_tokens'] ?? 0;
        $tokensOut = $response['usage']['completion_tokens'] ?? 0;
        $total     = $response['usage']['total_tokens'] ?? 0;

        // Logger la consommation de tokens
        Log::info('Mistral API call', [
            'model'        => $selectedModel,
            'tokens_in'    => $tokensIn,
            'tokens_out'   => $tokensOut,
            'total'        => $total,
        ]);

        // Incrémenter les compteurs de rate limiting
        $this->incrementRateLimitCounters();

        return [
            'content'    => $content,
            'tokens'     => $total,
            'tokens_in'  => $tokensIn,
            'tokens_out' => $tokensOut,
            'model'      => $selectedModel,
        ];
    }

    /**
     * ──────────────────────────────────────────────────────────
     * Résumer un texte (module Notes)
     * ──────────────────────────────────────────────────────────
     */
    public function summarize(string $text): string
    {
        $systemPrompt = config('mistral.system_prompts.note_summarize');

        $result = $this->chat(
            userMessage:  $text,
            systemPrompt: $systemPrompt,
            model:        config('mistral.models.fast') // Modèle rapide pour économiser
        );

        return $result['content'];
    }

    /**
     * ──────────────────────────────────────────────────────────
     * Améliorer un texte (module Notes)
     * ──────────────────────────────────────────────────────────
     */
    public function enhanceText(string $text): string
    {
        $systemPrompt = config('mistral.system_prompts.note_enhance');

        $result = $this->chat(
            userMessage:  $text,
            systemPrompt: $systemPrompt,
            model:        config('mistral.models.fast')
        );

        return $result['content'];
    }

    /**
     * ──────────────────────────────────────────────────────────
     * Suggérer des améliorations pour une liste de tâches
     * ──────────────────────────────────────────────────────────
     */
    public function suggestTaskImprovements(array $tasks): string
    {
        $systemPrompt = config('mistral.system_prompts.task_suggest');

        // Formater les tâches pour le prompt
        $taskList = collect($tasks)->map(function ($task, $index) {
            return ($index + 1) . ". [{$task['priority']}] {$task['title']}";
        })->join("\n");

        $result = $this->chat(
            userMessage:  "Voici mes tâches actuelles :\n\n{$taskList}",
            systemPrompt: $systemPrompt,
            model:        config('mistral.models.fast')
        );

        return $result['content'];
    }

    /**
     * ──────────────────────────────────────────────────────────
     * Décomposer une tâche complexe en sous-tâches
     * ──────────────────────────────────────────────────────────
     */
    public function breakdownTask(string $taskTitle, string $description = ''): string
    {
        $systemPrompt = config('mistral.system_prompts.task_breakdown');

        $prompt = "Tâche : {$taskTitle}";
        if ($description) {
            $prompt .= "\nDescription : {$description}";
        }

        $result = $this->chat(
            userMessage:  $prompt,
            systemPrompt: $systemPrompt,
            model:        config('mistral.models.fast')
        );

        return $result['content'];
    }

    /**
     * ──────────────────────────────────────────────────────────
     * Construire le tableau de messages pour l'API
     * ──────────────────────────────────────────────────────────
     */
    private function buildMessages(
        string  $userMessage,
        array   $history      = [],
        ?string $systemPrompt = null
    ): array {
        $messages = [];

        // Ajouter le prompt système s'il existe
        if ($systemPrompt) {
            $messages[] = [
                'role'    => 'system',
                'content' => $systemPrompt,
            ];
        } else {
            // Prompt par défaut du chatbot
            $messages[] = [
                'role'    => 'system',
                'content' => config('mistral.system_prompts.chatbot'),
            ];
        }

        // Ajouter l'historique de conversation (limité aux 10 derniers messages)
        $limitedHistory = array_slice($history, -10);
        foreach ($limitedHistory as $message) {
            $messages[] = [
                'role'    => $message['role'],
                'content' => $message['content'],
            ];
        }

        // Ajouter le message actuel de l'utilisateur
        $messages[] = [
            'role'    => 'user',
            'content' => $userMessage,
        ];

        return $messages;
    }

    /**
     * ──────────────────────────────────────────────────────────
     * Appel API avec logique de retry
     * ──────────────────────────────────────────────────────────
     *
     * @throws MistralApiException
     */
    private function callApiWithRetry(string $endpoint, array $payload, int $maxRetries = 3): array
    {
        $attempt = 0;
        $lastException = null;

        while ($attempt < $maxRetries) {
            try {
                $response = Http::withHeaders([
                    'Authorization' => 'Bearer ' . $this->apiKey,
                    'Content-Type'  => 'application/json',
                    'Accept'        => 'application/json',
                ])
                ->timeout(config('mistral.timeout', 30))
                ->connectTimeout(config('mistral.connect_timeout', 5))
                ->post($this->apiUrl . $endpoint, $payload);

                // Vérifier le statut HTTP
                if ($response->status() === 429) {
                    // Rate limit côté API Mistral
                    $retryAfter = $response->header('Retry-After', 60);
                    throw new MistralRateLimitException(
                        "Rate limit API Mistral atteint. Réessayer dans {$retryAfter}s."
                    );
                }

                if ($response->status() === 401) {
                    throw new MistralApiException('Clé API Mistral invalide. Vérifiez MISTRAL_API_KEY dans .env');
                }

                if (!$response->successful()) {
                    $error = $response->json('error.message', 'Erreur API inconnue');
                    throw new MistralApiException("Erreur Mistral API: {$error}");
                }

                return $response->json();

            } catch (MistralRateLimitException $e) {
                // Ne pas réessayer sur rate limit
                throw $e;

            } catch (MistralApiException $e) {
                // Ne pas réessayer sur erreur d'auth
                throw $e;

            } catch (\Exception $e) {
                $lastException = $e;
                $attempt++;

                if ($attempt < $maxRetries) {
                    // Délai exponentiel : 1s, 2s, 4s...
                    $delay = pow(2, $attempt - 1);
                    Log::warning("Mistral API retry {$attempt}/{$maxRetries}", [
                        'error' => $e->getMessage(),
                        'delay' => $delay,
                    ]);
                    sleep($delay);
                }
            }
        }

        throw new MistralApiException(
            "Impossible de contacter l'API Mistral après {$maxRetries} tentatives : " .
            $lastException?->getMessage()
        );
    }

    /**
     * ──────────────────────────────────────────────────────────
     * Vérifier le rate limiting local (protection quota gratuit)
     * ──────────────────────────────────────────────────────────
     *
     * @throws MistralRateLimitException
     */
    private function checkRateLimit(): void
    {
        $userId = auth()->id() ?? 'anonymous';

        // Vérifier limite par minute
        $minuteKey   = "mistral_rate_minute_{$userId}";
        $minuteCount = Cache::get($minuteKey, 0);
        $minuteLimit = config('mistral.rate_limit.per_minute', 10);

        if ($minuteCount >= $minuteLimit) {
            throw new MistralRateLimitException(
                "Limite locale dépassée : {$minuteLimit} requêtes/minute. Attendez un moment."
            );
        }

        // Vérifier limite par jour
        $dayKey   = "mistral_rate_day_{$userId}_" . now()->format('Y-m-d');
        $dayCount = Cache::get($dayKey, 0);
        $dayLimit = config('mistral.rate_limit.per_day', 100);

        if ($dayCount >= $dayLimit) {
            throw new MistralRateLimitException(
                "Limite quotidienne atteinte ({$dayLimit} requêtes). Revenez demain."
            );
        }
    }

    /**
     * Incrémenter les compteurs après un appel réussi
     */
    private function incrementRateLimitCounters(): void
    {
        $userId = auth()->id() ?? 'anonymous';

        $minuteKey = "mistral_rate_minute_{$userId}";
        Cache::add($minuteKey, 0, 60);       // Expire après 1 minute
        Cache::increment($minuteKey);

        $dayKey = "mistral_rate_day_{$userId}_" . now()->format('Y-m-d');
        Cache::add($dayKey, 0, 86400);        // Expire après 24h
        Cache::increment($dayKey);
    }

    /**
     * Obtenir les statistiques d'utilisation de l'API
     */
    public function getUsageStats(): array
    {
        $userId = auth()->id() ?? 'anonymous';

        return [
            'minute_usage' => Cache::get("mistral_rate_minute_{$userId}", 0),
            'minute_limit' => config('mistral.rate_limit.per_minute'),
            'day_usage'    => Cache::get("mistral_rate_day_{$userId}_" . now()->format('Y-m-d'), 0),
            'day_limit'    => config('mistral.rate_limit.per_day'),
        ];
    }
}
