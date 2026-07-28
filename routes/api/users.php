<?php

use App\Http\Controllers\Api\UserController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| User Management Routes (admin & support)
|--------------------------------------------------------------------------
*/

// Read-only user endpoints — users with users.view can view but not edit
Route::prefix('admin')->middleware('role_or_permission:admin|support|users.view')->group(function () {
    Route::get('/users', [UserController::class, 'index']);
    Route::get('/users/roles', [UserController::class, 'roles']);
    Route::get('/users/{username}', [UserController::class, 'show']);
});

// Write operations — users with create/edit/delete permissions can write
Route::prefix('admin')->middleware('role_or_permission:admin|support|users.create|users.edit|users.delete')->group(function () {
    Route::post('/users', [UserController::class, 'store']);
    Route::put('/users/{username}/toggle-active', [UserController::class, 'toggleActive']);
    Route::put('/users/{username}/password', [UserController::class, 'updatePassword']);
    Route::put('/users/{username}', [UserController::class, 'update']);
    Route::delete('/users/{username}', [UserController::class, 'destroy']);
});
