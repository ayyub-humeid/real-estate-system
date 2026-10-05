<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
class DesignPackageSubmissionDocument extends Model
{
    use \App\Traits\HasCompany;
    protected $fillable = ['company_id', 'design_package_submission_id', 'document_version_id', 'purpose', 'notes'];
    public function submission(): BelongsTo
    {
        return $this->belongsTo(DesignPackageSubmission::class, 'design_package_submission_id');
    }
    public function documentVersion(): BelongsTo
    {
        return $this->belongsTo(DocumentVersion::class);
    }
}
