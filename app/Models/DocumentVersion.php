<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DocumentVersion extends Model
{
    use \App\Traits\HasCompany;

    protected $fillable = ['company_id', 'document_id', 'version_number', 'file_name', 'file_path', 'mime_type', 'file_size', 'checksum', 'notes', 'uploaded_by'];
    protected $casts = ['file_size' => 'integer'];

    public function document(): BelongsTo { return $this->belongsTo(Document::class); }
    public function uploadedBy(): BelongsTo { return $this->belongsTo(User::class, 'uploaded_by'); }
    public function submissionDocuments(): HasMany { return $this->hasMany(DesignPackageSubmissionDocument::class); }
    public function findings(): HasMany { return $this->hasMany(DesignReviewFinding::class); }

    protected static function booted(): void
    {
        static::deleting(function (self $version): void {
            if ($version->submissionDocuments()->exists()) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'document_version' => 'A document version pinned to a formal submission cannot be deleted.',
                ]);
            }
        });
    }
}
