<?php

use Illuminate\Support\Facades\Route;

// ==================== Dashboard (پین‌ها و داشبورد) ====================
Route::prefix('dashboard')->group(function () {
    Route::get('/pinned-menus', [\App\Http\Controllers\Api\DashboardController::class, 'pinnedMenus']);
    Route::post('/pin', [\App\Http\Controllers\Api\DashboardController::class, 'pin']);
    Route::post('/unpin', [\App\Http\Controllers\Api\DashboardController::class, 'unpin']);
});
