<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // DVLA-SAFE-002: the payment gateway has no session or CSRF token; the HMAC signature protects it.
        // DVLA-023: "invoices/*" was added when the send button in emailed previews stopped working;
        // it also covers POST /invoices/{invoice}/send for signed-in users.
        $middleware->validateCsrfTokens(except: [
            'webhooks/payments',
            'invoices/*',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
