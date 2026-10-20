<?php
namespace App\Models;
use App\Traits\HasCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
class ContractSignature extends Model
{
    use HasCompany;
    protected $fillable = ['company_id', 'contract_version_id', 'contract_party_id', 'document_version_id', 'signatory_name', 'signing_method', 'signature_image_path', 'file_sha256', 'evidence', 'signed_at', 'recorded_by'];
    protected $casts = ['evidence' => 'array', 'signed_at' => 'datetime'];
    public function version(): BelongsTo
    {
        return $this->belongsTo(ContractVersion::class, 'contract_version_id');
    }
    public function party(): BelongsTo
    {
        return $this->belongsTo(ContractParty::class, 'contract_party_id');
    }
    public function documentVersion(): BelongsTo
    {
        return $this->belongsTo(DocumentVersion::class);
    }
}
