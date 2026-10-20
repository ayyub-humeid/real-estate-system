<?php
namespace App\Models;
use App\Enums\{ContractStatus, ContractType, ContractVersionStatus};
use App\Traits\HasCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, HasMany, HasManyThrough};
class Contract extends Model
{
    use HasCompany;
    protected $fillable = [
        'company_id',
        'contract_number',
        'contract_type',
        'status',
        'reservation_id',
        'property_id',
        'project_id',
        'unit_id',
        'planned_unit_id',
        'created_by'
    ];
    protected $casts = [
        'contract_type' => ContractType::class,
        'status' => ContractStatus::class,
    ];
    public function versions(): HasMany
    {
        return $this->hasMany(ContractVersion::class);
    }
    public function reservation(): BelongsTo
    {
        return $this->belongsTo(UnitReservation::class);
    }
    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }
    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }
    public function plannedUnit(): BelongsTo
    {
        return $this->belongsTo(ProjectPlannedUnit::class, 'planned_unit_id');
    }
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
    public function drafts(): HasMany
    {
        return $this->versions()->where('status', ContractVersionStatus::Draft->value);
    }
    public function statusHistories(): HasMany { return $this->hasMany(ContractStatusHistory::class)->latest('changed_at'); }
    public function amendments(): HasMany { return $this->hasMany(ContractAmendment::class); }
    public function approvals(): HasManyThrough { return $this->hasManyThrough(ContractApproval::class, ContractVersion::class, 'contract_id', 'contract_version_id'); }
    public function signatures(): HasManyThrough { return $this->hasManyThrough(ContractSignature::class, ContractVersion::class, 'contract_id', 'contract_version_id'); }
    public function documents(): HasManyThrough
    {
        return $this->hasManyThrough(Document::class, ContractVersion::class, 'contract_id', 'documentable_id')
            ->where('documents.documentable_type', ContractVersion::class);
    }
}
