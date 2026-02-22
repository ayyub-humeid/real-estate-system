<?php
// app/Models/User.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\HasOne;

class User extends Authenticatable implements FilamentUser
{
    use HasFactory, Notifiable;

    protected $fillable = [
        'company_id',
        'name',
        'email',
        'password',
        'role',
        'phone',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return in_array($this->role, ['super_admin', 'company_admin', 'property_manager']);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function isSuperAdmin(): bool
    {
        return $this->role === 'super_admin';
    }

    public function isCompanyAdmin(): bool
    {
        return $this->role === 'company_admin';
    }

    public function isPropertyManager(): bool
    {
        return $this->role === 'property_manager';
    }

    public function isTenant(): bool
    {
        return $this->role === 'tenant';
    }
    // app/Models/User.php

public function leasesAsTenant(): HasMany
{
    return $this->hasMany(Lease::class, 'tenant_id');
}

public function currentLease(): HasOne
{
    return $this->hasOne(Lease::class, 'tenant_id')->where('status', 'active');
}

public function payments(): HasManyThrough
{
    return $this->hasManyThrough(Payment::class, Lease::class, 'tenant_id', 'lease_id');
}
}