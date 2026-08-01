<?php

use App\Http\Controllers\Api\LanguageController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Language Routes (authenticated)
|--------------------------------------------------------------------------
| Only the user with username "support" can manage languages.
| Public listing is served from routes/api.php (publicIndex) — note the
| authenticated GET /languages (index) is intentionally NOT registered here:
| registering it after the public route would shadow it (Laravel matches in
| reverse registration order) and the public endpoint would return 401.
| yazdrud has no admin language-manager UI, so only the support CRUD is needed.
*/

Route::group(['middleware' => 'auth:api'], function () {
    Route::group(['middleware' => 'support.only'], function () {
        Route::post('/languages', [LanguageController::class, 'store']);
        Route::put('/languages/{id}', [LanguageController::class, 'update']);
        Route::delete('/languages/{id}', [LanguageController::class, 'destroy']);
    });
});
