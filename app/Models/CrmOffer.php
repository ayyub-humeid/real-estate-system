<?php

namespace App\Models;

use App\Enums\{OfferRevisionStatus, OfferStatus, OpportunityType};
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, HasMany, HasOne, HasManyThrough};

class CrmOffer extends Model
{
    use \App\Traits\HasCompany;

    protected $fillable = ['company_id', 'opportunity_id', 'listing_id', 'reference_number', 'transaction_type', 'assigned_to', 'created_by'];
    protected $casts = [
        'status' => OfferStatus::class,
        'transaction_type' => OpportunityType::class,
        'closed_at' => 'datetime',
        'next_revision_number' => 'integer'
    ];
    public function opportunity(): BelongsTo
    {
        return $this->belongsTo(Opportunity::class);
    }
    public function listing(): BelongsTo
    {
        return $this->belongsTo(Listing::class);
    }
    public function assignedTo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
    public function revisions(): HasMany
    {
        return $this->hasMany(CrmOfferRevision::class, 'offer_id')->orderByDesc('revision_number');
    }
    public function latestRevision(): HasOne
    {
        return $this->hasOne(CrmOfferRevision::class, 'offer_id')
            ->ofMany('revision_number', 'max');
    }
    public function agreedRevision(): HasOne
    {
        return $this->hasOne(CrmOfferRevision::class, 'offer_id')
            ->where('status', OfferRevisionStatus::Agreed->value);
    }
    public function approvals(): HasManyThrough
    {
        return $this->hasManyThrough(CrmOfferApproval::class, CrmOfferRevision::class, 'offer_id', 'offer_revision_id');
    }
}
