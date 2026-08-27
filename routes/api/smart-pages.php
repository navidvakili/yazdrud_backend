<?php

use App\Http\Controllers\Api\SmartPageController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Authenticated Smart Page Routes
|--------------------------------------------------------------------------
|
| Admin list, detail and CRUD for smart pages
|
*/

// ==================== Authenticated Smart Page Routes ====================
Route::group(['middleware' => 'auth:api'], function () {
    // List / detail (any authenticated user)
    Route::get('/smart-pages', [SmartPageController::class, 'index']);
    // Children of a page (for the canvas child-pages widget / admin)
    Route::get('/smart-pages/{id}/children', [SmartPageController::class, 'children']);
    // Full descendant TREE (recursive) — for the child-pages manager dialog
    Route::get('/smart-pages/{id}/children/tree', [SmartPageController::class, 'childrenTree']);
    Route::get('/smart-pages/{id}', [SmartPageController::class, 'show']);

    // CRUD — users with role admin/editor/support OR page-builder permissions can manage
    Route::group(['middleware' => 'role_or_permission:admin|editor|support|page-builder.create|page-builder.edit|page-builder.delete'], function () {
        Route::post('/smart-pages', [SmartPageController::class, 'store']);
        Route::post('/smart-pages/{id}/duplicate', [SmartPageController::class, 'duplicate']);
        Route::put('/smart-pages/{id}', [SmartPageController::class, 'update']);
        Route::delete('/smart-pages/{id}', [SmartPageController::class, 'destroy']);
    });
});
