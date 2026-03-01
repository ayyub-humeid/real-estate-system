<?php
// app/Models/Tenant.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Tenant extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'user_id',
        'avatar',
        // 'company_id',
        'emergency_contact_name',
        'emergency_contact_phone',
        'emergency_contact_relationship',
        'employer_name',
        'employer_phone',
        'employer_address',
        'job_title',
        'monthly_income',
        'employment_start_date',
        'previous_address',
        'previous_landlord_name',
        'previous_landlord_phone',
        'previous_tenancy_start',
        'previous_tenancy_end',
        'id_type',
        'id_number',
        'id_expiry_date',
        'move_in_date',
        'number_of_occupants',
        'has_pets',
        'pet_details',
        'references',
        'background_check_status',
        'background_check_date',
        'background_check_notes',
        'status',
        'notes',
        'company_id',
    ];

    protected $casts = [
        'monthly_income' => 'decimal:2',
        'employment_start_date' => 'date',
        'previous_tenancy_start' => 'date',
        'previous_tenancy_end' => 'date',
        'id_expiry_date' => 'date',
        'move_in_date' => 'date',
        'background_check_date' => 'date',
        'number_of_occupants' => 'integer',
        'has_pets' => 'boolean',
        'references' => 'array', // ✅ Auto-cast JSON to array
    ];

    // 🔥 Relationships
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    // public function leases(): HasMany
    // {
    //     return $this->hasMany(Lease::class, 'tenant_id', 'user_id');
    // }
    public function leases(): HasMany
    {
        return $this->hasMany(Lease::class, 'tenant_id');
    }
    // ✅ 4. Tenant → Payments (THROUGH Leases)
    public function payments(): HasManyThrough
    {
        return $this->hasManyThrough(
            Payment::class,      // Final model
            Lease::class,        // Intermediate model
            'tenant_id',         // Foreign key on leases table
            'lease_id',          // Foreign key on payments table
            'id',                // Local key on tenants table
            'id'                 // Local key on leases table
        );
    }
    public function rentalRequest(): HasMany
    {
        return $this->hasMany(RentalRequest::class);
    }
    // ✅ In Tenant.php — makes perfect sense
    public function currentLease(): HasOne
    {
        return $this->hasOne(Lease::class, 'tenant_id')
            ->where('status', 'active')
            ->latest();
    }
    // 🔥 Scopes
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    // public function scopeWithCurrentLease($query)
    // {
    //     return $query->whereHas(
    //         'user.leasesAsTenant',
    //         fn($q) =>
    //         $q->where('status', 'active')
    //     );
    // }


    // 🔥 Accessors
    public function getCurrentLeaseAttribute()
    {
        return $this->currentLease;  // ✅ Use Tenant's own relationship
    }

    public function getIsActiveAttribute(): bool
    {
        return $this->status === 'active';
    }

    public function getFullNameAttribute(): string
    {
        return $this->user->name ?? 'N/A';
    }

    public function getEmailAttribute(): string
    {
        return $this->user->email ?? 'N/A';
    }

    public function getPhoneAttribute(): string
    {
        return $this->user->phone ?? 'N/A';
    }
}
