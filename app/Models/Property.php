<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Facades\Auth;

class Property extends Model
{
    use \App\Traits\HasCompany;

    protected $fillable = [
        'company_id',
        'location_id',
        'name',
        'address',
        'type',
        'space',
        'description',
        'rent_price',
    ];

    protected $casts = [
        'rent_price' => 'decimal:2',
    ];

    // --- Relationships ---
    protected static function boot()
{
    parent::boot();

    static::creating(function ($model) {
        if (Auth::hasUser() && !Auth::user()->isSuperAdmin()) {
            if (!Auth::user()->company->canAddProperty()) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'limit' => 'You have reached the maximum number of properties allowed by your plan.',
                ]);
            }
        }
    });
}

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function units(): HasMany
    {
        return $this->hasMany(Unit::class);
    }
    public function ownerships(): HasMany { return $this->hasMany(PropertyOwnership::class); }
    public function activeOwnerships(): HasMany { return $this->ownerships()->whereNull('end_date'); }
    public function acquisitionProperties(): HasMany { return $this->hasMany(AcquisitionProperty::class); }
    public function propertyAcquisitions(): BelongsToMany { return $this->belongsToMany(PropertyAcquisition::class, 'acquisition_properties')->withPivot(['share_percentage', 'allocated_value', 'notes'])->withTimestamps(); }
    public function documents(): MorphMany { return $this->morphMany(Document::class, 'documentable'); }
    public function projectProperties(): HasMany { return $this->hasMany(ProjectProperty::class); }

    /**
     * Polymorphic: all images belonging to this property.
     * Usage: $property->images  (auto-scoped by imageable_type + imageable_id)
     *
     * Performance tip: always eager-load with ->with('images')
     * or use ->with(['images' => fn($q) => $q->where('is_primary', true)])
     * to avoid N+1 queries.
     */
    public function images(): MorphMany
    {
        return $this->morphMany(Image::class, 'imageable')->orderBy('order');
    }

    /**
     * Fast single-query primary image lookup.
     */
    public function primaryImage(): \Illuminate\Database\Eloquent\Relations\MorphOne
    {
        return $this->morphOne(Image::class, 'imageable')
            ->whereRaw('is_primary = true');
    }

    // --- Scopes ---

    public function scopeWithUnits($query)
    {
        return $query->has('units');
    }
}
