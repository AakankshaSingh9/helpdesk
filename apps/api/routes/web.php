<?php

use Illuminate\Support\Facades\Route;

// Serve the built Vue SPA (copied into public/index.html by the Docker build).
// Hashed assets under /assets/* are real files served before Laravel routing;
// the health check ('/up'), the API (routes/api.php), and Sanctum are excluded
// so every *other* path returns index.html and the client-side router takes over.
$spa = function () {
    $index = public_path('index.html');

    // In local dev the SPA is served by Vite, not Laravel, so index.html may be
    // absent — fall back to the framework welcome page instead of 500-ing.
    if (! is_file($index)) {
        return view('welcome');
    }

    return response(file_get_contents($index), 200, ['Content-Type' => 'text/html']);
};

Route::get('/', $spa);
Route::get('/{any}', $spa)->where('any', '^(?!api|sanctum|up|storage).*$');
