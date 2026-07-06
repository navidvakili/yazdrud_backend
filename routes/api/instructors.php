<?php

use Illuminate\Support\Facades\Route;

// ==================== Instructors (اساتید دوره‌ها) ====================
Route::prefix('instructors')->group(function () {
    Route::get('/', [\App\Http\Controllers\Api\InstructorController::class, 'index']);
    Route::post('/', [\App\Http\Controllers\Api\InstructorController::class, 'store']);
    Route::get('/{id}', [\App\Http\Controllers\Api\InstructorController::class, 'show']);
    Route::put('/{id}', [\App\Http\Controllers\Api\InstructorController::class, 'update']);
    Route::delete('/{id}', [\App\Http\Controllers\Api\InstructorController::class, 'destroy']);
});
