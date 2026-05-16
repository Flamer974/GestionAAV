<?php
/**
 * config/mistral.php
 * Configuration centralisée pour l'API Mistral AI.
 * Accès : config('mistral.api_key'), config('mistral.models.default'), etc.
 */

return [

    /*
    |--------------------------------------------------------------------------
    | Clé API Mistral
    |--------------------------------------------------------------------------
    | Récupérée depuis .env — ne jamais hardcoder directement ici.
    */
    'api_key' => env('MISTRAL_API_KEY'),

    /*
    |--------------------------------------------------------------------------
    | URL de base de l'API
    |--------------------------------------------------------------------------
    */
    'api_url' => env('MISTRAL_API_URL', 'https://api.mistral.ai/v1'),

    /*
    |--------------------------------------------------------------------------
    | Modèles disponibles
    |--------------------------------------------------------------------------
    | mistral-small-latest  → Rapide, économique (quota gratuit)
    | mistral-medium-latest → Équilibré
    | mistral-large-latest  → Le plus puissant (consomme plus de quota)
    */
    'models' => [
        'default'  => env('MISTRAL_DEFAULT_MODEL', 'mistral-small-latest'),
        'fast'     => 'mistral-small-latest',
        'balanced' => 'mistral-medium-latest',
        'powerful' => 'mistral-large-latest',
        'embed'    => 'mistral-embed',
    ],

    /*
    |--------------------------------------------------------------------------
    | Paramètres de génération par défaut
    |--------------------------------------------------------------------------
    */
    'max_tokens'  => (int) env('MISTRAL_MAX_TOKENS', 1024),
    'temperature' => (float) env('MISTRAL_TEMPERATURE', 0.7),
    'top_p'       => 1.0,

    /*
    |--------------------------------------------------------------------------
    | Rate limiting — protection du quota gratuit
    |--------------------------------------------------------------------------
    | Le plan gratuit Mistral autorise ~5 req/s et ~500k tokens/mois.
    | Ces valeurs sont appliquées dans MistralService via le cache Laravel.
    */
    'rate_limit' => [
        'per_minute' => (int) env('MISTRAL_RATE_LIMIT_PER_MINUTE', 10),
        'per_day'    => (int) env('MISTRAL_RATE_LIMIT_PER_DAY', 100),
    ],

    /*
    |--------------------------------------------------------------------------
    | Timeout HTTP (secondes)
    |--------------------------------------------------------------------------
    */
    'timeout'         => 30,
    'connect_timeout' => 5,

    /*
    |--------------------------------------------------------------------------
    | Prompts système par module
    |--------------------------------------------------------------------------
    | Personnalisables selon votre usage quotidien.
    */
    'system_prompts' => [

        'chatbot' => 'Tu es HomeBase Assistant, un assistant personnel intelligent,
utile et concis. Tu aides l\'utilisateur dans ses tâches quotidiennes,
ses projets et ses questions. Réponds toujours en français sauf demande contraire.
Sois direct, structuré et pratique.',

        'note_summarize' => 'Tu es un assistant expert en synthèse de texte.
Résume le contenu suivant de manière claire et concise en français,
en conservant les informations essentielles. Maximum 3 paragraphes.',

        'note_enhance' => 'Tu es un assistant expert en rédaction professionnelle.
Améliore le texte suivant : corrige l\'orthographe, améliore la clarté
et la structure, tout en préservant le sens original. Réponds uniquement
avec le texte amélioré, sans commentaire.',

        'task_suggest' => 'Tu es un expert en gestion de projet et productivité.
Analyse la liste de tâches suivante et propose des suggestions pour :
1. Prioriser les tâches les plus importantes
2. Identifier des tâches manquantes
3. Regrouper les tâches similaires
Sois concis et actionnable.',

        'task_breakdown' => 'Tu es un expert en gestion de projet agile.
Décompose la tâche suivante en sous-tâches concrètes et actionnables.
Format de réponse : liste numérotée, maximum 7 sous-tâches.',
    ],
];
