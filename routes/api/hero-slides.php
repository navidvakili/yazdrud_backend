<?php

use App\Http\Controllers\Api\HeroSlideController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Admin Hero Slides Routes
|--------------------------------------------------------------------------
|
| CRUD and reorder for hero slides.
| Only users with hero-slides permissions can manage slides.
|
*/

Route::group(['middleware' => 'role_or_permission:admin|support|hero-slides.view|hero-slides.create|hero-slides.edit|hero-slides.delete'], function () {
    Route::get('/hero-slides', [HeroSlideController::class, 'adminIndex']);
    Route::post('/hero-slides', [HeroSlideController::class, 'store']);
    Route::put('/hero-slides/{id}', [HeroSlideController::class, 'update']);
    Route::delete('/hero-slides/{id}', [HeroSlideController::class, 'destroy']);
    Route::put('/hero-slides/reorder', [HeroSlideController::class, 'reorder']);
});
