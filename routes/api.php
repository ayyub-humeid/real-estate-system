<?php

use App\Http\Controllers\Api\AgencyController;
use App\Http\Controllers\Api\TenantMaintenanceController;
use App\Http\Controllers\Api\UnitController;
use App\Http\Controllers\Api\PlanController;
use App\Http\Controllers\ContactController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\TenantDashboardController;
use App\Http\Controllers\Api\CheckoutController;
use App\Http\Controllers\Api\StripeWebhookController;
use App\Http\Controllers\Api\TenantRentalRequestController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

use Illuminate\Support\Facades\Broadcast;
Broadcast::routes(['middleware' => ['auth:sanctum']]);

// â”€â”€ Stripe Webhook (Public Callback) â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
Route::post('/stripe/webhook', [StripeWebhookController::class, 'handleWebhook']);

// â”€â”€ Auth Endpoints (Public) â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
Route::post('/auth/register', [AuthController::class, 'register']);
Route::post('/auth/login', [AuthController::class, 'login']);

// â”€â”€ Auth & Dashboard Endpoints (Protected) â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
Route::group(['middleware' => 'auth:sanctum'], function () {
    Route::post('/auth/logout', [AuthController::class, 'logout']);
    Route::get('/auth/me', [AuthController::class, 'me']);

    // Stripe Checkout Session Creation
    Route::post('/checkout/session', [CheckoutController::class, 'createSession']);
    Route::post('/checkout/verify-session', [CheckoutController::class, 'verifySession']);
    Route::post('/checkout/lease-session', [CheckoutController::class, 'createLeaseSession']); // Role guard in controller via isTenant() — checks both column + Spatie
    Route::post('/checkout/payment-session', [CheckoutController::class, 'createPaymentSession']);
    Route::post('/checkout/verify-payment-session', [CheckoutController::class, 'verifyPaymentSession']);

    // Tenant Dashboard stats â€” tenants only
    Route::get('/tenant/dashboard', [TenantDashboardController::class, 'index'])
        ->middleware('role:tenant,sanctum');

    // Tenant maintenance-requests
    Route::get('/tenant/maintenance-requests', [TenantMaintenanceController::class, 'index'])
        ->middleware('role:tenant,sanctum');
    Route::post('/tenant/maintenance-requests', [TenantMaintenanceController::class, 'store'])
        ->middleware('role:tenant,sanctum');

    // Tenant Payments Resource
    Route::get('/tenant/payments', [\App\Http\Controllers\Api\TenantPaymentController::class, 'index'])
        ->middleware('role:tenant,sanctum');
    Route::get('/tenant/payments/{payment}', [\App\Http\Controllers\Api\TenantPaymentController::class, 'show'])
        ->middleware('role:tenant,sanctum');
    // Route::post('/tenant/payments/{payment}/pay', [\App\Http\Controllers\Api\TenantPaymentController::class, 'pay'])
    //     ->middleware('role:tenant,sanctum');

    // Tenant Leases Resource
    Route::get('/tenant/leases', [\App\Http\Controllers\Api\TenantLeaseController::class, 'index'])
        ->middleware('role:tenant,sanctum');
    Route::get('/tenant/leases/{lease}', [\App\Http\Controllers\Api\TenantLeaseController::class, 'show'])
        ->middleware('role:tenant,sanctum');

    // Tenant Rental Requests Resource
    Route::get('/tenant/rental-requests', [TenantRentalRequestController::class, 'index'])
        ->middleware('role:tenant,sanctum');
    Route::get('/tenant/rental-requests/{id}', [TenantRentalRequestController::class, 'show'])
        ->middleware('role:tenant,sanctum');
    Route::delete('/tenant/rental-requests/{id}', [TenantRentalRequestController::class, 'destroy'])
        ->middleware('role:tenant,sanctum');

    // Tenant Notifications
    Route::get('/tenant/notifications', [\App\Http\Controllers\Api\TenantNotificationController::class, 'index'])
        ->middleware('role:tenant,sanctum');
    Route::post('/tenant/notifications/mark-as-read', [\App\Http\Controllers\Api\TenantNotificationController::class, 'markAsRead'])
        ->middleware('role:tenant,sanctum');
    Route::post('/tenant/notifications/{id}/mark-as-read', [\App\Http\Controllers\Api\TenantNotificationController::class, 'markAsRead'])
        ->middleware('role:tenant,sanctum');
});

// â”€â”€ General Public Endpoints
Route::group([
    'as' => 'api.',
    //    'middleware'=>'auth:sanctum',
], function () {
    Route::get('featured-units', [UnitController::class, 'featured'])->name('units.featured');
    Route::get('plans', [PlanController::class, 'index'])->name('plans.index');
    Route::get('plans/{plan:slug}', [PlanController::class, 'show'])->name('plans.show');
    Route::get('units/{unit}', [UnitController::class, 'show'])->name('units.show');
    Route::get('units', [UnitController::class, 'index'])->name('units.index');
    Route::post('/contacts', [ContactController::class, 'store'])->name('contacts.store');

    // Properties endpoints
    Route::get('properties', [\App\Http\Controllers\Api\PropertyController::class, 'index'])->name('properties.index');
    Route::get('properties/{property}', [\App\Http\Controllers\Api\PropertyController::class, 'show'])->name('properties.show');

    // Unit ratings
    Route::post('units/{unit}/rate', [UnitController::class, 'rate'])->name('units.rate');

    // Agencies endpoints
    Route::apiResource('agencies', AgencyController::class)->only(['index', 'show', 'store']);
});
