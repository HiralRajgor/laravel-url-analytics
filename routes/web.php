<?php

use App\Http\Controllers\Api\V1\RedirectController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| The redirect endpoint lives in web routes so it gets the fast response
| cycle without the API prefix overhead.
|
*/

// The redirect endpoint — rate limited, catches /{shortCode}
Route::get('/{shortCode}', RedirectController::class)
    ->middleware('throttle:redirect')
    ->name('redirect')
    ->where('shortCode', '[a-zA-Z0-9_-]+');
