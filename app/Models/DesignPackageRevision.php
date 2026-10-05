<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
class DesignPackageRevision extends Model
{
    use \App\Traits\HasCompany;
    public const STATUSES = ['draft', 'in_progress', 'ready', 'submitted', 'cancelled'];
    protected $fillable = ['company_id', 'project_design_package_id', 'source_submission_id', 'revision_number', 'description', 'started_at', 'ready_at', 'created_by'];
    protected $casts = ['started_at' => 'datetime', 'ready_at' => 'datetime'];
    public function package(): BelongsTo
    {
        return $this->belongsTo(ProjectDesignPackage::class, 'project_design_package_id');
    }
    public function sourceSubmission(): BelongsTo
    {
        return $this->belongsTo(DesignPackageSubmission::class, 'source_submission_id');
    }
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
    public function findingLinks(): HasMany
    {
        return $this->hasMany(DesignRevisionFinding::class);
    }
}
