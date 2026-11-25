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
        'ended_at',
        'end_reason',
        'ended_by',
        'created_by'
    ];
    protected $casts = [
        'contract_type' => ContractType::class,
        'status' => ContractStatus::class,
        'ended_at' => 'date',
    ];
    /**
     * A name people can recognise: what the contract is about, e.g. "A-304 · Marina Tower · Sale".
     * The contract number stays the exact identifier; this is what to show first.
     */
    public function displayName(bool $withType = true): string
    {
        $subject = $this->unit?->unit_number ?: $this->plannedUnit?->code;
        $place = $this->property?->name ?: $this->project?->name;
        $type = $withType && $this->contract_type instanceof ContractType ? $this->contract_type->getLabel() : null;
        $name = collect([$subject, $place, $type])->filter()->implode(' · ');

        return $name !== '' ? $name : (string) $this->contract_number;
    }

    /** The name together with the number, for places that must be exact, such as pickers and notifications. */
    public function displayNameWithNumber(): string
    {
        $name = $this->displayName();

        return $name === (string) $this->contract_number ? $name : $name.' ('.$this->contract_number.')';
    }

    /** The project a contract belongs to, even when it was written against a unit, planned unit or property instead. */
    public function effectiveProject(): ?Project
    {
        return $this->project
            ?? $this->unit?->effectiveProject()
            ?? $this->plannedUnit?->floor?->building?->project
            ?? $this->property?->currentProject();
    }

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
    public function paymentSchedules(): HasMany
    {
        return $this->hasMany(ContractPaymentSchedule::class)->orderByDesc('revision_number');
    }
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }
    public function financialEvents(): HasMany
    {
        return $this->hasMany(ContractFinancialEvent::class)->latest('occurred_at');
    }
}
