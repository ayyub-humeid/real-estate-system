<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
class ProjectBuilding extends Model
{
    use \App\Traits\HasCompany;
    public const TYPES = [
        'residential_tower' => 'Residential Tower',
        'villa' => 'Villa',
        'townhouse_block' => 'Townhouse Block',
        'commercial' => 'Commercial',
        'mixed_use' => 'Mixed Use',
        'retail' => 'Retail',
        'office' => 'Office',
        'warehouse' => 'Warehouse',
        'parking' => 'Parking Structure',
        'amenities' => 'Amenities Building',
        'other' => 'Other',
    ];
    protected $fillable = ['company_id', 'project_id', 'name', 'code', 'building_type', 'description', 'sort_order', 'status'];
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }
    public function floors(): HasMany
    {
        return $this->hasMany(ProjectBuildingFloor::class);
    }
}
