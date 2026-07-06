<?php

use Illuminate\Support\Facades\Route;

// ==================== Coupons / Vouchers (بن خرید و تخفیف) ====================
Route::prefix('coupons')->group(function () {
    Route::get('/', [\App\Http\Controllers\Api\CouponController::class, 'index']);
    Route::post('/', [\App\Http\Controllers\Api\CouponController::class, 'store']);
    Route::get('/generate-code', [\App\Http\Controllers\Api\CouponController::class, 'generateCode']);
    Route::post('/generate', [\App\Http\Controllers\Api\CouponController::class, 'generate']);
    Route::get('/courses', [\App\Http\Controllers\Api\CouponController::class, 'courses']);
    Route::get('/{id}', [\App\Http\Controllers\Api\CouponController::class, 'show']);
    Route::put('/{id}', [\App\Http\Controllers\Api\CouponController::class, 'update']);
    Route::delete('/{id}', [\App\Http\Controllers\Api\CouponController::class, 'destroy']);
});
