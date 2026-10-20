<?php
namespace App\Models;
use App\Enums\ContractType;
use App\Traits\HasCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
class ContractTemplate extends Model
{
    use HasCompany;
    protected $fillable = ['company_id', 'name', 'contract_type', 'is_active'];
    protected $casts = ['contract_type' => ContractType::class, 'is_active' => 'boolean'];
    public function versions(): HasMany
    {
        return $this->hasMany(ContractTemplateVersion::class);
    }
}
