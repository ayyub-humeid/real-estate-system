<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use App\Models\Unit;
use App\Models\Property;




class Company extends Model
{
    protected $fillable = [
        'name',
        'email',
        'phone',
        'address',
        'logo',
        'is_active',
        'verified',
        'relation',
        'badge',
        'badge_type',
        'hq',
        'branches',
        'rating',
        'years_active',
        'partner_developers',
        'about_title',
        'about_description',
        'about_sub_description',
    ];
    protected $casts = [
        'is_active' => 'boolean',
        'verified' => 'boolean',
        'rating' => 'float',
        'years_active' => 'integer',
    ];

    // Attributes

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }
    public function properties(): HasMany
    {
        return $this->hasMany(Property::class);
    }

    public function leases(): HasMany
    {
        return $this->hasMany(Lease::class);
    }

    public function rentalRequests(): HasMany
    {
        return $this->hasMany(RentalRequest::class);
    }

    public function expenses(): HasMany
    {
        return $this->hasMany(Expense::class);
    }
    public function units(): \Illuminate\Database\Eloquent\Relations\HasManyThrough
    {
        return $this->hasManyThrough(
            Unit::class,      // Final model
            Property::class,  // Intermediate model
            'company_id',     // Foreign key on properties table
            'property_id',    // Foreign key on units table
            'id',             // Local key on companies table
            'id'              // Local key on properties table
        );
    }

    public function employees(): HasMany
    {
        return $this->hasMany(Employee::class);
    }
    public function settings(): HasOne
    {
        return $this->hasOne(CompanySetting::class);
    }

    public function subscription(): HasOne
    {
        return $this->hasOne(Subscription::class)->latestOfMany();
    }

    public function subscriptionPayments(): HasMany
    {
        return $this->hasManyThrough(SubscriptionPayment::class, Subscription::class);
    }

    public function hasActiveSubscription(): bool
    {
        return $this->subscription && $this->subscription->isActive();
    }

    public function getPlanFeature(string $feature, $default = null)
    {
        if (!$this->hasActiveSubscription()) {
            return $default;
        }

        return $this->subscription->plan->features[$feature] ?? $default;
    }

    public function canAddProperty(): bool
    {
        $limit = $this->getPlanFeature('max_properties');

        if ($limit === null) {
            return true; // Unlimited
        }

        return $this->properties()->count() < $limit;
    }


    public function canAddEmployee(): bool
    {
        $limit = $this->getPlanFeature('max_employees');

        if ($limit === null) {
            return true; // Unlimited
        }

        return $this->employees()->count() < $limit;
    }

    public function canAddUnit(): bool
    {
        $limit = $this->getPlanFeature('max_units');

        if ($limit === null) {
            return true; // Unlimited
        }

        // Count all units across all properties of this company
        return $this->properties()->withCount('units')->get()->sum('units_count') < $limit;
    }

    public function canAddUser(): bool
    {
        $limit = $this->getPlanFeature('max_users');

        if ($limit === null) {
            return true; // Unlimited
        }

        return $this->users()->count() < $limit;
    }

    /**
     * Check if the company's active plan includes a boolean feature.
     * Used for feature flags like: maintenance_tracking, rental_requests, etc.
     */
    public function hasFeature(string $feature): bool
    {
        return (bool) $this->getPlanFeature($feature, false);
    }
}