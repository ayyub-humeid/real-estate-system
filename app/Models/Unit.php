<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Unit extends Model
{
    protected $fillable = [
        'property_id',
        'unit_number',
        'rent_price',
        'status',
        'type',
    ];

    protected $casts = [
        'rent_price' => 'decimal:2',
    ];

    const STATUSES = [
        'available'   => 'Available',
        'occupied'    => 'Occupied',
        'maintenance' => 'Maintenance',
        'reserved'    => 'Reserved',
    ];

    // --- Relationships ---

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    public function maintenanceRequests(): HasMany
    {
        return $this->hasMany(MaintenanceRequest::class);
    }

    public function leases(): HasMany
    {
        return $this->hasMany(Lease::class);
    }

    public function rentalRequests(): HasMany
    {
        return $this->hasMany(RentalRequest::class);
    }

    /**
     * Polymorphic: all images belonging to this unit.
     * Performance tip: always eager-load with ->with('images')
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

    // --- Helpers ---

    public function isAvailable(): bool
    {
        return $this->status === 'available';
    }
}
