<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// Public web route to view a certificate at 127.0.0.1:8000/certificate/{registerId}
Route::get('/certificate/{register}', [\App\Http\Controllers\Api\CertificateController::class, 'generate'])->name('tuts.certificates.generate');
Route::get('/certificate/preview/{register}', [\App\Http\Controllers\Api\CertificateController::class, 'preview'])->name('tuts.certificates.preview');
