<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
class ProjectBuildingFloor extends Model
{
    use \App\Traits\HasCompany;
    protected $fillable = ['company_id', 'project_building_id', 'floor_number', 'label', 'description', 'sort_order'];
    public function building(): BelongsTo
    {
        return $this->belongsTo(ProjectBuilding::class, 'project_building_id');
    }
    public function plannedUnits(): HasMany
    {
        return $this->hasMany(ProjectPlannedUnit::class);
    }
}
