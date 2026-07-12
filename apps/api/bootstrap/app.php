<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Behind Railway's TLS-terminating edge proxy, so trust its forwarded
        // headers — otherwise Laravel sees plain HTTP and mis-sets secure cookies
        // / generates http:// URLs. Safe here because the container is only ever
        // reached through that proxy.
        $middleware->trustProxies(at: '*');

        // Let the SPA authenticate `api` routes with its session cookie
        // (Sanctum SPA auth, paired with Fortify's session login).
        $middleware->statefulApi();

        // `admin` → restrict a route to users with the admin role.
        $middleware->alias([
            'admin' => \App\Http\Middleware\EnsureUserIsAdmin::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
