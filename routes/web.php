<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;

Route::prefix('auth')->group(function () {
    Route::post('/register', [AuthController::class, 'register'])->middleware('guest');
    Route::post('/login',    [AuthController::class, 'login'])->middleware('guest');
    Route::get('/me',        [AuthController::class, 'me'])->middleware('auth');
    Route::post('/logout',   [AuthController::class, 'logout'])->middleware('auth');
});
