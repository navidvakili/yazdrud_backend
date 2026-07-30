<?php

use App\Http\Controllers\Api\SliderProjectController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Admin Slider Studio Routes
|--------------------------------------------------------------------------
|
| CRUD for slider projects.
| Only users with slider-studio permissions can manage projects.
|
*/

Route::prefix('admin')->middleware('role_or_permission:admin|support|slider-studio.view|slider-studio.create|slider-studio.edit|slider-studio.delete')->group(function () {
    Route::get('/slider-studio/current', [SliderProjectController::class, 'current']);
    Route::get('/slider-studio', [SliderProjectController::class, 'index']);
    Route::get('/slider-studio/{id}', [SliderProjectController::class, 'show']);
    Route::post('/slider-studio', [SliderProjectController::class, 'store']);
    Route::put('/slider-studio/{id}', [SliderProjectController::class, 'update']);
    Route::delete('/slider-studio/{id}', [SliderProjectController::class, 'destroy']);
    Route::put('/slider-studio/{id}/toggle-active', [SliderProjectController::class, 'toggleActive']);
});
