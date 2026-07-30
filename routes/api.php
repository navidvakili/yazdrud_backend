<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\NewsCommentController;
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

// Password Reset (Email)
Route::post('/forgot-password', [AuthController::class, 'forgotPassword']);
Route::post('/reset-password', [AuthController::class, 'resetPassword']);

// Password Reset (SMS)
Route::post('/send-sms-code', [AuthController::class, 'sendSmsCode']);
Route::post('/verify-sms-code', [AuthController::class, 'verifySmsCode']);

// ==================== Session Warnings (Concurrent Login — unauthenticated endpoints) ====================
Route::post('/session-warnings', [\App\Http\Controllers\Api\SessionWarningController::class, 'store']);
Route::get('/session-warnings/{id}/status', [\App\Http\Controllers\Api\SessionWarningController::class, 'status']);
Route::post('/session-warnings/login', [\App\Http\Controllers\Api\SessionWarningController::class, 'login']);

// ==================== Public News Routes (no auth required) ====================
Route::get('/news', [\App\Http\Controllers\Api\NewsController::class, 'index']);
Route::get('/news/{id}', [\App\Http\Controllers\Api\NewsController::class, 'show']);
Route::post('/news/{id}/views', [\App\Http\Controllers\Api\NewsController::class, 'incrementViews']);
Route::get('/news/{id}/comments', [\App\Http\Controllers\Api\NewsCommentController::class, 'index']);
Route::post('/news/{id}/comments', [\App\Http\Controllers\Api\NewsCommentController::class, 'store']);

// ==================== Public County Projects Routes (no auth required) ====================
Route::get('/county-projects', [\App\Http\Controllers\Api\CountyProjectController::class, 'index']);
Route::get('/county-projects/{countyId}', [\App\Http\Controllers\Api\CountyProjectController::class, 'show']);

// ==================== Public Slider Studio Routes (no auth required) ====================
Route::get('/slider-studio/public', [\App\Http\Controllers\Api\SliderProjectController::class, 'publicIndex']);

// ==================== Media Upload (outside auth:api to avoid Passport PSR-7 file upload bug) ====================
Route::post('/media/upload', [\App\Http\Controllers\Api\MediaController::class, 'upload']);

// ==================== Authenticated Routes ====================
Route::group(['middleware' => 'auth:api'], function () {
    foreach (glob(__DIR__ . '/api/*.php') as $file_name) {
        include_once $file_name;
    }
});
