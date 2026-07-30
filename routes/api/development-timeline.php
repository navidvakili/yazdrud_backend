<?php

use App\Http\Controllers\Api\DevelopmentTimelineController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Development Timeline Routes — روند توسعه و تحول عمران شهری و جاده‌ای
|--------------------------------------------------------------------------
|
| Admin: CRUD for managing development timeline items
|
| Public routes are defined in routes/api.php
|
*/

// ==================== Admin Routes ====================
Route::prefix('admin')->middleware('role_or_permission:admin|support|development-timeline.view|development-timeline.edit')->group(function () {
    Route::get('/development-timeline', [DevelopmentTimelineController::class, 'index']);
    Route::post('/development-timeline', [DevelopmentTimelineController::class, 'store']);
    Route::get('/development-timeline/{id}', [DevelopmentTimelineController::class, 'show']);
    Route::put('/development-timeline/{id}', [DevelopmentTimelineController::class, 'update']);
    Route::delete('/development-timeline/{id}', [DevelopmentTimelineController::class, 'destroy']);
});
