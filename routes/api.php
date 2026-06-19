<?php

use App\Http\Controllers\Api\AuthController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Enjoy building your API!
|
*/

// ==================== Public Routes ====================
Route::post('/login', [AuthController::class, 'login']);
Route::post('/register', [AuthController::class, 'register']);

// Password Reset
Route::post('/forgot-password', [AuthController::class, 'forgotPassword']);
Route::post('/reset-password', [AuthController::class, 'resetPassword']);

// ==================== Authenticated Routes ====================
Route::middleware('auth:api')->group(function () {
    // Current user
    Route::get('/user', [AuthController::class, 'user']);
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::put('/user/profile', [AuthController::class, 'updateProfile']);
    Route::put('/user/password', [AuthController::class, 'updatePassword']);
    Route::put('/user/switch-role', [AuthController::class, 'switchRole']);

    // ==================== Navigation & Permissions ====================
    Route::get('/navigation', [\App\Http\Controllers\Api\NavigationController::class, 'index']);
    Route::get('/user/roles', [\App\Http\Controllers\Api\NavigationController::class, 'roles']);
    Route::get('/user/permissions', [\App\Http\Controllers\Api\NavigationController::class, 'permissions']);
});
