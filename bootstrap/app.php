<?php

use App\Http\Middleware\CountPvAndUv;
use App\Http\Middleware\RecordVisitors;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Support\Facades\Route;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
        then: function (): void {
            Route::group([], __DIR__.'/../routes/health.php');
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Resolve trusted proxy headers and maintenance mode before tracking visitors.
        $middleware->append(RecordVisitors::class);
        $middleware->appendToGroup('web', CountPvAndUv::class);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
