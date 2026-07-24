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
    ->withMiddleware(function (Middleware $middleware) {
        // API-only app: an unauthenticated request must get a JSON 401, never
        // a redirect to a (nonexistent) login page. Returning null disables
        // the guest redirect; the AuthenticationException then renders as
        // JSON via shouldRenderJsonWhen below — whatever the Accept header.
        $middleware->redirectGuestsTo(fn () => null);

        $middleware->alias([
            'admin.key' => \App\Http\Middleware\RequireAdminKey::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // API consumers (the mobile app, curl) must always get JSON errors,
        // never an HTML error page — even without an Accept: application/json header.
        $exceptions->shouldRenderJsonWhen(
            fn ($request) => $request->is('api/*') || $request->expectsJson()
        );
    })->create();
