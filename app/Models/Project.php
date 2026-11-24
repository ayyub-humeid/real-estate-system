<?php

namespace App\Models;

use App\Enums\ProjectBudgetStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class Project extends Model
{
    use \App\Traits\HasCompany;
    use \App\Models\Concerns\PreventsDeletionWithHistory;

    public const STATUSES = ['planning', 'approved', 'in_progress', 'completed', 'closed', 'cancelled'];
    protected $fillable = ['company_id', 'name', 'project_type', 'currency', 'status', 'start_date', 'expected_completion_date', 'description', 'approved_by', 'approved_at', 'completed_at', 'closed_at', 'cancelled_at', 'cancellation_reason'];
    protected $casts = ['start_date' => 'date', 'expected_completion_date' => 'date', 'approved_at' => 'datetime', 'completed_at' => 'datetime', 'closed_at' => 'datetime', 'cancelled_at' => 'datetime'];

    protected static function booted(): void
    {
        static::creating(function (self $project): void {
            $user = \Illuminate\Support\Facades\Auth::user();

            if ($user && ! $user->isSuperAdmin() && $user->company && ! $user->company->canAddProject()) {
                throw ValidationException::withMessages([
                    'limit' => __('You have reached the maximum number of active projects allowed by your plan. Complete or close a project, or upgrade your plan.'),
                ]);
            }
        });

        static::updating(function (self $project): void {
            if (!$project->isDirty('currency'))
                return;

            $hasFinancialHistory = $project->budgets()->exists()
                || $project->commitments()->exists()
                || $project->actualCosts()->exists()
                || $project->payments()->exists();

            if ($hasFinancialHistory) {
                throw ValidationException::withMessages([
                    'currency' => __('Project currency is locked once financial history exists.'),
                ]);
            }
        });

        // The PreventsDeletionWithHistory guard runs first (trait listeners register
        // before booted()), so this only ever purges pure planning data.
        static::deleting(fn (self $project) => $project->purgePlanningData());
    }

    /** Contract, financial, construction, design and unit history is never erased with the project. */
    protected function deletionChecks(): array
    {
        $has = fn (string $table): \Closure => fn (): bool => DB::table($table)->where('project_id', $this->id)->exists();

        return [
            'contracts' => $has('contracts'),
            'payments' => $has('payments'),
            'commitments' => $has('financial_commitments'),
            'actual_costs' => $has('actual_costs'),
            'constructions' => $has('project_constructions'),
            'design_packages' => $has('project_design_packages'),
            'units' => $has('units'),
            'approved_budgets' => fn (): bool => DB::table('project_budgets')->where('project_id', $this->id)
                ->where('status', '!=', ProjectBudgetStatus::Draft->value)->exists(),
        ];
    }

    /** Removes draft budgets, buildings, members and property links through Eloquent so model events run. */
    private function purgePlanningData(): void
    {
        DB::transaction(function (): void {
            $budgetIds = $this->budgets()->withoutGlobalScopes()->pluck('id');
            $categoryIds = BudgetCategory::withoutGlobalScopes()->whereIn('project_budget_id', $budgetIds)->pluck('id');
            BudgetCategory::withoutGlobalScopes()->whereIn('id', $categoryIds)->update(['parent_id' => null, 'cloned_from_id' => null]);
            BudgetItem::withoutGlobalScopes()->whereIn('budget_category_id', $categoryIds)->get()->each->delete();
            BudgetCategory::withoutGlobalScopes()->whereIn('id', $categoryIds)->get()->each->delete();
            $this->budgets()->withoutGlobalScopes()->get()->each->delete();
            BudgetLine::withoutGlobalScopes()->where('project_id', $this->id)->get()->each->delete();

            $buildingIds = $this->buildings()->withoutGlobalScopes()->pluck('id');
            $floorIds = ProjectBuildingFloor::withoutGlobalScopes()->whereIn('project_building_id', $buildingIds)->pluck('id');
            $plannedUnitIds = ProjectPlannedUnit::withoutGlobalScopes()->whereIn('project_building_floor_id', $floorIds)->pluck('id');
            PlannedUnitSpecification::withoutGlobalScopes()->whereIn('project_planned_unit_id', $plannedUnitIds)->get()->each->delete();
            ProjectPlannedUnit::withoutGlobalScopes()->whereIn('id', $plannedUnitIds)->get()->each->delete();
            ProjectBuildingFloor::withoutGlobalScopes()->whereIn('id', $floorIds)->get()->each->delete();
            $this->buildings()->withoutGlobalScopes()->get()->each->delete();

            $this->members()->withoutGlobalScopes()->get()->each->delete();
            $this->projectProperties()->withoutGlobalScopes()->get()->each->delete();
        });
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
    public function projectProperties(): HasMany
    {
        return $this->hasMany(ProjectProperty::class);
    }
    public function activeProjectProperties(): HasMany
    {
        return $this->projectProperties()->whereNull('detached_at');
    }
    public function members(): HasMany
    {
        return $this->hasMany(ProjectMember::class);
    }
    public function activeMembers(): HasMany
    {
        return $this->members()->whereNull('ended_at');
    }
    public function buildings(): HasMany
    {
        return $this->hasMany(ProjectBuilding::class);
    }
    public function designPackages(): HasMany
    {
        return $this->hasMany(ProjectDesignPackage::class);
    }
    public function budgets(): HasMany
    {
        return $this->hasMany(ProjectBudget::class);
    }
    public function commitments(): HasMany
    {
        return $this->hasMany(FinancialCommitment::class);
    }
    public function actualCosts(): HasMany
    {
        return $this->hasMany(ActualCost::class);
    }
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }
    public function constructions(): HasMany
    {
        return $this->hasMany(ProjectConstruction::class);
    }
    public function activeConstruction(): HasOne
    {
        return $this->hasOne(ProjectConstruction::class)->whereIn('status', ['planned', 'in_progress']);
    }
    public function units(): HasMany
    {
        return $this->hasMany(Unit::class);
    }
    public function contracts(): HasMany
    {
        return $this->hasMany(Contract::class);
    }
}
