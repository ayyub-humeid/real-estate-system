<?php

namespace App\Models;

use App\Enums\{CrmActivityOutcome, CrmActivityType};
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CrmActivity extends Model
{
    use \App\Traits\HasCompany;
    protected $fillable = ['company_id', 'lead_id', 'opportunity_id', 'task_id', 'type', 'outcome', 'performed_by', 'notes', 'occurred_at'];
    protected $casts = [
        'type' => CrmActivityType::class,
        'outcome' => CrmActivityOutcome::class,
        'occurred_at' => 'datetime'
    ];
    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }
    public function opportunity(): BelongsTo
    {
        return $this->belongsTo(Opportunity::class);
    }
    public function task(): BelongsTo
    {
        return $this->belongsTo(CrmFollowUpTask::class, 'task_id');
    }
    public function performedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'performed_by');
    }
}
