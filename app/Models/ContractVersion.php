<?php
namespace App\Models;
use App\Enums\ContractVersionStatus;
use App\Traits\HasCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, HasMany, MorphMany};
class ContractVersion extends Model
{
    use HasCompany;
    protected $fillable = ['company_id', 'contract_id', 'version_number', 'status', 'template_version_id', 'total_amount', 'currency', 'start_date', 'end_date', 'delivery_date', 'unit_snapshot', 'reservation_snapshot', 'created_by', 'submitted_at', 'approved_at'];
    protected $casts = ['status' => ContractVersionStatus::class, 'total_amount' => 'decimal:2', 'start_date' => 'date', 'end_date' => 'date', 'delivery_date' => 'date', 'unit_snapshot' => 'array', 'reservation_snapshot' => 'array', 'submitted_at' => 'datetime', 'approved_at' => 'datetime'];
    public function contract(): BelongsTo
    {
        return $this->belongsTo(Contract::class);
    }
    public function parties(): HasMany
    {
        return $this->hasMany(ContractParty::class);
    }
    public function terms(): HasMany
    {
        return $this->hasMany(ContractTerm::class)->orderBy('position');
    }
    public function approvals(): HasMany { return $this->hasMany(ContractApproval::class); }
    public function signatures(): HasMany { return $this->hasMany(ContractSignature::class); }
    public function documents(): MorphMany { return $this->morphMany(Document::class, 'documentable'); }
}
