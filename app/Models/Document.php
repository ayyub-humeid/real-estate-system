<?php
// app/Models/Document.php

namespace App\Models;

use App\Enums\ContractDocumentType;
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
        'document_type',
        'title',
        'description',
        'created_by',
    ];

    protected $casts = ['document_type' => ContractDocumentType::class];

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
            // Design drawing versions and contract signatures pin exact files.
            // Deleting their logical parent would silently destroy that history.
            if (in_array($document->document_type, [ContractDocumentType::OfficialApprovedPdf, ContractDocumentType::SignedFinalPdf], true)
                || $document->versions()->withoutGlobalScopes()
                ->where(function ($query): void {
                    $query->whereHas('designPackageVersion')->orWhereHas('contractSignatures');
                })->exists()) {
                throw ValidationException::withMessages([
                    'document' => __('project.design.errors.file_is_history'),
                ]);
            }
        });
    }
}
