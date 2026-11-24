<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Plan extends Model
{
    use \App\Models\Concerns\PreventsDeletionWithHistory;

    protected function deletionChecks(): array
    {
        return [
            'subscriptions' => fn (): bool => \Illuminate\Support\Facades\DB::table('subscriptions')->where('plan_id', $this->id)->exists(),
        ];
    }

    protected $fillable = [
        'name',
        'slug',
        'description',
        'price',
        'annual_price',
        'trial_days',
        'billing_cycle',
        'features',
        'is_active',
    ];

    protected $casts = [
        'features' => 'array',
        'is_active' => 'boolean',
        'price' => 'decimal:2',
        'annual_price' => 'decimal:2',
        'trial_days' => 'integer',
    ];

    public function subscriptions(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Subscription::class);
    }
}
