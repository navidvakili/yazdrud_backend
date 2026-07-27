<?php

use App\Http\Controllers\Api\PermissionController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Permission & Role Management Routes (Admin & Support)
|--------------------------------------------------------------------------
*/

Route::prefix('admin')->middleware('role_or_permission:admin|support')->group(function () {
    // Permissions
    Route::get('/permissions', [PermissionController::class, 'index']);

    // Roles
    Route::get('/roles', [PermissionController::class, 'roles']);
    Route::post('/roles', [PermissionController::class, 'storeRole']);
    Route::put('/roles/{id}', [PermissionController::class, 'updateRole']);
    Route::delete('/roles/{id}', [PermissionController::class, 'destroyRole']);

    // User role assignment
    Route::post('/users/assign-role', [PermissionController::class, 'assignRole']);
    Route::post('/users/remove-role', [PermissionController::class, 'removeRole']);
    Route::get('/users/{username}/permissions', [PermissionController::class, 'userPermissions']);
});
