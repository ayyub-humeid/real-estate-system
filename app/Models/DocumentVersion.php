<?php

namespace App\Models;

use App\Enums\ContractDocumentType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class DocumentVersion extends Model
{
    use \App\Traits\HasCompany;

    protected $fillable = ['company_id', 'document_id', 'version_number', 'file_name', 'file_path', 'mime_type', 'file_size', 'checksum', 'notes', 'uploaded_by'];
    protected $casts = ['file_size' => 'integer'];

    public function document(): BelongsTo { return $this->belongsTo(Document::class); }
    public function uploadedBy(): BelongsTo { return $this->belongsTo(User::class, 'uploaded_by'); }
    public function designPackageVersion(): HasOne { return $this->hasOne(DesignPackageVersion::class); }
    public function contractSignatures(): HasMany { return $this->hasMany(ContractSignature::class); }

    protected static function booted(): void
    {
        static::deleting(function (self $version): void {
            if ($version->document?->document_type !== ContractDocumentType::Supporting
                || $version->designPackageVersion()->exists() || $version->contractSignatures()->exists()) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'document_version' => __('project.design.errors.file_is_history'),
                ]);
            }
        });
    }
}
