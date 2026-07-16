<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\CompanyBootstrapController;
use App\Http\Controllers\ContentDataController;
use App\Http\Controllers\ContentSchemaController;
use App\Http\Controllers\BookingController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\DeviceSessionController;
use App\Http\Controllers\OrganizationProfileController;
use App\Http\Controllers\DestinationController;
use App\Http\Controllers\TenantInboxController;
use App\Http\Controllers\TenantContactInquiryController;
use App\Http\Controllers\TenantContactSettingsController;
use App\Http\Controllers\TenantCustomerIntakeMailController;
use App\Http\Controllers\TenantActivityLogController;
use App\Http\Controllers\TenantDashboardController;
use App\Http\Controllers\TenantMediaController;
use App\Http\Controllers\TenantPageController;
use App\Http\Controllers\TourismBusinessController;
use App\Http\Controllers\StayController;
use App\Http\Controllers\TransportOptionController;
use Illuminate\Http\Request;
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
Route::middleware('auth:sanctum', 'tracked')->get('/admin/activity-logs', [TenantActivityLogController::class, 'index']);
Route::middleware('auth:sanctum', 'tracked')->get('/admin/inbox', [TenantInboxController::class, 'index']);
Route::middleware('auth:sanctum', 'tracked')->get('/admin/inbox/{inboxMessage}', [TenantInboxController::class, 'show'])->whereNumber('inboxMessage');
Route::middleware('auth:sanctum', 'tracked')->patch('/admin/inbox/{inboxMessage}', [TenantInboxController::class, 'update'])->whereNumber('inboxMessage');
Route::middleware('auth:sanctum', 'tracked')->delete('/admin/inbox/{inboxMessage}', [TenantInboxController::class, 'destroy'])->whereNumber('inboxMessage');
Route::middleware('auth:sanctum', 'tracked')->get('/admin/customers', [CustomerController::class, 'index']);
Route::middleware('auth:sanctum', 'tracked')->post('/admin/customers', [CustomerController::class, 'store']);
Route::middleware('auth:sanctum', 'tracked')->get('/admin/customers/{customer}', [CustomerController::class, 'show'])->whereNumber('customer');
Route::middleware('auth:sanctum', 'tracked')->patch('/admin/customers/{customer}', [CustomerController::class, 'update'])->whereNumber('customer');
Route::middleware('auth:sanctum', 'tracked')->delete('/admin/customers/{customer}', [CustomerController::class, 'destroy'])->whereNumber('customer');

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

// Destination CRUD
Route::middleware('auth:sanctum', 'tracked')->get('/admin/destinations', [DestinationController::class, 'index']);
Route::middleware('auth:sanctum', 'tracked')->post('/admin/destinations', [DestinationController::class, 'store']);
Route::middleware('auth:sanctum', 'tracked')->get('/admin/destinations/{destination}', [DestinationController::class, 'show'])->whereNumber('destination');
Route::middleware('auth:sanctum', 'tracked')->patch('/admin/destinations/{destination}', [DestinationController::class, 'update'])->whereNumber('destination');
Route::middleware('auth:sanctum', 'tracked')->delete('/admin/destinations/{destination}', [DestinationController::class, 'destroy'])->whereNumber('destination');

// Live render
Route::post('/live/{tenantKey}/contact', [TenantContactInquiryController::class, 'store']);
Route::post('/live/{tenantKey}/customer-intake/send', [TenantCustomerIntakeMailController::class, 'send']);
Route::get('/live/{tenantKey}', [TenantPageController::class, 'showDefault']);
Route::get('/live/{tenantKey}/{slug}', [TenantPageController::class, 'show']);

// Tourism business platform bridge
Route::get('/public/{tenantKey}/destinations', [DestinationController::class, 'publicIndex']);
Route::get('/public/{tenantKey}/destinations/{slug}', [DestinationController::class, 'publicShow']);
Route::get('/public/{tenantKey}/packages', [TourismBusinessController::class, 'publicPackages']);
Route::get('/public/{tenantKey}/packages/{slug}', [TourismBusinessController::class, 'publicPackageShow']);
Route::get('/public/{tenantKey}/services', [TourismBusinessController::class, 'publicServices']);
Route::get('/public/{tenantKey}/services/{slug}', [TourismBusinessController::class, 'publicServiceShow']);
Route::get('/public/{tenantKey}/activities', [TourismBusinessController::class, 'publicActivities']);
Route::get('/public/{tenantKey}/activities/{slug}', [TourismBusinessController::class, 'publicActivityShow']);
Route::get('/public/{tenantKey}/reviews', [TourismBusinessController::class, 'publicReviews']);
Route::post('/public/{tenantKey}/bookings', [BookingController::class, 'storePublic']);
Route::post('/public/{tenantKey}/bookings/{bookingReference}/payments', [CustomerPaymentController::class, 'storePublic']);
Route::post('/public/{tenantKey}/inquiries', [TourismBusinessController::class, 'storeInquiry']);

Route::get('/customer/dashboard', [TourismBusinessController::class, 'customerDashboard']);
Route::get('/customer/bookings', [TourismBusinessController::class, 'customerBookings']);
Route::get('/customer/bookings/{bookingReference}', [TourismBusinessController::class, 'customerBookingShow']);
Route::post('/customer/bookings/{bookingReference}/payments', [CustomerPaymentController::class, 'storeCustomer']);
Route::match(['get', 'patch'], '/customer/profile', [TourismBusinessController::class, 'customerProfile']);
Route::match(['get', 'post'], '/customer/reviews', [TourismBusinessController::class, 'customerReviews']);

Route::middleware(['auth:sanctum', 'tracked'])->group(function () {
    Route::prefix('admin')->group(function () {
        Route::get('/business-dashboard', [TourismBusinessController::class, 'dashboard']);
        Route::get('/packages', [TourismBusinessController::class, 'adminPackages']);
        Route::post('/packages', [TourismBusinessController::class, 'adminPackageStore']);
        Route::patch('/packages/{id}', [TourismBusinessController::class, 'adminPackageUpdate']);
        Route::delete('/packages/{id}', [TourismBusinessController::class, 'adminPackageDestroy']);
        Route::get('/services', [TourismBusinessController::class, 'adminServices']);
        Route::post('/services', [TourismBusinessController::class, 'adminServiceStore']);
        Route::patch('/services/{id}', [TourismBusinessController::class, 'adminServiceUpdate']);
        Route::delete('/services/{id}', [TourismBusinessController::class, 'adminServiceDestroy']);
        Route::get('/activities', [TourismBusinessController::class, 'adminActivities']);
        Route::post('/activities', [TourismBusinessController::class, 'adminActivityStore']);
        Route::patch('/activities/{id}', [TourismBusinessController::class, 'adminActivityUpdate']);
        Route::delete('/activities/{id}', [TourismBusinessController::class, 'adminActivityDestroy']);

        Route::get('/bookings', [BookingController::class, 'index']);
        Route::post('/bookings', [BookingController::class, 'store']);
        Route::get('/bookings/{booking}', [BookingController::class, 'show'])->whereNumber('booking');
        Route::patch('/bookings/{booking}', [BookingController::class, 'update'])->whereNumber('booking');
        Route::delete('/bookings/{booking}', [BookingController::class, 'destroy'])->whereNumber('booking');

        Route::get('/stays', [StayController::class, 'index']);
        Route::post('/stays', [StayController::class, 'store']);
        Route::get('/stays/{stay}', [StayController::class, 'show'])->whereNumber('stay');
        Route::patch('/stays/{stay}', [StayController::class, 'update'])->whereNumber('stay');
        Route::delete('/stays/{stay}', [StayController::class, 'destroy'])->whereNumber('stay');
        Route::get('/accommodations', [StayController::class, 'index']);
        Route::post('/accommodations', [StayController::class, 'store']);
        Route::get('/accommodations/{stay}', [StayController::class, 'show'])->whereNumber('stay');
        Route::patch('/accommodations/{stay}', [StayController::class, 'update'])->whereNumber('stay');
        Route::delete('/accommodations/{stay}', [StayController::class, 'destroy'])->whereNumber('stay');

        Route::get('/transport', [TransportOptionController::class, 'index']);
        Route::post('/transport', [TransportOptionController::class, 'store']);
        Route::get('/transport/{transportOption}', [TransportOptionController::class, 'show'])->whereNumber('transportOption');
        Route::patch('/transport/{transportOption}', [TransportOptionController::class, 'update'])->whereNumber('transportOption');
        Route::delete('/transport/{transportOption}', [TransportOptionController::class, 'destroy'])->whereNumber('transportOption');
        Route::get('/transport-options', [TransportOptionController::class, 'index']);
        Route::post('/transport-options', [TransportOptionController::class, 'store']);
        Route::get('/transport-options/{transportOption}', [TransportOptionController::class, 'show'])->whereNumber('transportOption');
        Route::patch('/transport-options/{transportOption}', [TransportOptionController::class, 'update'])->whereNumber('transportOption');
        Route::delete('/transport-options/{transportOption}', [TransportOptionController::class, 'destroy'])->whereNumber('transportOption');
    });
});

