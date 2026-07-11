<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Master switch
    |--------------------------------------------------------------------------
    |
    | When false, every AI step is skipped and the app degrades to a normal
    | (manual) helpdesk — classification is left blank, replies aren't polished,
    | tickets aren't auto-resolved. Keep false until a real key is configured.
    |
    */

    'enabled' => env('AI_ENABLED', false),

    /*
    |--------------------------------------------------------------------------
    | LLM (OpenAI-compatible endpoint)
    |--------------------------------------------------------------------------
    |
    | Reply-polish, ticket classification, and KB auto-resolve call an
    | OpenAI-compatible Chat Completions API directly from PHP (no JS SDK; the
    | key stays server-side). The base URL is configurable, so any compatible
    | provider works — the default targets Groq's free tier running
    | llama-3.3-70b-versatile, a fast model well-suited to these short tasks.
    | (Config key stays `openai` for backwards compatibility.)
    |
    */

    'openai' => [
        'key' => env('OPENAI_API_KEY'),
        'base_url' => env('OPENAI_BASE_URL', 'https://api.groq.com/openai/v1'),
        'model' => env('OPENAI_MODEL', 'llama-3.3-70b-versatile'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Knowledge base
    |--------------------------------------------------------------------------
    |
    | Path to a single plain-text/Markdown KB file used for auto-resolving
    | inbound tickets. Relative paths resolve from the API app root. When the
    | file is missing or empty, auto-resolve simply does nothing.
    |
    */

    'kb_path' => env('AI_KB_PATH', 'storage/app/knowledge-base.md'),

];
