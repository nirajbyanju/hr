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
        $middleware->web(append: [
            \App\Http\Middleware\InitializeTenancyFromSession::class,
            \App\Http\Middleware\SetLocale::class,
        ]);

        // Ordering is load-bearing. This must run:
        //   - AFTER StartSession, because it reads the tenant id from the session
        //   - BEFORE Authenticate, because resolving the signed-in user reads the
        //     `users` table, which only exists in the tenant database
        //   - BEFORE SubstituteBindings, because route-model bindings resolve
        //     against the default connection
        // Pinned against the AuthenticatesRequests CONTRACT, not the Authenticate
        // class: only the contract appears in Laravel's default priority list, and
        // pinning against a class that is absent from that list silently does
        // nothing (the middleware then falls back to plain group order).
        $middleware->prependToPriorityList(
            \Illuminate\Contracts\Auth\Middleware\AuthenticatesRequests::class,
            \App\Http\Middleware\InitializeTenancyFromSession::class,
        );

        // Without this, an expired session on /platform/* would redirect the
        // platform administrator to the TENANT login, which then cannot resolve
        // a tenant for their email address.
        $middleware->redirectGuestsTo(fn (\Illuminate\Http\Request $request) => $request->is('platform', 'platform/*')
            ? route('platform.login')
            : route('login'));

        $middleware->alias([
            'role.any' => \App\Http\Middleware\EnsureUserHasAnyRole::class,
            'permission' => \App\Http\Middleware\EnsureUserHasPermission::class,
            'portal.access' => \App\Http\Middleware\EnsureUserCanAccessPortal::class,
            'central.usable' => \App\Http\Middleware\EnsureCentralAdminIsUsable::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
