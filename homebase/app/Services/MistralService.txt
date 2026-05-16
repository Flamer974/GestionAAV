<?php

namespace App\Services;

use App\Exceptions\MistralApiException;
use App\Exceptions\MistralRateLimitException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class MistralService
{
    protected string $apiUrl;
    protected string $apiKey;
    protected string $defaultModel;
    protected int $maxTokens;
    protected float $temperature;

    public function __construct()
    {
        $this->apiUrl = config('mistral.api_url', 'https://api.mistral.ai/v1');
        $this->apiKey = config('mistral.api_key');
        $this->defaultModel = config('mistral.models.default', 'mistral-small-latest');
        $this->maxTokens = config('mistral.max_tokens', 1024);
        $this->temperature = config('mistral.temperature', 0.7);

        if (!$this->apiKey) {
            Log::error('Mistral API key not configured');
        }
    }

    public function chat(
        string $userMessage,
        array $history = [],
        ?string $systemPrompt = null,
        ?string $model = null
    ): array {
        $this->checkRateLimit();

        $messages = $this->buildMessages($userMessage, $history, $systemPrompt);
        $selectedModel = $model ?? $this->defaultModel;

        $response = $this->callApiWithRetry('/chat/completions', [
            'model' => $selectedModel,
            'messages' => $messages,
            'max_tokens' => $this->maxTokens,
            'temperature' => $this->temperature,
        ]);

        $content = $response['choices'][0]['message']['content'] ?? '';
        $tokensIn = $response['usage']['prompt_tokens'] ?? 0;
        $tokensOut = $response['usage']['completion_tokens'] ?? 0;
        $total = $response['usage']['total_tokens'] ?? 0;

        Log::info('Mistral API call', [
            'model' => $selectedModel,
            'tokens_in' => $tokensIn,
            'tokens_out' => $tokensOut,
            'total' => $total,
        ]);

        $this->incrementRateLimitCounters();

        return [
            'content' => $content,
            'tokens' => $total,
            'tokens_in' => $tokensIn,
            'tokens_out' => $tokensOut,
            'model' => $selectedModel,
        ];
    }

    public function summarize(string $text): string
    {
        $systemPrompt = config('mistral.system_prompts.note_summarize');
        $result = $this->chat(
            userMessage: $text,
            systemPrompt: $systemPrompt,
            model: config('mistral.models.fast')
        );
        return $result['content'];
    }

    public function enhanceText(string $text): string
    {
        $systemPrompt = config('mistral.system_prompts.note_enhance');
        $result = $this->chat(
            userMessage: $text,
            systemPrompt: $systemPrompt,
            model: config('mistral.models.fast')
        );
        return $result['content'];
    }

    public function suggestTaskImprovements(array $tasks): string
    {
        $systemPrompt = config('mistral.system_prompts.task_suggest');
        $taskList = collect($tasks)->map(function ($task, $index) {
            return ($index + 1) . ". [{$task['priority']}] {$task['title']}";
        })->join("\n");

        $result = $this->chat(
            userMessage: "Voici mes tâches actuelles :\n\n{$taskList}",
            systemPrompt: $systemPrompt,
            model: config('mistral.models.fast')
        );
        return $result['content'];
    }

    public function breakdownTask(string $taskTitle, string $description = ''): string
    {
        $systemPrompt = config('mistral.system_prompts.task_breakdown');
        $prompt = "Tâche : {$taskTitle}";
        if ($description) {
            $prompt .= "\nDescription : {$description}";
        }

        $result = $this->chat(
            userMessage: $prompt,
            systemPrompt: $systemPrompt,
            model: config('mistral.models.fast')
        );
        return $result['content'];
    }

    private function buildMessages(
        string $userMessage,
        array $history = [],
        ?string $systemPrompt = null
    ): array {
        $messages = [];

        if ($systemPrompt) {
            $messages[] = ['role' => 'system', 'content' => $systemPrompt];
        } else {
            $messages[] = ['role' => 'system', 'content' => config('mistral.system_prompts.chatbot')];
        }

        $limitedHistory = array_slice($history, -10);
        foreach ($limitedHistory as $message) {
            $messages[] = ['role' => $message['role'], 'content' => $message['content']];
        }

        $messages[] = ['role' => 'user', 'content' => $userMessage];
        return $messages;
    }

    private function callApiWithRetry(string $endpoint, array $payload, int $maxRetries = 3): array
    {
        $attempt = 0;
        $lastException = null;

        while ($attempt < $maxRetries) {
            try {
                $response = Http::withHeaders([
                    'Authorization' => 'Bearer ' . $this->apiKey,
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json',
                ])
                ->timeout(config('mistral.timeout', 30))
                ->connectTimeout(config('mistral.connect_timeout', 5))
                ->post($this->apiUrl . $endpoint, $payload);

                if ($response->status() === 429) {
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

            } catch (MistralRateLimitException|MistralApiException $e) {
                throw $e;
            } catch (Throwable $e) {
                $lastException = $e;
                $attempt++;

                if ($attempt < $maxRetries) {
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

    private function checkRateLimit(): void
    {
        $userId = auth()->id() ?? 'anonymous';

        $minuteKey = "mistral_rate_minute_{$userId}";
        $minuteCount = Cache::get($minuteKey, 0);
        $minuteLimit = config('mistral.rate_limit.per_minute', 10);

        if ($minuteCount >= $minuteLimit) {
            throw new MistralRateLimitException(
                "Limite locale dépassée : {$minuteLimit} requêtes/minute. Attendez un moment."
            );
        }

        $dayKey = "mistral_rate_day_{$userId}_" . now()->format('Y-m-d');
        $dayCount = Cache::get($dayKey, 0);
        $dayLimit = config('mistral.rate_limit.per_day', 100);

        if ($dayCount >= $dayLimit) {
            throw new MistralRateLimitException(
                "Limite quotidienne atteinte ({$dayLimit} requêtes). Revenez demain."
            );
        }
    }

    private function incrementRateLimitCounters(): void
    {
        $userId = auth()->id() ?? 'anonymous';

        $minuteKey = "mistral_rate_minute_{$userId}";
        Cache::add($minuteKey, 0, 60);
        Cache::increment($minuteKey);

        $dayKey = "mistral_rate_day_{$userId}_" . now()->format('Y-m-d');
        Cache::add($dayKey, 0, 86400);
        Cache::increment($dayKey);
    }

    public function getUsageStats(): array
    {
        $userId = auth()->id() ?? 'anonymous';

        return [
            'minute_usage' => Cache::get("mistral_rate_minute_{$userId}", 0),
            'minute_limit' => config('mistral.rate_limit.per_minute'),
            'day_usage' => Cache::get("mistral_rate_day_{$userId}_" . now()->format('Y-m-d'), 0),
            'day_limit' => config('mistral.rate_limit.per_day'),
        ];
    }
}