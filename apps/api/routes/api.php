<?php

use App\Http\Controllers\AgentController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\InboundMailController;
use App\Http\Controllers\TicketController;
use App\Http\Controllers\UserController;
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

// Admin-only user management.
Route::middleware(['auth:sanctum', 'admin'])->group(function () {
    Route::get('/users', [UserController::class, 'index']);
    Route::delete('/users/{user}', [UserController::class, 'destroy']);
});

// Ticket workspace (both roles; scoping is a later phase).
Route::middleware('auth:sanctum')->group(function () {
    // Aggregate ticket metrics for the dashboard (counts + category breakdown).
    Route::get('/dashboard', [DashboardController::class, 'index']);
    Route::get('/tickets', [TicketController::class, 'index']);
    Route::get('/tickets/{ticket}', [TicketController::class, 'show']);
    Route::patch('/tickets/{ticket}', [TicketController::class, 'update']);
    Route::post('/tickets/{ticket}/polish', [TicketController::class, 'polish']);
    Route::post('/tickets/{ticket}/summarize', [TicketController::class, 'summarize']);
    // Assignable staff for the assignment picker.
    Route::get('/agents', [AgentController::class, 'index']);
});

// Inbound-email webhook. Unauthenticated (machine-to-machine) — guarded inside
// the controller by a shared secret — and throttled against abuse.
Route::post('/mail/inbound', [InboundMailController::class, 'store'])
    ->middleware('throttle:60,1');
