<?php

return [
    'api_key' => env('MISTRAL_API_KEY'),
    'api_url' => env('MISTRAL_API_URL', 'https://api.mistral.ai/v1'),
    
    'models' => [
        'default' => env('MISTRAL_DEFAULT_MODEL', 'mistral-small-latest'),
        'fast' => env('MISTRAL_FAST_MODEL', 'mistral-small-latest'),
        'large' => 'mistral-large-latest',
    ],
    
    'max_tokens' => (int) env('MISTRAL_MAX_TOKENS', 1024),
    'temperature' => (float) env('MISTRAL_TEMPERATURE', 0.7),
    'timeout' => (int) env('MISTRAL_TIMEOUT', 30),
    'connect_timeout' => (int) env('MISTRAL_CONNECT_TIMEOUT', 5),
    
    'rate_limit' => [
        'per_minute' => (int) env('MISTRAL_RATE_LIMIT_PER_MINUTE', 10),
        'per_day' => (int) env('MISTRAL_RATE_LIMIT_PER_DAY', 100),
    ],
    
    'system_prompts' => [
        'chatbot' => "Tu es un assistant personnel utile, concis et bienveillant. Tu aides l'utilisateur à organiser ses tâches, prendre des notes et réfléchir. Réponds en français sauf demande contraire.",
        
        'note_summarize' => "Résume ce texte en 3-5 points clés maximum. Sois concis et pertinent. Format : liste à puces.",
        
        'note_enhance' => "Améliore ce texte : corrige la grammaire, structure les idées, ajoute des titres si utile. Garde le ton original. Ne change pas le sens.",
        
        'task_suggest' => "Analyse cette liste de tâches et suggère : 1) priorités, 2) regroupements logiques, 3) étapes manquantes. Sois pratique et actionnable.",
        
        'task_breakdown' => "Décompose cette tâche en sous-étapes concrètes et ordonnées. Chaque étape doit être réalisable en <30min. Format : liste numérotée.",
    ],
];