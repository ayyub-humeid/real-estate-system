<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Subscription extends Model
{
    protected $fillable = [
        'company_id',
        'plan_id',
        'status',
        'starts_at',
        'ends_at',
        'trial_ends_at',
        'canceled_at',
    ];

    protected $casts = [
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'trial_ends_at' => 'datetime',
        'canceled_at' => 'datetime',
    ];

    public function company(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function plan(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    public function payments(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(SubscriptionPayment::class);
    }

    
public function isActive(): bool
{
    // A subscription is active if status is correct AND it hasn't expired yet
    return ($this->status === 'active' || $this->status === 'trialing') 
           && !$this->isExpired();
}

    public function isExpired(): bool
    {
        return $this->ends_at && $this->ends_at->isPast();
    }

    /**
     * Returns the number of days remaining until the subscription expires.
     * Returns null if no end date is set (unlimited / lifetime).
     * Returns 0 if already expired.
     */
    public function getRemainingDaysAttribute(): ?int
    {
        if (! $this->ends_at) {
            return null; // No expiry = unlimited
        }

        if ($this->ends_at->isPast()) {
            return 0; // Already expired
        }

        return (int) now()->diffInDays($this->ends_at);
    }
}
