<?php

use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use App\Models\Plan;
use App\Models\User;
use App\Models\Company;
use App\Services\StripePaymentService;

Route::get('/', function () {
    return view('welcome');
});
Route::get('/install-storage', function () {
    Illuminate\Support\Facades\Artisan::call('storage:link');
    return 'Storage linked successfully!';
});

Route::get('/test-checkout-view', function () {
    return view('test-checkout');
});

Route::post('/test-checkout', function (StripePaymentService $stripeService) {
    // 1. Get or create a basic plan
    $plan = Plan::firstOrCreate(
        ['slug' => 'basic'],
        [
            'name' => 'Basic Plan',
            'description' => 'A basic plan',
            'price' => 19.00,
            'billing_cycle' => 'monthly',
            'is_active' => true,
            'features' => []
        ]
    );

    // 2. Get or create a test company
    $company = Company::firstOrCreate(
        ['email' => 'testcompany@example.com'],
        [
            'name' => 'Test Company',
            'phone' => '1234567890',
            'is_active' => true,
        ]
    );

    // 3. Get or create a test user
    $user = User::firstOrCreate(
        ['email' => 'testuser@example.com'],
        [
            'name' => 'Test User',
            'password' => Hash::make('password'),
            'company_id' => $company->id,
            'role' => 'company_admin'
        ]
    );

    // Create session
    $session = $stripeService->createCheckoutSession($plan, (string) $company->id, (string) $user->id);

    // Redirect directly to Stripe Checkout
    return redirect($session->url);
})->name('test.checkout');

Route::get('success', function () {
    return 'success';
})->name('success');
Route::get('cancel', function () {
    return 'failed';
})->name('fail');