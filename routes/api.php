<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CanvasController;
use App\Http\Controllers\Api\PixelController;
use Illuminate\Support\Facades\Route;

// Auth routes
Route::prefix('auth')->group(function () {
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/anonymous', [AuthController::class, 'anonymous']);
    Route::get('/me', [AuthController::class, 'me']);

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/logout', [AuthController::class, 'logout']);
    });
});

// Canvas routes
Route::prefix('canvas')->group(function () {
    Route::get('/', [CanvasController::class, 'index']);
    Route::get('/info', [CanvasController::class, 'info']);
    Route::get('/history', [CanvasController::class, 'history']);
});

// Pixel routes
Route::post('/pixel', [PixelController::class, 'store']);
Route::get('/cooldown', [PixelController::class, 'cooldown']);
