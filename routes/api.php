<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\CompanyBootstrapController;
use App\Http\Controllers\DeviceSessionController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum', 'tracked')->get('/me',                         [AuthController::class, 'me']);
Route::middleware('auth:sanctum', 'tracked')->get('/me/sessions',                [DeviceSessionController::class, 'list']);
Route::middleware('auth:sanctum', 'tracked')->post('/me/sessions/{id}/logout',   [DeviceSessionController::class, 'logoutSingle']);
Route::middleware('auth:sanctum', 'tracked')->post('/me/sessions/logout-others', [DeviceSessionController::class, 'logoutOthers']);

// Tenant Bootstrap Routes
Route::get  ('/company/bootstrap',       [CompanyBootstrapController::class, 'index']);
Route::get  ('/company/bootstrap/theme', [CompanyBootstrapController::class, 'getTheme']);
Route::patch('/company/bootstrap/theme', [CompanyBootstrapController::class, 'updateTheme']);