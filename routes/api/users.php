<?php

use App\Http\Controllers\Api\UserController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| User Management Routes (admin & support)
|--------------------------------------------------------------------------
*/

Route::prefix('admin')->middleware('role_or_permission:admin|support')->group(function () {
    // Users CRUD
    Route::get('/users', [UserController::class, 'index']);
    Route::get('/users/roles', [UserController::class, 'roles']);
    Route::post('/users', [UserController::class, 'store']);
    Route::get('/users/{username}', [UserController::class, 'show']);
    Route::put('/users/{username}', [UserController::class, 'update']);
    Route::put('/users/{username}/password', [UserController::class, 'updatePassword']);
    Route::delete('/users/{username}', [UserController::class, 'destroy']);

    // Role assignment
    Route::post('/users/assign-role', [UserController::class, 'assignRole']);
    Route::post('/users/remove-role', [UserController::class, 'removeRole']);
});
