<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ConstructionInspection extends Model
{
    use \App\Traits\HasCompany;
    public const RESULTS = ['passed', 'failed', 'passed_with_notes'];
    protected $fillable = ['company_id', 'construction_work_package_task_id', 'inspector_party_id', 'inspection_date', 'result', 'notes', 'recorded_by'];
    protected $casts = ['inspection_date' => 'date'];
    public function task(): BelongsTo
    {
        return $this->belongsTo(ConstructionWorkPackageTask::class, 'construction_work_package_task_id');
    }
    public function inspectorParty(): BelongsTo
    {
        return $this->belongsTo(Party::class, 'inspector_party_id');
    }
    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}
