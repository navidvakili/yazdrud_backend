<?php

use App\Http\Controllers\Api\AuthController;
use Illuminate\Support\Facades\Route;

// ==================== Current User ====================
Route::get('/user', [AuthController::class, 'user']);
Route::post('/logout', [AuthController::class, 'logout']);
Route::put('/user/profile', [AuthController::class, 'updateProfile']);
Route::put('/user/password', [AuthController::class, 'updatePassword']);
Route::put('/user/switch-role', [AuthController::class, 'switchRole']);
Route::put('/user/theme', [AuthController::class, 'updateTheme']);
Route::post('/user/verify-password', [AuthController::class, 'verifyPassword']);

// ==================== Navigation & Permissions ====================
Route::get('/navigation', [\App\Http\Controllers\Api\NavigationController::class, 'index']);
Route::get('/user/roles', [\App\Http\Controllers\Api\NavigationController::class, 'roles']);
Route::get('/user/permissions', [\App\Http\Controllers\Api\NavigationController::class, 'permissions']);
