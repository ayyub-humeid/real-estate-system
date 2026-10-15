<?php

namespace App\Models;

use App\Enums\{OfferProposedBySide, OfferRevisionStatus, RentFrequency};
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, HasMany};

class CrmOfferRevision extends Model
{
    use \App\Traits\HasCompany;

    protected $fillable = [
        'company_id',
        'offer_id',
        'revision_number',
        'proposed_by_side',
        'amount',
        'currency',
        'down_payment_amount',
        'payment_method',
        'payment_terms',
        'rent_frequency',
        'lease_term_value',
        'lease_term_unit',
        'deposit_amount',
        'terms_json',
        'notes',
        'expires_at'
    ];
    protected $casts = [
        'status' => OfferRevisionStatus::class,
        'proposed_by_side' => OfferProposedBySide::class,
        'rent_frequency' => RentFrequency::class,
        'amount' => 'decimal:2',
        'down_payment_amount' => 'decimal:2',
        'deposit_amount' => 'decimal:2',
        'terms_json' => 'array',
        'lease_term_value' => 'integer',
        'submitted_at' => 'datetime',
        'expires_at' => 'datetime',
        'confirmed_at' => 'datetime'
    ];
    public function offer(): BelongsTo
    {
        return $this->belongsTo(CrmOffer::class, 'offer_id');
    }
    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
    public function submittedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }
    public function confirmedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'confirmed_by');
    }
    public function approvals(): HasMany
    {
        return $this->hasMany(CrmOfferApproval::class, 'offer_revision_id')->latest();
    }
}
