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

// Public survey submission (no auth required)
Route::post('/surveys', [\App\Http\Controllers\Api\SurveyController::class, 'store']);

// Public certificate verification (no auth required)
Route::get('/certificates/verify/{encryptedNumber}', [\App\Http\Controllers\Api\CertificateController::class, 'verify'])->name('certificates.verify');

// Public certificate PDF display via token query param (for iframe dialog — no CORS)
Route::get('/certificates/public-view/{registerId}', [\App\Http\Controllers\Api\CertificateController::class, 'publicView']);

// ==================== Authenticated Routes ====================
Route::middleware('auth:api')->group(function () {
    // Current user
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

    // ==================== Dashboard (پین‌ها و داشبورد) ====================
    Route::prefix('dashboard')->group(function () {
        Route::get('/pinned-menus', [\App\Http\Controllers\Api\DashboardController::class, 'pinnedMenus']);
        Route::post('/pin', [\App\Http\Controllers\Api\DashboardController::class, 'pin']);
        Route::post('/unpin', [\App\Http\Controllers\Api\DashboardController::class, 'unpin']);
    });

    // ==================== Courses (دوره‌های آموزشی) ====================
    Route::prefix('courses')->group(function () {
        Route::get('/', [\App\Http\Controllers\Api\CourseController::class, 'index']);
        Route::post('/', [\App\Http\Controllers\Api\CourseController::class, 'store']);
        Route::get('/statistics', [\App\Http\Controllers\Api\CourseStatisticsController::class, 'statistics']);
        Route::get('/statistics/detailed', [\App\Http\Controllers\Api\CourseStatisticsController::class, 'index']);
        Route::get('/registrations', [\App\Http\Controllers\Api\CourseController::class, 'allRegistrations']);
        Route::get('/{id}', [\App\Http\Controllers\Api\CourseController::class, 'show']);
        Route::put('/{id}', [\App\Http\Controllers\Api\CourseController::class, 'update']);
        Route::delete('/{id}', [\App\Http\Controllers\Api\CourseController::class, 'destroy']);
        Route::put('/{id}/toggle-active', [\App\Http\Controllers\Api\CourseController::class, 'toggleActive']);
        Route::get('/{id}/registrations', [\App\Http\Controllers\Api\CourseController::class, 'registrations']);
        Route::post('/registrations/{id}/approve-receipt', [\App\Http\Controllers\Api\CourseController::class, 'approveReceipt']);
        Route::post('/registrations/{id}/reject-receipt', [\App\Http\Controllers\Api\CourseController::class, 'rejectReceipt']);
    });

    // ==================== Surveys (نظرسنجی دوره‌ها) ====================
    Route::prefix('surveys')->group(function () {
        Route::get('/', [\App\Http\Controllers\Api\SurveyController::class, 'index']);
        Route::get('/statistics', [\App\Http\Controllers\Api\SurveyController::class, 'statistics']);
        Route::get('/{id}', [\App\Http\Controllers\Api\SurveyController::class, 'show']);
        Route::delete('/{id}', [\App\Http\Controllers\Api\SurveyController::class, 'destroy']);
    });

    // ==================== Coupons (بن تخفیف) ====================
    Route::prefix('coupons')->group(function () {
        Route::get('/', [\App\Http\Controllers\Api\CouponController::class, 'index']);
        Route::post('/', [\App\Http\Controllers\Api\CouponController::class, 'store']);
        Route::post('/validate', [\App\Http\Controllers\Api\CouponController::class, 'validate']);
        Route::get('/{id}', [\App\Http\Controllers\Api\CouponController::class, 'show']);
        Route::put('/{id}', [\App\Http\Controllers\Api\CouponController::class, 'update']);
        Route::delete('/{id}', [\App\Http\Controllers\Api\CouponController::class, 'destroy']);
    });

    // ==================== Course Groups (گروه‌های آموزشی و کارگاهی) ====================
    Route::prefix('course-groups')->group(function () {
        Route::get('/', [\App\Http\Controllers\Api\CourseGroupController::class, 'index']);
        Route::post('/', [\App\Http\Controllers\Api\CourseGroupController::class, 'store']);
        Route::put('/{id}', [\App\Http\Controllers\Api\CourseGroupController::class, 'update']);
        Route::delete('/{id}', [\App\Http\Controllers\Api\CourseGroupController::class, 'destroy']);
    });

    // ==================== Certificates (صدور گواهی دوره‌ها) ====================
    Route::prefix('certificates')->group(function () {
        // Static routes MUST come before parameterized routes
        Route::get('/', [\App\Http\Controllers\Api\CertificateController::class, 'index']);
        Route::post('/approve-all', [\App\Http\Controllers\Api\CertificateController::class, 'approveAll']);
        Route::get('/download-all', [\App\Http\Controllers\Api\CertificateController::class, 'downloadAll']);
        Route::post('/approve/{registerId}', [\App\Http\Controllers\Api\CertificateController::class, 'approve']);
        Route::post('/reject/{registerId}', [\App\Http\Controllers\Api\CertificateController::class, 'reject']);
    });
});
