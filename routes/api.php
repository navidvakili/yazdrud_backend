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

// ==================== Session Warnings (Concurrent Login — unauthenticated endpoints) ====================
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
Route::group(['middleware' => 'auth:api'], function () {
    foreach (glob(__DIR__ . '/api/*.php') as $file_name) {
        include_once $file_name;
    }
});
