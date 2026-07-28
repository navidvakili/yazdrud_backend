<?php

use App\Http\Controllers\Api\PermissionController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Permission & Role Management Routes (Admin & Support)
|--------------------------------------------------------------------------
*/

// Read-only role/permission endpoints — users with roles.view can view but not edit
Route::prefix('admin')->middleware('role_or_permission:admin|support|roles.view')->group(function () {
    Route::get('/permissions', [PermissionController::class, 'index']);
    Route::get('/roles', [PermissionController::class, 'roles']);
    Route::get('/users/{username}/permissions', [PermissionController::class, 'userPermissions']);
});

// Write operations — admin/support only
Route::prefix('admin')->middleware('role_or_permission:admin|support')->group(function () {
    Route::post('/roles', [PermissionController::class, 'storeRole']);
    Route::put('/roles/{id}', [PermissionController::class, 'updateRole']);
    Route::delete('/roles/{id}', [PermissionController::class, 'destroyRole']);
    Route::post('/users/assign-role', [PermissionController::class, 'assignRole']);
    Route::post('/users/remove-role', [PermissionController::class, 'removeRole']);
});
