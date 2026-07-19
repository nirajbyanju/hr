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
            \App\Http\Middleware\IdentifyTenant::class,
            \App\Http\Middleware\SetLocale::class,
        ]);

        // IdentifyTenant reads the session to resolve the signed-in user's
        // company, so it must run after StartSession. It must also run before
        // SubstituteBindings: route-model bindings resolve through the tenant
        // global scope, and binding first would let a user load another
        // tenant's record by id.
        $middleware->prependToPriorityList(
            \Illuminate\Routing\Middleware\SubstituteBindings::class,
            \App\Http\Middleware\IdentifyTenant::class,
        );

        $middleware->alias([
            'role.any' => \App\Http\Middleware\EnsureUserHasAnyRole::class,
            'permission' => \App\Http\Middleware\EnsureUserHasPermission::class,
            'portal.access' => \App\Http\Middleware\EnsureUserCanAccessPortal::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
