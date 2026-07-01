<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// Public web route to view a certificate at 127.0.0.1:8000/certificate/{registerId}
Route::get('/certificate/{registerId}', [\App\Http\Controllers\Api\CertificateController::class, 'generate'])->name('tuts.certificates.generate');
Route::get('/certificate/preview/{registerId}', [\App\Http\Controllers\Api\CertificateController::class, 'preview'])->name('tuts.certificates.preview');
Route::get('/run-migrations', function () {
    return Artisan::call('migrate');
});