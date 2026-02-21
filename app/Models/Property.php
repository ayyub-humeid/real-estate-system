<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Property extends Model
{
    protected $fillable = [
        'company_id',
        'location_id',
        'name',
        'address',
        'description',
    ];

    // --- Relationships ---

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function units(): HasMany
    {
        return $this->hasMany(Unit::class);
    }

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
            ->where('is_primary', true);
    }
}
