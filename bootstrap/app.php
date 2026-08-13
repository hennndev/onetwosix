<?php

use App\Http\Middleware\CheckAdminRole;
use App\Http\Middleware\EnsureDatabaseIsSelected;
use App\Http\Middleware\EnsureWaiterRole;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [
            \App\Http\Middleware\EnsureActiveAreaMiddleware::class,
        ]);

        $middleware->alias([
            'database_selected' => EnsureDatabaseIsSelected::class,
            'ensure_waiter' => EnsureWaiterRole::class,
            'check.admin.role' => CheckAdminRole::class,
            'ensure_active_area' => \App\Http\Middleware\EnsureActiveAreaMiddleware::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
