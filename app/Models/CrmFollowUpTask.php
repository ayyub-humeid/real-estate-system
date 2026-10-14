<?php

namespace App\Models;

use App\Enums\{CrmActivityType, CrmTaskStatus};
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, HasOne};

class CrmFollowUpTask extends Model
{
    use \App\Traits\HasCompany;
    protected $fillable = [
        'company_id',
        'lead_id',
        'opportunity_id',
        'created_by',
        'assigned_to',
        'completed_by',
        'type',
        'status',
        'title',
        'description',
        'due_at',
        'completed_at',
        'cancelled_at',
        'reminder_sent_at'
    ];
    protected $casts = [
        'type' => CrmActivityType::class,
        'status' => CrmTaskStatus::class,
        'due_at' => 'datetime',
        'completed_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'reminder_sent_at' => 'datetime'
    ];
    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }
    public function opportunity(): BelongsTo
    {
        return $this->belongsTo(Opportunity::class);
    }
    public function assignedTo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
    public function completedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'completed_by');
    }
    public function activity(): HasOne
    {
        return $this->hasOne(CrmActivity::class, 'task_id');
    }
    public function getIsOverdueAttribute(): bool
    {
        return $this->due_at?->isPast() && !in_array($this->status, [CrmTaskStatus::Completed, CrmTaskStatus::Cancelled], true);
    }
}
