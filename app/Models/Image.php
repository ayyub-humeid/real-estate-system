<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Image extends Model
{
    protected $fillable = [
        'imageable_type',
        'imageable_id',
        'path',
        'disk',
        'is_primary',
        'order',
    ];

    protected $casts = [
        'is_primary' => 'boolean',
        'order' => 'integer',
    ];

    // --- Polymorphic Relationship ---

    /**
     * Get the parent imageable model (Property, Unit, etc.)
     */
    public function imageable(): MorphTo
    {
        return $this->morphTo();
    }

    // --- Helper ---

    /**
     * Get the full URL to the image.
     */
    public function getUrlAttribute(): string
    {
        return $this->path ? asset('storage/' . $this->path) : '';
    }
}
