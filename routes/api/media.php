<?php

use App\Http\Controllers\Api\MediaController;
use App\Http\Controllers\Api\MediaFolderController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Media Routes
|--------------------------------------------------------------------------
|
| Upload, list, and delete media files.
| Virtual media folders (organizational only — never shown in the
| MediaManager dialog, never created on disk).
|
*/

Route::get('/media', [MediaController::class, 'index']);
Route::put('/media/{id}', [MediaController::class, 'update']);
Route::delete('/media/{id}', [MediaController::class, 'destroy']);
Route::post('/media/{id}/move', [MediaController::class, 'move']);

// Virtual media folders
Route::get('/media/folders', [MediaFolderController::class, 'index']);
Route::post('/media/folders', [MediaFolderController::class, 'store']);
Route::put('/media/folders/{id}', [MediaFolderController::class, 'update']);
Route::delete('/media/folders/{id}', [MediaFolderController::class, 'destroy']);
