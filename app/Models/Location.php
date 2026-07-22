<?php

namespace App\Models;

use App\Traits\HasCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Location extends Model
{
    use HasCompany;
    protected $fillable = [
        'company_id',
        'parent_id',
        'name',
        'type',
        'latitude',
        'longitude',
    ];
    protected $casts = [
        'latitude' => 'decimal:8',
        'longitude' => 'decimal:8',
    ];
    // Parent location (e.g., city belongs to country)
    public function parent(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'parent_id');
    }
    // Child locations (e.g., country has many cities)
    public function children(): HasMany
    {
        return $this->hasMany(Location::class, 'parent_id');
    }
    public function properties(): HasMany
    {
        return $this->hasMany(Property::class);
    }

    // Helper: Get full location path (e.g., "USA > California > Los Angeles")
    public function getFullPathAttribute(): string
    {
        $path = [$this->name];
        $parent = $this->parent;

        while ($parent) {
            array_unshift($path, $parent->name);
            $parent = $parent->parent;
        }

        return implode(' > ', $path);
    }
}
