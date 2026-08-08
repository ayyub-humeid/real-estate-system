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

    /**
     * Auto-assign the disk from config when not explicitly set.
     */
    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (self $image) {
            if (empty($image->disk)) {
                $image->disk = config('filesystems.default', 'public');
            }
        });
    }

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
     * Uses the stored disk column so R2-uploaded images get the R2 URL,
     * and locally-stored images still resolve via the public disk.
     */
    public function getUrlAttribute(): string
    {
        if (str_starts_with($this->path, 'http')) {
            return $this->path;
        }

        if (!$this->path) {
            return '';
        }

        // $disk = $this->disk ?: config('filesystems.default');

        return \Illuminate\Support\Facades\Storage::url($this->path);
    }
}
