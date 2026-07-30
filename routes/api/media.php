<?php

use App\Http\Controllers\Api\MediaController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Media Routes
|--------------------------------------------------------------------------
|
| Upload, list, and delete media files.
|
*/

Route::get('/media', [MediaController::class, 'index']);
Route::delete('/media/{id}', [MediaController::class, 'destroy']);
