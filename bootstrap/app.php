<?php

use App\Http\Middleware\AdminAccess;
use App\Http\Middleware\EnsureTenantUser;
use App\Http\Middleware\KeepPageNavigation;
use App\Http\Middleware\ProtectDemoViewer;
use App\Http\Middleware\ResolveTenant;
use App\Http\Middleware\SecurityHeaders;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\AuthenticateSession;
use Illuminate\Session\Middleware\StartSession;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(web: __DIR__.'/../routes/web.php', commands: __DIR__.'/../routes/console.php', health: '/up')
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias(['admin' => AdminAccess::class]);
        $middleware->redirectGuestsTo('/belepes');
        $middleware->append(SecurityHeaders::class);
        $middleware->web(prepend: [ResolveTenant::class], append: [AuthenticateSession::class, EnsureTenantUser::class, ProtectDemoViewer::class], replace: [StartSession::class => KeepPageNavigation::class]);
        $middleware->prependToPriorityList(EncryptCookies::class, ResolveTenant::class);
        $middleware->prependToPriorityList(SubstituteBindings::class, EnsureTenantUser::class);
        $middleware->prependToPriorityList(SubstituteBindings::class, ProtectDemoViewer::class);
        $middleware->validateCsrfTokens(except: ['ertekeles/lemondas/*']);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->dontFlash(['password', 'password_confirmation', 'current_password', 'new_password', 'token', 'billing_agent_key']);
    })->create();
