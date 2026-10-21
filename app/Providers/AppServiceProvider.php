<?php

namespace App\Providers;

use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;
use App\Models\User;
use App\Observers\UserObserver;
use Filament\Support\Assets\Js;
use Filament\Support\Facades\FilamentAsset;
use Illuminate\Support\Facades\Vite;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\Connection;
use Filament\Actions\Action;
use Filament\Forms\Components\Component as FormComponent;
use Filament\Infolists\Components\Component as InfolistComponent;
use Filament\Tables\Columns\Column;
use Filament\Tables\Filters\BaseFilter;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        Connection::resolverFor('pgsql', function ($connection, $database, $prefix, $config) {
            return new \App\Database\PostgresConnection($connection, $database, $prefix, $config);
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Legacy resources can opt into `translateLabel()` once here. This
        // keeps Form fields, table columns, filters, infolist entries, and
        // actions on the same Laravel translation path as newer modules.
        FormComponent::configureUsing(fn (FormComponent $component) => $component->translateLabel());
        InfolistComponent::configureUsing(fn (InfolistComponent $component) => $component->translateLabel());
        Column::configureUsing(fn (Column $component) => $component->translateLabel());
        BaseFilter::configureUsing(fn (BaseFilter $component) => $component->translateLabel());
        Action::configureUsing(fn (Action $component) => $component->translateLabel());

        Schema::defaultStringLength(191);
        Relation::morphMap([
            'planned_unit' => \App\Models\ProjectPlannedUnit::class,
            'unit' => \App\Models\Unit::class,
        ]);

        if (config('app.env') === 'production') {
            \Illuminate\Support\Facades\URL::forceScheme('https');
        }

        \App\Models\User::observe(\App\Observers\UserObserver::class);
        \App\Models\MaintenanceRequest::observe(\App\Observers\MaintenanceRequestObserver::class);
        \App\Models\RentalRequest::observe(\App\Observers\RentalRequestObserver::class);
    }
}
