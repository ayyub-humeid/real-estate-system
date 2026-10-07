<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ConstructionWorkPackage extends Model
{
    use \App\Traits\HasCompany;

    public const STATUSES = ['planned', 'in_progress', 'completed', 'cancelled'];

    protected $attributes = ['status' => 'planned', 'progress_percentage' => 0];

    protected $fillable = ['company_id', 'project_construction_id', 'budget_line_id', 'responsible_party_id', 'name', 'code', 'description', 'planned_start_date', 'planned_end_date', 'notes'];
    protected $casts = ['planned_start_date' => 'date', 'planned_end_date' => 'date', 'actual_start_date' => 'date', 'actual_end_date' => 'date', 'cancelled_at' => 'datetime', 'progress_percentage' => 'decimal:2'];

    public function construction(): BelongsTo
    {
        return $this->belongsTo(ProjectConstruction::class, 'project_construction_id');
    }
    public function budgetLine(): BelongsTo
    {
        return $this->belongsTo(BudgetLine::class);
    }
    public function responsibleParty(): BelongsTo
    {
        return $this->belongsTo(Party::class, 'responsible_party_id');
    }
    public function tasks(): HasMany
    {
        return $this->hasMany(ConstructionWorkPackageTask::class);
    }
    public function issues(): HasMany
    {
        return $this->hasMany(ConstructionIssue::class);
    }
    public function delays(): HasMany
    {
        return $this->hasMany(ConstructionDelay::class);
    }
}
