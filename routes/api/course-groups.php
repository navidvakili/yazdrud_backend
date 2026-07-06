<?php

use Illuminate\Support\Facades\Route;

// ==================== Course Groups (گروه‌های آموزشی و کارگاهی) ====================
Route::prefix('course-groups')->group(function () {
    Route::get('/', [\App\Http\Controllers\Api\CourseGroupController::class, 'index']);
    Route::post('/', [\App\Http\Controllers\Api\CourseGroupController::class, 'store']);
    Route::put('/{id}', [\App\Http\Controllers\Api\CourseGroupController::class, 'update']);
    Route::delete('/{id}', [\App\Http\Controllers\Api\CourseGroupController::class, 'destroy']);
});
