<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class Unit extends Model
{
    use \App\Traits\HasCompany;

    protected static function boot()
    {
        parent::boot();

        // existing - keep this
        static::creating(function ($model) {
            if (empty($model->company_id) && $model->property_id) {
                $property = Property::find($model->property_id);
                if ($property) {
                    $model->company_id = $property->company_id;
                }
            }
        });

        // ADD THIS
        static::creating(function ($model) {
            if (Auth::hasUser() && !Auth::user()->isSuperAdmin()) {
                if (!Auth::user()->company->canAddUnit()) {
                    throw \Illuminate\Validation\ValidationException::withMessages([
                        'limit' => 'You have reached the maximum number of units allowed by your plan.',
                    ]);
                }
            }
        });
    }

    protected $fillable = [
        'company_id',
        'property_id',
        'unit_number',
        'rent_price',
        'status',
        'type',
        'is_featured',
        'bedrooms',
        'bathrooms',
        'sqft'
    ];

    protected $casts = [
        'rent_price' => 'decimal:2',
    ];

    const STATUSES = [
        'available' => 'Available',
        'occupied' => 'Occupied',
        'maintenance' => 'Maintenance',
        'reserved' => 'Reserved',
    ];

    // --- Relationships ---

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function maintenanceRequests(): HasMany
    {
        return $this->hasMany(MaintenanceRequest::class);
    }

    public function leases(): HasMany
    {
        return $this->hasMany(Lease::class);
    }

    public function features(): HasMany
    {
        return $this->hasMany(UnitFeature::class);
    }

    public function ratings(): HasMany
    {
        return $this->hasMany(Rating::class);
    }
    public function rentalRequest(): HasMany
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
        $result = $this->morphOne(Image::class, 'imageable')
            ->where('is_primary', true);
        return $result;
    }

    // --- Helpers ---

    public function isAvailable(): bool
    {
        return $this->status === 'available';
    }

    // --- Scopes ---

    public function scopeAvailable($query)
    {
        return $query->where('status', 'available');
    }
    public function scopeFeatured($query)
    {
        return $query->where('is_featured', 1);
    }

    public function scopeOccupied($query)
    {
        return $query->where('status', 'occupied');
    }

    public function scopeMaintenance($query)
    {
        return $query->where('status', 'maintenance');
    }

    public function scopeByType($query, $type)
    {
        return $query->where('type', $type);
    }

    public function scopeFilter($query, array $filters)
    {
        $query->when($filters['search'] ?? null, function ($query, $search) {
            $query->where(function ($q) use ($search) {
                $q->where('unit_number', 'like', "%{$search}%")
                  ->orWhereHas('property', function ($q) use ($search) {
                      $q->where('name', 'like', "%{$search}%")
                        ->orWhere('address', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%");
                  });
            });
        });

        $query->when($filters['type'] ?? null, function ($query, $type) {
            if ($type !== 'All') {
                $query->where('type', $type);
            }
        });

        $query->when($filters['min_price'] ?? null, function ($query, $minPrice) {
            $query->where('rent_price', '>=', $minPrice);
        });

        $query->when($filters['max_price'] ?? null, function ($query, $maxPrice) {
            $query->where('rent_price', '<=', $maxPrice);
        });

        $query->when($filters['bedrooms'] ?? null, function ($query, $bedrooms) {
            if ($bedrooms !== 'Any') {
                if ($bedrooms === '4+') {
                    $query->where('bedrooms', '>=', 4);
                } else {
                    $query->where('bedrooms', $bedrooms);
                }
            }
        });

        $query->when($filters['amenities'] ?? null, function ($query, $amenities) {
            if (is_array($amenities) && count($amenities) > 0) {
                foreach ($amenities as $amenity) {
                    $query->whereHas('features', function ($q) use ($amenity) {
                        $q->where('name', $amenity)->where('value', 'true');
                    });
                }
            }
        });
    }


    public function currentLease(): HasOne
    {
        return $this->hasOne(Lease::class)->where('status', 'active')->latest();
    }

    public function documents(): MorphMany
    {
        return $this->morphMany(Document::class, 'documentable');
    }
}