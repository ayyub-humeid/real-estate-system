<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class ProjectDesignPackage extends Model
{
    use \App\Traits\HasCompany;

    public const STATUSES = [
        'planned',
        'assigned',
        'in_progress',
        'submitted',
        'under_review',
        'revision_required',
        'resubmitted',
        'approved',
        'closed'
    ];
    public const DISCIPLINES = [
        'architectural' => 'Architectural',
        'structural' => 'Structural',
        'electrical' => 'Electrical',
        'mechanical' => 'Mechanical',
        'civil' => 'Civil',
        'interior' => 'Interior',
        'other' => 'Other'
    ];
    protected $fillable = [
        'company_id',
        'project_id',
        'name',
        'code',
        'discipline',
        'description',
        'target_submission_date',
        'created_by'
    ];
    protected $casts = [
        'target_submission_date' => 'date',
        'approved_at' => 'datetime',
        'closed_at' => 'datetime'
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
    public function assignments(): HasMany
    {
        return $this->hasMany(DesignPackageAssignment::class);
    }
    public function activeAssignment(): HasOne
    {
        return $this->hasOne(DesignPackageAssignment::class)->where('status', 'active')->latestOfMany('assigned_at');
    }
    public function scopeItems(): \Illuminate\Database\Eloquent\Relations\HasManyThrough
    {
        return $this->hasManyThrough(
            DesignPackageScopeItem::class,
            DesignPackageAssignment::class,
            'project_design_package_id',
            'design_package_assignment_id'
        );
    }
    public function activities(): HasMany
    {
        return $this->hasMany(DesignPackageActivity::class);
    }
    public function submissions(): HasMany
    {
        return $this->hasMany(DesignPackageSubmission::class);
    }
    public function latestSubmission(): HasOne
    {
        return $this->hasOne(DesignPackageSubmission::class)->latestOfMany('submission_number');
    }
    public function revisions(): HasMany
    {
        return $this->hasMany(DesignPackageRevision::class);
    }
    public function approval(): HasOne
    {
        return $this->hasOne(DesignPackageApproval::class);
    }
    public function documents(): \Illuminate\Database\Eloquent\Relations\MorphMany
    {
        return $this->morphMany(Document::class, 'documentable');
    }
}
