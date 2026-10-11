<?php

namespace App\Models;

use App\Enums\{ListingStatus, ListingType, RentFrequency};
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, MorphTo, HasMany};

class Listing extends Model
{
    use \App\Traits\HasCompany;

    protected $fillable = [
        'company_id',
        'listable_type',
        'listable_id',
        'listing_type',
        'title',
        'description',
        'asking_price',
        'currency',
        'rent_frequency',
        'security_deposit',
        'minimum_lease_months',
        'availability_notes',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'listing_type' => ListingType::class,
        'status' => ListingStatus::class,
        'rent_frequency' => RentFrequency::class,
        'asking_price' => 'decimal:2',
        'security_deposit' => 'decimal:2',
        'minimum_lease_months' => 'integer',
        'published_at' => 'datetime',
        'paused_at' => 'datetime',
        'archived_at' => 'datetime',
    ];

    public function listable(): MorphTo
    {
        return $this->morphTo();
    }
    public function priceHistories(): HasMany
    {
        return $this->hasMany(ListingPriceHistory::class)->orderByDesc('created_at')->orderByDesc('id');
    }
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function getTargetLabelAttribute(): string
    {
        return match ($this->listable_type) {
            'unit' => 'Actual Unit: ' . ($this->listable?->unit_number ?? "#{$this->listable_id}"),
            'planned_unit' => 'Planned Unit: ' . ($this->listable?->code ?? "#{$this->listable_id}"),
            default => "Target #{$this->listable_id}",
        };
    }
}
