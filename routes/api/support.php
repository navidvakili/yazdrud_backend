<?php

use App\Http\Controllers\Api\SupportController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Support Routes (support user only)
|--------------------------------------------------------------------------
*/

Route::prefix('admin')->middleware('role:support')->group(function () {
    // Impersonate a user — get a token for the target user
    Route::post('/support/impersonate', [SupportController::class, 'impersonate']);

    // End impersonation — revoke impersonation token and get a new support token
    Route::post('/support/end-impersonation', [SupportController::class, 'endImpersonation']);
});
