<?php

use Illuminate\Support\Facades\Route;

// ==================== Voucher Installment Manager (مدیریت تقسیط بن خرید) ====================
Route::prefix('installments')->group(function () {
    // Stats (must come before parameterized routes)
    Route::get('/stats', [\App\Http\Controllers\Api\VoucherInstallmentController::class, 'getStats']);

    // Registrations with installment plans
    Route::get('/registrations', [\App\Http\Controllers\Api\VoucherInstallmentController::class, 'registrations']);
    Route::get('/registrations/{registerId}', [\App\Http\Controllers\Api\VoucherInstallmentController::class, 'show']);

    // Bulk operations
    Route::post('/bulk-update-dates', [\App\Http\Controllers\Api\VoucherInstallmentController::class, 'bulkUpdateDates']);

    // Installment CRUD
    Route::get('/', [\App\Http\Controllers\Api\VoucherInstallmentController::class, 'index']);
    Route::post('/{installmentId}/verify', [\App\Http\Controllers\Api\VoucherInstallmentController::class, 'verifyManual']);
    Route::post('/{installmentId}/revert', [\App\Http\Controllers\Api\VoucherInstallmentController::class, 'revertPayment']);
    Route::put('/{installmentId}', [\App\Http\Controllers\Api\VoucherInstallmentController::class, 'update']);
});
