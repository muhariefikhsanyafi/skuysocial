<?php

use App\Http\Middleware\EnforceAccountSession;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\RequireActiveModerationState;
use App\Http\Middleware\RequireRecentPasswordConfirmation;
use App\Http\Middleware\SetLocale;
use Illuminate\Contracts\Auth\Middleware\AuthenticatesRequests;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Session\Middleware\StartSession;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->trustHosts(
            at: fn (): array => app()->environment('production')
                ? ['^skuysocial\.id$']
                : ['^skuysocial\.id$', '^localhost$', '^127\.0\.0\.1$'],
            subdomains: false,
        );

        $middleware->web(append: [SetLocale::class, EnforceAccountSession::class]);
        $middleware->alias([
            'inertia' => HandleInertiaRequests::class,
            'moderation.active' => RequireActiveModerationState::class,
            'password.confirm.recent' => RequireRecentPasswordConfirmation::class,
        ]);
        $middleware->appendToPriorityList(StartSession::class, EnforceAccountSession::class);
        $middleware->appendToPriorityList(
            AuthenticatesRequests::class,
            RequireActiveModerationState::class,
        );
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
