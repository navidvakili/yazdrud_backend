<?php

use Illuminate\Support\Facades\Route;

// ==================== Session Warnings (Concurrent Login) ====================
Route::get('/session-warnings/pending', [\App\Http\Controllers\Api\SessionWarningController::class, 'pending']);
Route::post('/session-warnings/{id}/respond', [\App\Http\Controllers\Api\SessionWarningController::class, 'respond']);

// ==================== Active Sessions Management ====================
Route::prefix('user/sessions')->group(function () {
    Route::get('/', [\App\Http\Controllers\Api\SessionController::class, 'index']);
    Route::post('/{tokenId}/revoke', [\App\Http\Controllers\Api\SessionController::class, 'revoke']);
});

// ==================== Admin: All Active Sessions ====================
Route::prefix('admin/sessions')->group(function () {
    Route::get('/', [\App\Http\Controllers\Api\SessionController::class, 'adminIndex']);
    Route::post('/{tokenId}/revoke', [\App\Http\Controllers\Api\SessionController::class, 'adminRevoke']);
});
