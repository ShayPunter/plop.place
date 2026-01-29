<?php

use App\Http\Controllers\CanvasPageController;
use Illuminate\Support\Facades\Route;

Route::get('/', [CanvasPageController::class, 'index'])->name('canvas');
