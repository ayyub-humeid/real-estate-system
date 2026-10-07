<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ConstructionDelay extends Model
{
    use \App\Traits\HasCompany;
    public const REASONS = ['material', 'contractor', 'weather', 'design_change', 'inspection', 'authority', 'other'];
    protected $fillable = ['company_id', 'construction_work_package_id', 'construction_work_package_task_id', 'baseline_end_date', 'revised_end_date', 'reason_code', 'description', 'reported_at', 'reported_by'];
    protected $casts = ['baseline_end_date' => 'date', 'revised_end_date' => 'date', 'reported_at' => 'date'];
    public function workPackage(): BelongsTo
    {
        return $this->belongsTo(ConstructionWorkPackage::class, 'construction_work_package_id');
    }
    public function task(): BelongsTo
    {
        return $this->belongsTo(ConstructionWorkPackageTask::class, 'construction_work_package_task_id');
    }
    public function reportedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reported_by');
    }
    public function getDelayDaysAttribute(): int
    {
        return $this->baseline_end_date->diffInDays($this->revised_end_date);
    }
}
