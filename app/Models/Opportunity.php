<?php
namespace App\Models;
use App\Enums\{CrmInquirySource, OpportunityLostReason, OpportunityType};
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, HasMany};
class Opportunity extends Model
{
    use \App\Traits\HasCompany;
    protected $fillable = ['company_id', 'lead_id', 'listing_id', 'pipeline_id', 'pipeline_stage_id', 'type', 'assigned_to', 'budget_min', 'budget_max', 'currency', 'preferred_property_type', 'preferred_location', 'preferred_bedrooms', 'expected_close_date', 'notes', 'source', 'source_reference', 'lost_reason', 'closure_notes', 'closed_at', 'created_by', 'updated_by'];
    protected $casts = ['type' => OpportunityType::class, 'source' => CrmInquirySource::class, 'lost_reason' => OpportunityLostReason::class, 'budget_min' => 'decimal:2', 'budget_max' => 'decimal:2', 'expected_close_date' => 'date', 'closed_at' => 'datetime'];
    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }
    public function listing(): BelongsTo
    {
        return $this->belongsTo(Listing::class);
    }
    public function pipeline(): BelongsTo
    {
        return $this->belongsTo(CrmPipeline::class, 'pipeline_id');
    }
    public function stage(): BelongsTo
    {
        return $this->belongsTo(CrmPipelineStage::class, 'pipeline_stage_id');
    }
    public function assignedTo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }
    public function requirementCompletions(): HasMany
    {
        return $this->hasMany(OpportunityRequirementCompletion::class);
    }
    public function stageHistories(): HasMany
    {
        return $this->hasMany(OpportunityStageHistory::class)->orderByDesc('changed_at')->orderByDesc('id');
    }
    public function followUpTasks(): HasMany
    {
        return $this->hasMany(CrmFollowUpTask::class);
    }
    public function activities(): HasMany
    {
        return $this->hasMany(CrmActivity::class)->orderByDesc('occurred_at')->orderByDesc('id');
    }
    public function offers(): HasMany
    {
        return $this->hasMany(CrmOffer::class)->orderByDesc('created_at')->orderByDesc('id');
    }
}
