<?php

namespace App\Providers;

use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;
use App\Models\Payment;
use App\Models\User;
use App\Observers\PaymentObserver;
use App\Observers\UserObserver;


class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        
        // Register the PaymentObserver
        // \App\Models\Payment::observe(PaymentObserver::class);

    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Schema::defaultStringLength(191);
        \App\Models\Payment::observe(\App\Observers\PaymentObserver::class);
        \App\Models\User::observe(\App\Observers\UserObserver::class);
        \App\Models\MaintenanceRequest::observe(\App\Observers\MaintenanceRequestObserver::class);
        \App\Models\RentalRequest::observe(\App\Observers\RentalRequestObserver::class);
    }
}
