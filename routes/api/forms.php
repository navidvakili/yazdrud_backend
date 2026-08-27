<?php

use App\Http\Controllers\Api\FormController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Authenticated Form Routes
|--------------------------------------------------------------------------
|
| Admin list, detail, CRUD and submissions review for the form builder.
| Public (no-auth) fill-out/submit routes live in routes/api.php instead —
| every file under routes/api/*.php is auto-wrapped in auth:api.
|
*/

Route::group(['middleware' => 'auth:api'], function () {
    Route::get('/forms', [FormController::class, 'index']);
    Route::get('/forms/{id}', [FormController::class, 'show']);
    Route::get('/forms/{id}/submissions', [FormController::class, 'submissions']);
    Route::get('/forms/{id}/share-link', [FormController::class, 'getShareLink']);

    Route::group(['middleware' => 'role_or_permission:admin|editor|support|forms.create|forms.edit|forms.delete'], function () {
        Route::post('/forms', [FormController::class, 'store']);
        Route::put('/forms/{id}', [FormController::class, 'update']);
        Route::delete('/forms/{id}', [FormController::class, 'destroy']);
        Route::post('/forms/{id}/clone', [FormController::class, 'clone']);
        Route::post('/forms/{id}/duplicate', [FormController::class, 'duplicate']);
        Route::put('/forms/{id}/share-link', [FormController::class, 'updateShareLink']);
    });

    // انتشار فرم — فقط برای کاربران دارای مجوز forms.approve (بررسی دقیق‌تر داخل کنترلر)
    Route::group(['middleware' => 'role_or_permission:admin|support|forms.approve'], function () {
        Route::patch('/forms/{id}/status', [FormController::class, 'updateStatus']);
    });
});
