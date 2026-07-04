<?php

return [
    // Paths the SPA is allowed to call cross-origin.
    'paths' => ['api/*', 'sanctum/csrf-cookie', 'login', 'logout'],

    'allowed_methods' => ['*'],

    // Only the Vue SPA origin. Driven by FRONTEND_URL in .env.
    'allowed_origins' => [env('FRONTEND_URL', 'http://localhost:5173')],

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    // Required for Sanctum's cookie-based SPA authentication.
    'supports_credentials' => true,
];
