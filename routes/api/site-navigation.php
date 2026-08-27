<?php

use App\Http\Controllers\Api\SiteNavigationController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Admin Site Navigation Routes (Navigation Builder)
|--------------------------------------------------------------------------
|
| CRUD for the menus of the public site theme. Only users with the
| navigation permission (admin/support/editor or navigation.*) can manage.
|
| NOTE: /site-navigation/location/{location} MUST be registered before
| /site-navigation/{id} so the "location" literal isn't captured as an id.
|
*/

Route::group(['middleware' => 'role_or_permission:admin|support|editor|navigation.view|navigation.create|navigation.edit|navigation.delete'], function () {
    Route::get('/site-navigation/location/{location}', [SiteNavigationController::class, 'byLocation']);
    Route::get('/site-navigation', [SiteNavigationController::class, 'index']);
    Route::get('/site-navigation/{id}', [SiteNavigationController::class, 'show']);
    Route::post('/site-navigation', [SiteNavigationController::class, 'store']);
    Route::put('/site-navigation/{id}', [SiteNavigationController::class, 'update']);
    Route::post('/site-navigation/{id}/publish', [SiteNavigationController::class, 'publish']);
    Route::delete('/site-navigation/{id}', [SiteNavigationController::class, 'destroy']);
});
