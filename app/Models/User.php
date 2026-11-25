<?php
// app/Models/User.php

namespace App\Models;

use App\Traits\HasCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\Auth;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable implements FilamentUser
{
    use HasFactory, Notifiable, HasApiTokens, HasCompany, HasRoles;
    use \App\Models\Concerns\PreventsDeletionWithHistory;

    /** users → tenants → leases → payments cascade (hard delete); expenses and maintenance requests cascade too. */
    protected function deletionChecks(): array
    {
        $db = \Illuminate\Support\Facades\DB::class;

        return [
            'leases' => fn (): bool => $db::table('leases')
                ->whereIn('tenant_id', $db::table('tenants')->where('user_id', $this->id)->select('id'))
                ->exists(),
            'payments' => fn (): bool => $db::table('payments')->where('recorded_by', $this->id)->exists(),
            'expenses' => fn (): bool => $db::table('expenses')->where('created_by', $this->id)->exists(),
            'maintenance_requests' => fn (): bool => $db::table('maintenance_requests')->where('reported_by_id', $this->id)->exists(),
        ];
    }

    protected $fillable = [
        'company_id',
        'name',
        'email',
        'password',
        'role',
        'phone',
        'locale',
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
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (Auth::hasUser() && !Auth::user()->isSuperAdmin()) {
                if (!Auth::user()->company?->canAddUser()) {
                    throw \Illuminate\Validation\ValidationException::withMessages([
                        'limit' => __('You have reached the maximum number of users allowed by your plan.'),
                    ]);
                }
            }
        });
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return $this->roles()->exists();
    }

    public function isSuperAdmin(): bool
    {
        return $this->hasRole('super_admin');
    }

    /**
     * Null-safe plan feature check: false when the user has no company
     * or the company has no active subscription/plan.
     */
    public function companyHasFeature(string $feature): bool
    {
        return (bool) $this->company?->hasFeature($feature);
    }

    public function isCompanyAdmin(): bool
    {
        return $this->hasRole('company_admin');
    }

    public function isPropertyManager(): bool
    {
        return $this->hasRole('property_manager');
    }

    // public function isTenant(): bool
    // {
    //     return $this->role === 'tenant';
    // }
    // app/Models/User.php

    // public function leasesAsTenant(): HasMany
// {
//     return $this->hasMany(Lease::class, 'tenant_id');
// }
// app/Models/User.php

    public function leasesAsTenant(): HasManyThrough
    {
        return $this->hasManyThrough(
            Lease::class,
            Tenant::class,
            'user_id',
            'tenant_id',
            'id',
            'id'
        );
    }

    // public function currentLease(): HasOne
// {
//     return $this->hasOne(Lease::class, 'tenant_id')->where('status', 'active');
// }


    // Better approach:
// public function currentLease()
// {
//     return $this->hasOneThrough(Lease::class, Tenant::class, 'user_id', 'tenant_id', 'id', 'id')
//         ->where('leases.status', 'active');
// }
// public function payments(): HasManyThrough
// {
//     return $this->hasManyThrough(Payment::class, Lease::class, 'tenant_id', 'lease_id');
// }
    public function ownedLeads(): HasMany { return $this->hasMany(Lead::class, 'assigned_to'); }
    public function ownedOpportunities(): HasMany { return $this->hasMany(Opportunity::class, 'assigned_to'); }

    public function tenants(): HasMany
    {
        return $this->hasMany(Tenant::class);
    }

    public function tenant(): HasOne
    {
        return $this->hasOne(Tenant::class);
    }

    public function employee(): HasOne
    {
        return $this->hasOne(Employee::class);
    }

    public function isTenant(): bool
    {
        return $this->role == 'tenant' || $this->hasRole('tenant');
    }

    // --- Scopes ---

    public function scopeByRole($query, $role)
    {
        return $query->role($role);
    }

    public function scopeAdmins($query)
    {
        return $query->role(['super_admin', 'company_admin']);
    }

    public function scopeTenants($query)
    {
        return $query->role('tenant');
    }

    public function scopePropertyManagers($query)
    {
        return $query->role('property_manager');
    }

    /**
     * Get the user's avatar URL.
     */
    public function getAvatarUrlAttribute(): string
    {
        if ($this->relationLoaded('employee') && $this->employee && $this->employee->avatar) {
            return storage_url($this->employee->avatar);
        }
        return "https://ui-avatars.com/api/?name=" . urlencode($this->name) . "&background=0D8ABC&color=fff";
    }
}
