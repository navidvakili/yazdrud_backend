<?php

use Illuminate\Support\Facades\Route;

// ==================== Surveys (نظرسنجی دوره‌ها) ====================
Route::prefix('surveys')->group(function () {
    Route::get('/', [\App\Http\Controllers\Api\SurveyController::class, 'index']);
    Route::get('/statistics', [\App\Http\Controllers\Api\SurveyController::class, 'statistics']);
    Route::get('/export', [\App\Http\Controllers\Api\SurveyController::class, 'export']);
    Route::get('/{id}', [\App\Http\Controllers\Api\SurveyController::class, 'show']);
    Route::delete('/{id}', [\App\Http\Controllers\Api\SurveyController::class, 'destroy']);
});
