<?php

namespace App\Models\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use Illuminate\Support\Facades\Auth;

class CompanyScope implements Scope
{
    /**
     * Apply the scope to a given Eloquent query builder.
     */
    public function apply(Builder $builder, Model $model): void
    {
        // 🚀 CRITICAL FIX: Use hasUser() to avoid infinite recursion when scoping the User model itself
        if (Auth::hasUser()) {
            $user = Auth::user();

            // Only apply if the user is not a super admin
            if ($user && method_exists($user, 'isSuperAdmin') && !$user->isSuperAdmin()) {
                $builder->where($model->getTable() . '.company_id', $user->company_id);
            }
        }
    }
}
