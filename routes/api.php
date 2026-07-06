<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\CompanyBootstrapController;
use App\Http\Controllers\ContentDataController;
use App\Http\Controllers\ContentSchemaController;
use App\Http\Controllers\DeviceSessionController;
use App\Http\Controllers\OrganizationProfileController;
use App\Http\Controllers\TenantContactInquiryController;
use App\Http\Controllers\TenantContactSettingsController;
use App\Http\Controllers\TenantCustomerIntakeMailController;
use App\Http\Controllers\TenantDashboardController;
use App\Http\Controllers\TenantMediaController;
use App\Http\Controllers\TenantPageController;
use Illuminate\Support\Facades\Route;

// Auth routes
Route::middleware('auth:sanctum', 'tracked')->get('/me',                            [AuthController::class, 'me']);
Route::middleware('auth:sanctum', 'tracked')->get('/me/sessions',                   [DeviceSessionController::class, 'list']);
Route::middleware('auth:sanctum', 'tracked')->post('/me/sessions/{id}/logout',      [DeviceSessionController::class, 'logoutSingle']);
Route::middleware('auth:sanctum', 'tracked')->post('/me/sessions/logout-others',    [DeviceSessionController::class, 'logoutOthers']);

// Tenant Bootstrap Routes
Route::middleware('auth:sanctum', 'tracked')->get('/company/bootstrap', [CompanyBootstrapController::class, 'index']);
Route::middleware('auth:sanctum', 'tracked')->get('/company/bootstrap/theme', [CompanyBootstrapController::class, 'getTheme']);
Route::middleware('auth:sanctum', 'tracked')->patch('/company/bootstrap/theme', [CompanyBootstrapController::class, 'updateTheme']);
Route::middleware('auth:sanctum', 'tracked')->get('/company/bootstrap/contact-settings', [TenantContactSettingsController::class, 'show']);
Route::middleware('auth:sanctum', 'tracked')->patch('/company/bootstrap/contact-settings', [TenantContactSettingsController::class, 'update']);

// Organization profile 
Route::middleware('auth:sanctum', 'tracked')->get('/organization', [OrganizationProfileController::class, 'index']);
Route::middleware('auth:sanctum', 'tracked')->get('/tenant/dashboard', [TenantDashboardController::class, 'index']);

// Content Management [Schema]
Route::middleware('auth:sanctum', 'tracked')->get('/content', [ContentSchemaController::class, 'index']);
Route::middleware('auth:sanctum', 'tracked')->get('/content/available', [ContentSchemaController::class, 'available']);
Route::middleware('auth:sanctum', 'tracked')->post('/content', [ContentSchemaController::class, 'store']);
Route::middleware('auth:sanctum', 'tracked')->get('/content/{content}', [ContentSchemaController::class, 'show'])->whereNumber('content');
Route::middleware('auth:sanctum', 'tracked')->put('/content/{content}', [ContentSchemaController::class, 'update'])->whereNumber('content');
Route::middleware('auth:sanctum', 'tracked')->delete('/content/{content}', [ContentSchemaController::class, 'destroy'])->whereNumber('content');

// Content Management [Data]
Route::middleware('auth:sanctum', 'tracked')->get('/content/data', [ContentDataController::class, 'index']);
Route::middleware('auth:sanctum', 'tracked')->get('/content/data/sources', [ContentDataController::class, 'sources']);
Route::middleware('auth:sanctum', 'tracked')->post('/content/data', [ContentDataController::class, 'store']);
Route::middleware('auth:sanctum', 'tracked')->get('/content/data/{data}', [ContentDataController::class, 'show']);
Route::middleware('auth:sanctum', 'tracked')->put('/content/data/{data}', [ContentDataController::class, 'update']);
Route::middleware('auth:sanctum', 'tracked')->delete('/content/data/{data}', [ContentDataController::class, 'destroy']);

// Tenant Pages / Designer
Route::middleware('auth:sanctum', 'tracked')->get('/tenant/pages', [TenantPageController::class, 'index']);
Route::middleware('auth:sanctum', 'tracked')->get('/tenant/pages/{slug}', [TenantPageController::class, 'edit']);
Route::middleware('auth:sanctum', 'tracked')->post('/tenant/pages', [TenantPageController::class, 'store']);
Route::middleware('auth:sanctum', 'tracked')->put('/tenant/pages/{slug}', [TenantPageController::class, 'update']);
Route::middleware('auth:sanctum', 'tracked')->delete('/tenant/pages/{slug}', [TenantPageController::class, 'destroy']);

// Tenant Media Library
Route::middleware('auth:sanctum', 'tracked')->get('/tenant/media', [TenantMediaController::class, 'index']);
Route::middleware('auth:sanctum', 'tracked')->post('/tenant/media', [TenantMediaController::class, 'store']);
Route::middleware('auth:sanctum', 'tracked')->delete('/tenant/media/{asset}', [TenantMediaController::class, 'destroy'])->whereNumber('asset');

// Live render
Route::post('/live/{tenantKey}/contact', [TenantContactInquiryController::class, 'store']);
Route::post('/live/{tenantKey}/customer-intake/send', [TenantCustomerIntakeMailController::class, 'send']);
Route::get('/live/{tenantKey}', [TenantPageController::class, 'showDefault']);
Route::get('/live/{tenantKey}/{slug}', [TenantPageController::class, 'show']);
