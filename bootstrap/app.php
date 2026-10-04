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
        // An upload bigger than PHP's post_max_size.
        //
        // PHP throws this away before the request reaches a controller, so no
        // validation rule can catch it and the admin got a raw Whoops page
        // reading "The POST data is too large." — with no mention of what the
        // limit is or that a video was the cause. Handled first, because the
        // renderer below only speaks JSON and this is a form post.
        $exceptions->render(function (
            \Illuminate\Http\Exceptions\PostTooLargeException $e,
            Request $request
        ) {
            if ($request->expectsJson()) {
                return response()->json([
                    'status'  => false,
                    'message' => 'That upload is too large for the server to accept.',
                ], 413);
            }

            return back()->withInput($request->except('_token'))->with(
                'error',
                'That file is too large to upload. The server accepts up to '
                . \App\Support\UploadLimit::label()
                . ' per request. Compress the video, or raise post_max_size and '
                . 'upload_max_filesize in php.ini and restart the server.'
            );
        });

        $exceptions->render(function (Throwable $e, Request $request) {
            return app(\App\Exceptions\ApiExceptionRenderer::class)->render($e, $request);
        });
    })->create();
