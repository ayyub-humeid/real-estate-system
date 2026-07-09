<?php

use App\Http\Controllers\Api\AgencyController;
use App\Http\Controllers\Api\UnitController;
use App\Http\Controllers\Api\PlanController;
use App\Http\Controllers\ContactController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\TenantDashboardController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');


Route::group([
    'as' => 'api.',
    //    'middleware'=>'auth:sanctum',
], function () {
    Route::get('featured-units', [UnitController::class, 'featured'])->name('units.featured');
    Route::get('plans', [PlanController::class, 'index'])->name('plans.index');
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
