<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'permission' => \App\Http\Middleware\CheckPermission::class,
            'vendor.active' => \App\Http\Middleware\CheckVendorActive::class,
            'farmer.active' => \App\Http\Middleware\UpdateFarmerLastActive::class,
            'farm.access' => \App\Http\Middleware\EnsureFarmAccess::class,
            'farm.unlocked' => \App\Http\Middleware\EnsureFarmNotLocked::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (Throwable $e, Request $request) {
            return app(\App\Exceptions\ApiExceptionRenderer::class)->render($e, $request);
        });
    })->create();
