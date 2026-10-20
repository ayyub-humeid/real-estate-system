<?php
namespace App\Models;
use App\Traits\HasCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
class ContractTerm extends Model
{
    use HasCompany;
    protected $fillable = ['company_id', 'contract_version_id', 'contract_party_id', 'term_type', 'position', 'title', 'text', 'metadata'];
    protected $casts = ['metadata' => 'array'];
    public function version(): BelongsTo
    {
        return $this->belongsTo(ContractVersion::class, 'contract_version_id');
    }
}
