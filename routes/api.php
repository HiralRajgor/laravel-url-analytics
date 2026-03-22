<?php

use App\Http\Controllers\Api\V1\HealthCheckController;
use App\Http\Controllers\Api\V1\UrlController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Route naming convention: urls.{action}
| All resource routes are versioned under /api/v1
|
*/

// ─── Health check (no rate limiting — ops must always reach this) ──────────
Route::get('/health', HealthCheckController::class)->name('health');

// ─── V1 URL API ────────────────────────────────────────────────────────────
Route::prefix('v1')->name('v1.')->group(function () {

    // Shorten a URL — rate limited separately (stricter)
    Route::post('/urls', [UrlController::class, 'store'])
        ->middleware('throttle:shorten')
        ->name('urls.store');

    // List URLs — optional auth scopes results to the authenticated user
    Route::get('/urls', [UrlController::class, 'index'])
        ->middleware('auth:sanctum')
        ->name('urls.index');

    // Url detail — public (short_code is the implicit route model key)
    Route::get('/urls/{url:short_code}', [UrlController::class, 'show'])
        ->name('urls.show');

    // Stats — public (see UrlPolicy::viewStats to restrict)
    Route::get('/urls/{url:short_code}/stats', [UrlController::class, 'stats'])
        ->name('urls.stats');

    // Mutations — require authentication
    Route::middleware('auth:sanctum')->group(function () {
        Route::patch('/urls/{url:short_code}', [UrlController::class, 'update'])
            ->name('urls.update');

        Route::delete('/urls/{url:short_code}', [UrlController::class, 'destroy'])
            ->name('urls.destroy');
    });
});
