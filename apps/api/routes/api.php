<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// Liveness check — used by the SPA to confirm it can reach the API.
Route::get('/health', function () {
    return response()->json([
        'status' => 'ok',
        'service' => 'helpdesk-api',
        'time' => now()->toIso8601String(),
    ]);
});

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');
