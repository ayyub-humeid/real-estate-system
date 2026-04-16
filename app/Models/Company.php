<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;



class Company extends Model
{
    protected $fillable = [
        'name',
        'email',
        'phone',
        'address',
        'logo',
        'is_active',
    ];
    protected $casts = [
        'is_active' => 'boolean',
    ];

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
}