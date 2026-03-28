<?php

namespace App\Traits;

use App\Models\Scopes\CompanyScope;
use Illuminate\Support\Facades\Auth;

trait HasCompany
{
    /**
     * Boot the HasCompany trait for the model.
     */
    protected static function bootHasCompany(): void
    {
        static::addGlobalScope(new CompanyScope);

        static::creating(function ($model) {
            if (empty($model->company_id) && Auth::hasUser()) {
                $user = Auth::user();

                // Only inject company_id if the current user is not a super admin
                if ($user && method_exists($user, 'isSuperAdmin') && !$user->isSuperAdmin()) {
                    $model->company_id = $user->company_id;
                }
            }
        });
    }

    /**
     * Relationship to company.
     */
    public function company(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(\App\Models\Company::class);
    }
}
