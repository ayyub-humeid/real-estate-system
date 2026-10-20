<?php
namespace App\Models;
use App\Traits\HasCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
class ContractTemplateVersion extends Model
{
    use HasCompany;
    protected $fillable = ['company_id', 'contract_template_id', 'version_number', 'body', 'clauses', 'created_by'];
    protected $casts = ['clauses' => 'array'];
    public function template(): BelongsTo
    {
        return $this->belongsTo(ContractTemplate::class, 'contract_template_id');
    }
}
