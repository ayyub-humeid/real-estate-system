<?php
namespace App\Models;
use App\Traits\HasCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
class ContractParty extends Model
{
    use HasCompany;
    protected $fillable = ['company_id', 'contract_version_id', 'party_id', 'role', 'share_percentage', 'snapshot'];
    protected $casts = ['snapshot' => 'array', 'share_percentage' => 'decimal:2'];
    public function version(): BelongsTo
    {
        return $this->belongsTo(ContractVersion::class, 'contract_version_id');
    }
    public function party(): BelongsTo
    {
        return $this->belongsTo(Party::class);
    }
}
