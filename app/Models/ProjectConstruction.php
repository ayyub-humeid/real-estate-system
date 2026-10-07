<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProjectConstruction extends Model
{
    use \App\Traits\HasCompany;

    public const STATUSES = ['planned', 'in_progress', 'completed', 'cancelled'];

    protected $attributes = ['status' => 'planned', 'progress_percentage' => 0];

    protected $fillable = ['company_id', 'project_id', 'execution_number', 'planned_start_date', 'expected_completion_date', 'manager_party_id', 'notes'];
    protected $casts = ['planned_start_date' => 'date', 'expected_completion_date' => 'date', 'actual_start_date' => 'date', 'actual_completion_date' => 'date', 'cancelled_at' => 'datetime', 'progress_percentage' => 'decimal:2'];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }
    public function managerParty(): BelongsTo
    {
        return $this->belongsTo(Party::class, 'manager_party_id');
    }
    public function workPackages(): HasMany
    {
        return $this->hasMany(ConstructionWorkPackage::class);
    }
}
