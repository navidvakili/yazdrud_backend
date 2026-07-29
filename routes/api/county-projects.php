<?php

use App\Http\Controllers\Api\CountyProjectController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| County Projects (Admin) Routes — نقشه پروژه‌های عمرانی
|--------------------------------------------------------------------------
|
| Admin: CRUD for managing county project data
|
| Public routes are defined in routes/api.php
|
*/

// ==================== Admin Routes ====================
Route::prefix('admin')->middleware('role_or_permission:admin|support|county-projects.view|county-projects.edit')->group(function () {
    Route::get('/county-projects', [CountyProjectController::class, 'adminIndex']);
    Route::put('/county-projects/batch', [CountyProjectController::class, 'updateBatch']);
    Route::put('/county-projects/{id}', [CountyProjectController::class, 'update']);
});
