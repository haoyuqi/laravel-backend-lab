<?php

use App\Http\Controllers\IndexController;
use App\Http\Controllers\QueueController;
use App\Http\Controllers\SortController;
use App\Http\Middleware\EnsureDemoEnabled;
use Illuminate\Support\Facades\Route;

Route::get('/', [IndexController::class, 'index']);
Route::get('/error', [IndexController::class, 'error']);
Route::get('/test', [IndexController::class, 'test']);
Route::get('/time', [IndexController::class, 'time']);

Route::middleware([EnsureDemoEnabled::class, 'throttle:demo'])->group(function () {
    Route::post('/queue/create', [QueueController::class, 'create']);

    Route::prefix('sort')->group(function () {
        Route::get('/bubble', [SortController::class, 'bubbleSort']);
        Route::get('/quick', [SortController::class, 'quickSort']);
        Route::get('/select', [SortController::class, 'selectSort']);
        Route::get('/insert', [SortController::class, 'insertSort']);
    });
});
