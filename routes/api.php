<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\CompanyBootstrapController;
use App\Http\Controllers\ContentSchemaController;
use App\Http\Controllers\DeviceSessionController;
use App\Http\Controllers\OrganizationProfileController;
use Illuminate\Support\Facades\Route;

// Auth routes
Route::middleware('auth:sanctum', 'tracked')->get('/me',                            [AuthController::class, 'me']);
Route::middleware('auth:sanctum', 'tracked')->get('/me/sessions',                   [DeviceSessionController::class, 'list']);
Route::middleware('auth:sanctum', 'tracked')->post('/me/sessions/{id}/logout',      [DeviceSessionController::class, 'logoutSingle']);
Route::middleware('auth:sanctum', 'tracked')->post('/me/sessions/logout-others',    [DeviceSessionController::class, 'logoutOthers']);

// Tenant Bootstrap Routes
Route::middleware('auth:sanctum', 'tracked')->get  ('/company/bootstrap',           [CompanyBootstrapController::class, 'index']);
Route::middleware('auth:sanctum', 'tracked')->get  ('/company/bootstrap/theme',     [CompanyBootstrapController::class, 'getTheme']);
Route::middleware('auth:sanctum', 'tracked')->patch('/company/bootstrap/theme',     [CompanyBootstrapController::class, 'updateTheme']);

// Organization profile 
Route::middleware('auth:sanctum', 'tracked')->get('/organization',                  [OrganizationProfileController::class, 'index']);

// Content Management [Schema]
Route::middleware('auth:sanctum', 'tracked')->get   ('/content',                      [ContentSchemaController::class, 'index']);
Route::middleware('auth:sanctum', 'tracked')->post  ('/content',                      [ContentSchemaController::class, 'store']);
Route::middleware('auth:sanctum', 'tracked')->get   ('/content/{content}',            [ContentSchemaController::class, 'show']);
Route::middleware('auth:sanctum', 'tracked')->put   ('/content/{content}',            [ContentSchemaController::class, 'update']);
Route::middleware('auth:sanctum', 'tracked')->delete('/content/{content}',            [ContentSchemaController::class, 'destroy']);

// Content Management [Data]