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
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

// ── Stripe Webhook (Public Callback) ─────────────────────────
Route::post('/stripe/webhook', [StripeWebhookController::class, 'handleWebhook']);

// ── Auth Endpoints (Public) ──────────────────────────────────
Route::post('/auth/register', [AuthController::class, 'register']);
Route::post('/auth/login', [AuthController::class, 'login']);

// ── Auth & Dashboard Endpoints (Protected) ───────────────────
Route::group(['middleware' => 'auth:sanctum'], function () {
    Route::post('/auth/logout', [AuthController::class, 'logout']);
    Route::get('/auth/me', [AuthController::class, 'me']);

    // Stripe Checkout Session Creation
    Route::post('/checkout/session', [CheckoutController::class, 'createSession']);
    Route::post('/checkout/verify-session', [CheckoutController::class, 'verifySession']);
    Route::post('/checkout/lease-session', [CheckoutController::class, 'createLeaseSession']);

    // Tenant Dashboard stats — tenants only
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
});

// ── General Public Endpoints
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
