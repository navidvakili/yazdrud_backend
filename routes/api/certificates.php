<?php

use Illuminate\Support\Facades\Route;

// ==================== Certificates (صدور گواهی دوره‌ها) ====================
Route::prefix('certificates')->group(function () {
    // Static routes MUST come before parameterized routes
    Route::get('/', [\App\Http\Controllers\Api\CertificateController::class, 'index']);
    Route::post('/approve-all', [\App\Http\Controllers\Api\CertificateController::class, 'approveAll']);
    Route::get('/download-all', [\App\Http\Controllers\Api\CertificateController::class, 'downloadAll']);
    Route::post('/approve/{registerId}', [\App\Http\Controllers\Api\CertificateController::class, 'approve']);
    Route::post('/reject/{registerId}', [\App\Http\Controllers\Api\CertificateController::class, 'reject']);
});
