<?php

use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\AdminAuthenticate;
use App\Http\Middleware\CheckPermission;
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
            HandleInertiaRequests::class,
        ]);

        // Register middleware aliases
        $middleware->alias([
            'admin.auth'  => AdminAuthenticate::class,
            'permission'  => CheckPermission::class,
        ]);

        // Exclude SePay IPN and webhook from CSRF verification
        $middleware->validateCsrfTokens(except: [
            'payment/ipn',
            'api/*',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
