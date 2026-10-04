<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
class PlannedUnitSpecification extends Model
{
    use \App\Traits\HasCompany;
    protected $fillable = ['company_id', 'project_planned_unit_id', 'name', 'value', 'unit', 'notes', 'sort_order'];
    public function plannedUnit(): BelongsTo
    {
        return $this->belongsTo(ProjectPlannedUnit::class, 'project_planned_unit_id');
    }
}
