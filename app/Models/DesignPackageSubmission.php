<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
class DesignPackageSubmission extends Model
{
    use \App\Traits\HasCompany;
    public const STATUSES = [
        'submitted',
        'under_review',
        'reviewed',
        'superseded',
        'approved'
    ];
    protected $fillable = [
        'company_id',
        'project_design_package_id',
        'design_package_assignment_id',
        'submission_number',
        'submitted_at',
        'submitted_by',
        'notes'
    ];
    protected $casts = ['submitted_at' => 'datetime'];
    public function package(): BelongsTo
    {
        return $this->belongsTo(ProjectDesignPackage::class, 'project_design_package_id');
    }
    public function assignment(): BelongsTo
    {
        return $this->belongsTo(DesignPackageAssignment::class, 'design_package_assignment_id');
    }
    public function submittedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }
    public function documents(): HasMany
    {
        return $this->hasMany(DesignPackageSubmissionDocument::class);
    }
    public function reviews(): HasMany
    {
        return $this->hasMany(DesignPackageReview::class);
    }
    public function revisions(): HasMany
    {
        return $this->hasMany(DesignPackageRevision::class, 'source_submission_id');
    }
}
