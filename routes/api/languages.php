<?php

use App\Http\Controllers\Api\LanguageController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Language Routes (authenticated)
|--------------------------------------------------------------------------
| Only the user with username "support" can manage languages.
| Public endpoints live under /api/v1/... in routes/api.php (publicIndex /
| publicLocale) — the v1 prefix keeps them out of the way of these
| authenticated routes, because Laravel matches routes in reverse
| registration order and these auth'd routes are registered last.
*/

Route::group(['middleware' => 'auth:api'], function () {
    // List all languages (any authenticated user — needed to show the selector)
    Route::get('/languages', [LanguageController::class, 'index']);

    // CRUD + locale editing — ONLY the user with username "support"
    // (admin and any other user are explicitly NOT allowed)
    Route::group(['middleware' => 'support.only'], function () {
        Route::post('/languages', [LanguageController::class, 'store']);
        Route::put('/languages/{id}', [LanguageController::class, 'update']);
        Route::delete('/languages/{id}', [LanguageController::class, 'destroy']);
        Route::get('/languages/{code}/locale', [LanguageController::class, 'locale']);
        Route::put('/languages/{code}/locale', [LanguageController::class, 'updateLocale']);
    });
});
