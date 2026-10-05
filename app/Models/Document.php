<?php
// app/Models/Document.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Validation\ValidationException;

class Document extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'documentable_id',
        'documentable_type',
        'title',
        'description',
        'created_by',
    ];

    // Relationships
    public function documentable(): MorphTo
    {
        return $this->morphTo();
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
    public function versions(): HasMany
    {
        return $this->hasMany(DocumentVersion::class);
    }
    public function latestVersion(): HasOne
    {
        return $this->hasOne(DocumentVersion::class)->latestOfMany('version_number');
    }

    protected static function boot()
    {
        parent::boot();

        static::deleting(function ($document) {
            // A formal design submission pins exact document versions. Deleting
            // their logical parent would silently destroy auditable history.
            if ($document->versions()->withoutGlobalScopes()->whereHas('submissionDocuments')->exists()) {
                throw ValidationException::withMessages([
                    'document' => 'A document used by a formal design submission cannot be deleted.',
                ]);
            }
        });
    }
}
