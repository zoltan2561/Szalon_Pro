<?php

use App\Http\Middleware\AdminAccess;
use App\Http\Middleware\SecurityHeaders;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(web: __DIR__.'/../routes/web.php', commands: __DIR__.'/../routes/console.php', health: '/up')
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias(['admin' => AdminAccess::class]);
        $middleware->redirectGuestsTo('/belepes');
        $middleware->append(SecurityHeaders::class);
        $middleware->validateCsrfTokens(except: ['ertekeles/lemondas/*']);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->dontFlash(['password', 'password_confirmation', 'current_password', 'new_password', 'token', 'billing_agent_key']);
    })->create();
