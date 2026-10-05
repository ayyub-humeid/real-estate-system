<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
class DesignPackageApproval extends Model
{
    use \App\Traits\HasCompany;
    protected $fillable = ['company_id', 'project_design_package_id', 'design_package_submission_id', 'approved_by', 'approved_at', 'notes'];
    protected $casts = ['approved_at' => 'datetime'];
    public function package(): BelongsTo
    {
        return $this->belongsTo(ProjectDesignPackage::class, 'project_design_package_id');
    }
    public function submission(): BelongsTo
    {
        return $this->belongsTo(DesignPackageSubmission::class, 'design_package_submission_id');
    }
    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}
