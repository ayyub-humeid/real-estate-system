<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ConstructionIssue extends Model
{
    use \App\Traits\HasCompany;

    protected $attributes = ['status' => 'open'];
    public const STATUSES = ['open', 'in_progress', 'resolved', 'closed'];
    public const SEVERITIES = ['low', 'medium', 'high', 'critical'];
    protected $fillable = ['company_id', 'construction_work_package_id', 'construction_work_package_task_id', 'assigned_to_user_id', 'title', 'description', 'severity', 'opened_at'];
    protected $casts = ['opened_at' => 'date', 'resolved_at' => 'date'];
    public function workPackage(): BelongsTo
    {
        return $this->belongsTo(ConstructionWorkPackage::class, 'construction_work_package_id');
    }
    public function task(): BelongsTo
    {
        return $this->belongsTo(ConstructionWorkPackageTask::class, 'construction_work_package_task_id');
    }
    public function assignedTo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to_user_id');
    }
    public function resolvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }
}
