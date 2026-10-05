<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Permission\Contracts\Role as RoleContract;
use Spatie\Permission\Exceptions\RoleAlreadyExists;
use Spatie\Permission\Exceptions\RoleDoesNotExist;
use Spatie\Permission\Guard;
use Spatie\Permission\Models\Role as SpatieRole;

class Role extends SpatieRole
{
    protected $fillable = ['name', 'guard_name', 'company_id'];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function scopePlatform(Builder $query): Builder
    {
        return $query->whereNull('company_id');
    }

    public function scopeForCompany(Builder $query, int $companyId): Builder
    {
        return $query->where('company_id', $companyId);
    }

    /**
     * Spatie's default uniqueness key is name + guard. Company ownership is part
     * of this application's role identity, so the package check must match the
     * database constraint instead of rejecting another company's same-named role.
     */
    public static function create(array $attributes = []): RoleContract
    {
        $attributes['guard_name'] ??= Guard::getDefaultName(static::class);
        $companyId = $attributes['company_id'] ?? null;

        if (static::query()->where('name', $attributes['name'])->where('guard_name', $attributes['guard_name'])
            ->when($companyId, fn (Builder $query) => $query->where('company_id', $companyId), fn (Builder $query) => $query->whereNull('company_id'))
            ->exists()) {
            throw RoleAlreadyExists::create($attributes['name'], $attributes['guard_name']);
        }

        $role = new static($attributes);
        $role->save();

        return $role;
    }

    public static function findByName(string $name, ?string $guardName = null): RoleContract
    {
        $guardName ??= Guard::getDefaultName(static::class);
        $user = auth()->user();
        $companyId = $user && $user->company_id && ! $user->isSuperAdmin() ? $user->company_id : null;

        $role = static::query()->where('name', $name)->where('guard_name', $guardName)
            ->when($companyId, fn (Builder $query) => $query->where('company_id', $companyId), fn (Builder $query) => $query->whereNull('company_id'))
            ->first();

        if (! $role) {
            throw RoleDoesNotExist::named($name, $guardName);
        }

        return $role;
    }
}
