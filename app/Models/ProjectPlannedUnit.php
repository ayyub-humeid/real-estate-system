<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
class ProjectPlannedUnit extends Model
{
    use \App\Traits\HasCompany;
    public const STATUSES = ['planned', 'approved', 'converted', 'cancelled'];
    public const TYPES = [
        'apartment' => 'Apartment',
        'studio' => 'Studio',
        'duplex' => 'Duplex',
        'penthouse' => 'Penthouse',
        'villa' => 'Villa',
        'townhouse' => 'Townhouse',
        'office' => 'Office',
        'retail' => 'Retail',
        'warehouse' => 'Warehouse',
        'parking' => 'Parking Space',
        'storage' => 'Storage',
        'other' => 'Other',
    ];
    protected $fillable = ['company_id', 'project_building_floor_id', 'code', 'unit_type', 'planned_area', 'status', 'description', 'sort_order'];
    protected $casts = ['planned_area' => 'decimal:2'];
    public function floor(): BelongsTo
    {
        return $this->belongsTo(ProjectBuildingFloor::class, 'project_building_floor_id');
    }
    public function specifications(): HasMany
    {
        return $this->hasMany(PlannedUnitSpecification::class);
    }
}
