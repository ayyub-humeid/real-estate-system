<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
class Party extends Model
{
    use \App\Traits\HasCompany;
    protected $fillable = ['company_id', 'type', 'name', 'legal_name', 'national_id', 'registration_number', 'tax_number', 'phone', 'email', 'address', 'notes', 'is_active'];
    protected $casts = ['is_active' => 'boolean'];
    public function acquisitionParties(): HasMany
    {
        return $this->hasMany(AcquisitionParty::class);
    }
    public function propertyOwnerships(): HasMany
    {
        return $this->hasMany(PropertyOwnership::class);
    }
    public function unitOwnerships(): HasMany
    {
        return $this->hasMany(UnitOwnership::class);
    }
}
