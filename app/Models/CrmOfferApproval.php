<?php

namespace App\Models;

use App\Enums\OfferApprovalStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CrmOfferApproval extends Model
{
    use \App\Traits\HasCompany;

    protected $fillable = [
        'company_id',
        'offer_revision_id',
        'requested_by',
        'assigned_approver_id',
        'status',
        'decided_by',
        'decision_notes',
        'decided_at'
    ];
    protected $casts = ['status' => OfferApprovalStatus::class, 'decided_at' => 'datetime'];
    public function revision(): BelongsTo
    {
        return $this->belongsTo(CrmOfferRevision::class, 'offer_revision_id');
    }
    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }
    public function assignedApprover(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_approver_id');
    }
    public function decidedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by');
    }
}
