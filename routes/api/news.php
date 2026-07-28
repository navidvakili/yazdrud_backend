<?php

use App\Http\Controllers\Api\NewsController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| News Routes
|--------------------------------------------------------------------------
|
| Public routes: list & show published news
| Authenticated: create, update, delete, pin, like
| Admin: categories management, analytics
|
*/

// ==================== Public News Routes ====================
Route::get('/news', [NewsController::class, 'index']);
Route::get('/news/{id}', [NewsController::class, 'show']);
Route::post('/news/{id}/views', [NewsController::class, 'incrementViews']);

// ==================== Authenticated News Routes ====================
Route::group(['middleware' => 'auth:api'], function () {
    // Like (any authenticated user)
    Route::post('/news/{id}/like', [NewsController::class, 'like']);

    // CRUD — users with role admin/editor/support OR news permissions can manage news
    Route::group(['middleware' => 'role_or_permission:admin|editor|support|news.create|news.edit|news.delete'], function () {
        Route::post('/news', [NewsController::class, 'store']);
        Route::put('/news/{id}', [NewsController::class, 'update']);
        Route::delete('/news/{id}', [NewsController::class, 'destroy']);
        Route::put('/news/{id}/toggle-pin', [NewsController::class, 'togglePin']);
    });

    // Categories list (any authenticated user — needed for filters)
    Route::get('/news-categories', [NewsController::class, 'categories']);

    // Categories management (admin/support only)
    Route::group(['middleware' => 'role_or_permission:admin|support'], function () {
        Route::post('/news-categories', [NewsController::class, 'storeCategory']);
        Route::put('/news-categories/{id}', [NewsController::class, 'updateCategory']);
        Route::delete('/news-categories/{id}', [NewsController::class, 'destroyCategory']);
    });

    // Analytics (admin/support only)
    Route::get('/news-analytics', [NewsController::class, 'analytics'])
        ->middleware('role_or_permission:admin|support');
});
