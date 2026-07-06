<?php

use Illuminate\Support\Facades\Route;

// ==================== Courses (دوره‌های آموزشی) ====================
Route::prefix('courses')->group(function () {
    Route::get('/', [\App\Http\Controllers\Api\CourseController::class, 'index']);
    Route::post('/', [\App\Http\Controllers\Api\CourseController::class, 'store']);
    Route::get('/statistics', [\App\Http\Controllers\Api\CourseStatisticsController::class, 'statistics']);
    Route::get('/statistics/detailed', [\App\Http\Controllers\Api\CourseStatisticsController::class, 'index']);
    Route::get('/registrations', [\App\Http\Controllers\Api\CourseController::class, 'allRegistrations']);
    Route::get('/registrations/export', [\App\Http\Controllers\Api\CourseController::class, 'exportRegistrations']);
    Route::get('/{id}', [\App\Http\Controllers\Api\CourseController::class, 'show']);
    Route::put('/{id}', [\App\Http\Controllers\Api\CourseController::class, 'update']);
    Route::delete('/{id}', [\App\Http\Controllers\Api\CourseController::class, 'destroy']);
    Route::put('/{id}/toggle-active', [\App\Http\Controllers\Api\CourseController::class, 'toggleActive']);
    Route::get('/{id}/registrations', [\App\Http\Controllers\Api\CourseController::class, 'registrations']);
    Route::post('/registrations/{id}/approve-receipt', [\App\Http\Controllers\Api\CourseController::class, 'approveReceipt']);
    Route::post('/registrations/{id}/reject-receipt', [\App\Http\Controllers\Api\CourseController::class, 'rejectReceipt']);
    Route::post('/registrations/{id}/refund', [\App\Http\Controllers\Api\CourseController::class, 'refundRegistration']);
    Route::post('/registrations/{id}/undo-refund', [\App\Http\Controllers\Api\CourseController::class, 'undoRefundRegistration']);
    Route::put('/registrations/{id}', [\App\Http\Controllers\Api\CourseController::class, 'updateRegistration']);
});
