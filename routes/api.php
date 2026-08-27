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

// ==================== Public Development Timeline Routes (no auth required) ====================
Route::get('/development-timeline', [\App\Http\Controllers\Api\DevelopmentTimelineController::class, 'publicIndex']);

// ==================== Public Languages Routes (no auth required) ====================
// The v1 prefix keeps these out of the way of the authenticated /languages
// routes (routes/api/languages.php) — Laravel matches in reverse registration
// order, and the auth'd routes are registered after these.
Route::prefix('v1')->group(function () {
    Route::get('/languages', [\App\Http\Controllers\Api\LanguageController::class, 'publicIndex']);
    // Runtime translations JSON — lets the public site reflect locale-editor
    // changes on an already-built site without rebuilding.
    Route::get('/languages/{code}/locale', [\App\Http\Controllers\Api\LanguageController::class, 'publicLocale']);
});

// ==================== Media Upload (outside auth:api to avoid Passport PSR-7 file upload bug) ====================
Route::post('/media/upload', [\App\Http\Controllers\Api\MediaController::class, 'upload']);

// Public media stream — same file Apache serves at /storage/..., but routed
// through the API so the global CORS middleware adds Access-Control-Allow-*
// headers (EmbedPDF fetches the PDF from the browser, which enforces CORS).
Route::get('/media/{id}/stream', [\App\Http\Controllers\Api\MediaController::class, 'stream']);

// ==================== Public Media Listing (no auth required) ====================
// For public smart-page widgets (file-manager / gallery). Files live in public
// storage and are already reachable without auth, so listing them is safe.
Route::get('/media/public', [\App\Http\Controllers\Api\MediaController::class, 'publicIndex']);
Route::prefix('v1')->group(function () {
    Route::get('/media/public', [\App\Http\Controllers\Api\MediaController::class, 'publicIndex']);
});

// ==================== Public Form Routes (no auth required) ====================
// Rate-limited since submission/upload are unauthenticated write endpoints.
Route::get('/forms/public', [\App\Http\Controllers\Api\FormController::class, 'publicIndex']);
Route::get('/forms/slug/{slug}/public', [\App\Http\Controllers\Api\FormController::class, 'publicShowBySlug']);
Route::get('/forms/slug/{slug}/embed', [\App\Http\Controllers\Api\FormController::class, 'publicShowBySlugForEmbed']);
Route::get('/forms/share/{slug}/public', [\App\Http\Controllers\Api\FormController::class, 'publicShowByShareSlug']);
Route::post('/forms/share/{slug}/unlock', [\App\Http\Controllers\Api\FormController::class, 'unlockShareLink'])
    ->middleware('throttle:8,1');
Route::post('/forms/{id}/submit', [\App\Http\Controllers\Api\FormController::class, 'submit'])
    ->whereNumber('id')->middleware('throttle:10,1');
Route::post('/forms/{id}/upload-answer-file', [\App\Http\Controllers\Api\FormController::class, 'uploadAnswerFile'])
    ->whereNumber('id')->middleware('throttle:20,1');
// «فیلد امنیتی» (security field) — تولید/بررسی چالش کپچا برای فرم‌ساز
Route::post('/forms/security-challenge/generate', [\App\Http\Controllers\Api\SecurityChallengeController::class, 'generate'])
    ->middleware('throttle:30,1');
Route::post('/forms/security-challenge/verify', [\App\Http\Controllers\Api\SecurityChallengeController::class, 'verify'])
    ->middleware('throttle:30,1');

// ==================== Public Site Navigation Routes (no auth required) ====================
// Menus of the public site theme (Navigation Builder) — separate from the
// admin sidebar's NavigationController. Single aggregate call + per-location call.
Route::get('/navigation/public', [\App\Http\Controllers\Api\SiteNavigationController::class, 'publicIndex']);
Route::get('/navigation/{location}/public', [\App\Http\Controllers\Api\SiteNavigationController::class, 'publicByLocation']);

// ==================== Public Smart Page Routes (no auth required) ====================
// Order matters: literal /smart-pages/slug/... and /.../children/public MUST be
// registered before the generic /smart-pages/{parentSlug}/{childKey}/public.
Route::get('/smart-pages/public', [\App\Http\Controllers\Api\SmartPageController::class, 'publicIndex']);
Route::get('/smart-pages/slug/{slug}/public', [\App\Http\Controllers\Api\SmartPageController::class, 'publicShowBySlug']);
Route::get('/smart-pages/{parentSlug}/children/public', [\App\Http\Controllers\Api\SmartPageController::class, 'publicChildren']);
Route::get('/smart-pages/{parentSlug}/{childKey}/public', [\App\Http\Controllers\Api\SmartPageController::class, 'publicShowChild']);

// ==================== Authenticated Routes ====================
// idle.timeout MUST come after auth:api — it needs $request->user()->token()
// already resolved by Passport's guard.
Route::group(['middleware' => ['auth:api', 'idle.timeout']], function () {
    foreach (glob(__DIR__ . '/api/*.php') as $file_name) {
        include_once $file_name;
    }
});
