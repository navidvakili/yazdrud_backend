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

// Password Reset (Email)
Route::post('/forgot-password', [AuthController::class, 'forgotPassword']);
Route::post('/reset-password', [AuthController::class, 'resetPassword']);

// Password Reset (SMS)
Route::post('/send-sms-code', [AuthController::class, 'sendSmsCode']);
Route::post('/verify-sms-code', [AuthController::class, 'verifySmsCode']);

// Public survey submission (no auth required)
Route::post('/surveys', [\App\Http\Controllers\Api\SurveyController::class, 'store']);

// Public certificate verification (no auth required)
Route::get('/certificates/verify/{encryptedNumber}', [\App\Http\Controllers\Api\CertificateController::class, 'verify'])->name('certificates.verify');

// Public certificate PDF display via token query param (for iframe dialog — no CORS)
Route::get('/certificates/public-view/{registerId}', [\App\Http\Controllers\Api\CertificateController::class, 'publicView']);

// ==================== Session Warnings (Concurrent Login) ====================
Route::post('/session-warnings', [\App\Http\Controllers\Api\SessionWarningController::class, 'store']);
Route::get('/session-warnings/{id}/status', [\App\Http\Controllers\Api\SessionWarningController::class, 'status']);
Route::post('/session-warnings/login', [\App\Http\Controllers\Api\SessionWarningController::class, 'login']);

// ==================== Public Courses API (for frontend website) ====================
// NOTE: Uses /public prefix to avoid conflict with admin CourseController
Route::get('/public/courses/featured', [\App\Http\Controllers\Api\PublicCourseController::class, 'featured']);
Route::get('/public/courses/pre-register', [\App\Http\Controllers\Api\PublicCourseController::class, 'preRegister']);
Route::get('/public/courses/free', [\App\Http\Controllers\Api\PublicCourseController::class, 'free']);
Route::get('/public/courses', [\App\Http\Controllers\Api\PublicCourseController::class, 'index']);
Route::get('/public/courses/{id}', [\App\Http\Controllers\Api\PublicCourseController::class, 'show']);

// ==================== Public Site Stats ====================
Route::get('/public/stats', [\App\Http\Controllers\Api\PublicCourseController::class, 'stats']);

// ==================== Public Course Groups ====================
Route::get('/public/course-groups', [\App\Http\Controllers\Api\CourseGroupController::class, 'index']);

// ==================== Public Learner Club (باشگاه فراگیران) ====================
Route::get('/public/learner-club/lookup', [\App\Http\Controllers\Api\LearnerClubController::class, 'lookup']);

// ==================== Public Registrations API (for frontend website) ====================
// NOTE: lookup MUST come before {id} to avoid route conflict
Route::get('/registrations', [\App\Http\Controllers\Api\RegistrationController::class, 'index']);
Route::post('/registrations', [\App\Http\Controllers\Api\RegistrationController::class, 'store']);
Route::any('/registrations/verify', [\App\Http\Controllers\Api\RegistrationController::class, 'verify']);
Route::get('/registrations/lookup', [\App\Http\Controllers\Api\RegistrationController::class, 'lookup']);
Route::get('/registrations/lookup-by-enrollment-code/{code}', [\App\Http\Controllers\Api\RegistrationController::class, 'lookupByEnrollmentCode']);
Route::get('/registrations/{id}', [\App\Http\Controllers\Api\RegistrationController::class, 'show']);
Route::put('/registrations/{id}/status', [\App\Http\Controllers\Api\RegistrationController::class, 'updateStatus']);
Route::delete('/registrations/{id}', [\App\Http\Controllers\Api\RegistrationController::class, 'destroy']);

// ==================== Public Coupon Validation (used by registration form) ====================
Route::post('/coupons/validate', [\App\Http\Controllers\Api\CouponController::class, 'validate']);

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

    // ==================== Session Warnings (Concurrent Login) ====================
    Route::get('/session-warnings/pending', [\App\Http\Controllers\Api\SessionWarningController::class, 'pending']);
    Route::post('/session-warnings/{id}/respond', [\App\Http\Controllers\Api\SessionWarningController::class, 'respond']);

    // ==================== Active Sessions Management ====================
    Route::prefix('user/sessions')->group(function () {
        Route::get('/', [\App\Http\Controllers\Api\SessionController::class, 'index']);
        Route::post('/{tokenId}/revoke', [\App\Http\Controllers\Api\SessionController::class, 'revoke']);
    });

    // ==================== Admin: All Active Sessions ====================
    Route::prefix('admin/sessions')->group(function () {
        Route::get('/', [\App\Http\Controllers\Api\SessionController::class, 'adminIndex']);
        Route::post('/{tokenId}/revoke', [\App\Http\Controllers\Api\SessionController::class, 'adminRevoke']);
    });

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
        Route::get('/registrations/export', [\App\Http\Controllers\Api\CourseController::class, 'exportRegistrations']);
        Route::get('/{id}', [\App\Http\Controllers\Api\CourseController::class, 'show']);
        Route::put('/{id}', [\App\Http\Controllers\Api\CourseController::class, 'update']);
        Route::delete('/{id}', [\App\Http\Controllers\Api\CourseController::class, 'destroy']);
        Route::put('/{id}/toggle-active', [\App\Http\Controllers\Api\CourseController::class, 'toggleActive']);
        Route::get('/{id}/registrations', [\App\Http\Controllers\Api\CourseController::class, 'registrations']);
        Route::post('/registrations/{id}/approve-receipt', [\App\Http\Controllers\Api\CourseController::class, 'approveReceipt']);
        Route::post('/registrations/{id}/reject-receipt', [\App\Http\Controllers\Api\CourseController::class, 'rejectReceipt']);
        Route::post('/registrations/{id}/refund', [\App\Http\Controllers\Api\CourseController::class, 'refundRegistration']);
        Route::post('/registrations/{id}/undo-refund', [\App\Http\Controllers\Api\CourseController::class, 'undoRefundRegistration']);
    });

    // ==================== Surveys (نظرسنجی دوره‌ها) ====================
    Route::prefix('surveys')->group(function () {
        Route::get('/', [\App\Http\Controllers\Api\SurveyController::class, 'index']);
        Route::get('/statistics', [\App\Http\Controllers\Api\SurveyController::class, 'statistics']);
        Route::get('/export', [\App\Http\Controllers\Api\SurveyController::class, 'export']);
        Route::get('/{id}', [\App\Http\Controllers\Api\SurveyController::class, 'show']);
        Route::delete('/{id}', [\App\Http\Controllers\Api\SurveyController::class, 'destroy']);
    });

    // ==================== Coupons / Vouchers (بن خرید و تخفیف) ====================
    Route::prefix('coupons')->group(function () {
        Route::get('/', [\App\Http\Controllers\Api\CouponController::class, 'index']);
        Route::post('/', [\App\Http\Controllers\Api\CouponController::class, 'store']);
        Route::get('/generate-code', [\App\Http\Controllers\Api\CouponController::class, 'generateCode']);
        Route::post('/generate', [\App\Http\Controllers\Api\CouponController::class, 'generate']);
        Route::get('/courses', [\App\Http\Controllers\Api\CouponController::class, 'courses']);
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

    // ==================== Instructors (اساتید دوره‌ها) ====================
    Route::prefix('instructors')->group(function () {
        Route::get('/', [\App\Http\Controllers\Api\InstructorController::class, 'index']);
        Route::post('/', [\App\Http\Controllers\Api\InstructorController::class, 'store']);
        Route::get('/{id}', [\App\Http\Controllers\Api\InstructorController::class, 'show']);
        Route::put('/{id}', [\App\Http\Controllers\Api\InstructorController::class, 'update']);
        Route::delete('/{id}', [\App\Http\Controllers\Api\InstructorController::class, 'destroy']);
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
