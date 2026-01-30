<?php

use App\Http\Controllers\CanvasPageController;
use App\Http\Controllers\StatsPageController;
use Illuminate\Support\Facades\Route;

Route::get('/', [CanvasPageController::class, 'index'])->name('canvas');
Route::get('/stats', [StatsPageController::class, 'index'])->name('stats');
