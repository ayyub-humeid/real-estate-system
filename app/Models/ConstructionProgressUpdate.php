<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ConstructionProgressUpdate extends Model
{
    use \App\Traits\HasCompany;
    protected $fillable = ['company_id', 'construction_work_package_task_id', 'progress_percentage', 'reported_at', 'notes', 'reported_by', 'corrects_progress_update_id', 'correction_reason'];
    protected $casts = ['progress_percentage' => 'decimal:2', 'reported_at' => 'date'];
    public function task(): BelongsTo
    {
        return $this->belongsTo(ConstructionWorkPackageTask::class, 'construction_work_package_task_id');
    }
    public function correctionOf(): BelongsTo
    {
        return $this->belongsTo(self::class, 'corrects_progress_update_id');
    }
    public function reportedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reported_by');
    }
}
