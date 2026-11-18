<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class Project extends Model
{
    use \App\Traits\HasCompany;

    public const STATUSES = ['planning', 'approved', 'in_progress', 'completed', 'closed', 'cancelled'];
    protected $fillable = ['company_id', 'name', 'project_type', 'currency', 'status', 'start_date', 'expected_completion_date', 'description', 'approved_by', 'approved_at', 'completed_at', 'closed_at', 'cancelled_at', 'cancellation_reason'];
    protected $casts = ['start_date' => 'date', 'expected_completion_date' => 'date', 'approved_at' => 'datetime', 'completed_at' => 'datetime', 'closed_at' => 'datetime', 'cancelled_at' => 'datetime'];

    protected static function booted(): void
    {
        static::updating(function (self $project): void {
            if (!$project->isDirty('currency'))
                return;

            $hasFinancialHistory = $project->budgets()->exists()
                || $project->commitments()->exists()
                || $project->actualCosts()->exists()
                || $project->payments()->exists();

            if ($hasFinancialHistory) {
                throw ValidationException::withMessages([
                    'currency' => 'Project currency is locked once financial history exists.',
                ]);
            }
        });

        static::deleting(function (self $project): void {
            DB::transaction(function () use ($project): void {
                $projectId = $project->id;

                // ── 1. Construction deep children ──────────────────────────────
                $workPackageIds = DB::table('construction_work_packages')
                    ->whereIn('project_construction_id',
                        DB::table('project_constructions')->where('project_id', $projectId)->pluck('id'))
                    ->pluck('id');
                DB::table('construction_delays')->whereIn('construction_work_package_id', $workPackageIds)->delete();
                DB::table('construction_issues')->whereIn('construction_work_package_id', $workPackageIds)->delete();
                DB::table('construction_work_package_tasks')->whereIn('construction_work_package_id', $workPackageIds)->delete();
                DB::table('construction_work_packages')->whereIn('id', $workPackageIds)->delete();
                DB::table('project_constructions')->where('project_id', $projectId)->delete();

                // ── 2. Design package deep children ───────────────────────────
                $designPackageIds = DB::table('project_design_packages')
                    ->where('project_id', $projectId)->pluck('id');
                $submissionIds = DB::table('design_package_submissions')
                    ->whereIn('project_design_package_id', $designPackageIds)->pluck('id');
                $revisionIds = DB::table('design_package_revisions')
                    ->whereIn('project_design_package_id', $designPackageIds)->pluck('id');
                DB::table('design_revision_findings')->whereIn('design_package_revision_id', $revisionIds)->delete();
                DB::table('design_package_submission_documents')->whereIn('design_package_submission_id', $submissionIds)->delete();
                DB::table('design_package_reviews')->whereIn('design_package_submission_id', $submissionIds)->delete();
                // Note: design_package_approvals FKs both design_package_id and submission_id
                DB::table('design_package_approvals')->whereIn('design_package_id', $designPackageIds)->delete();
                DB::table('design_package_revisions')->whereIn('project_design_package_id', $designPackageIds)->delete();
                DB::table('design_package_submissions')->whereIn('project_design_package_id', $designPackageIds)->delete();
                DB::table('design_package_activities')->whereIn('project_design_package_id', $designPackageIds)->delete();
                DB::table('design_package_assignments')->whereIn('project_design_package_id', $designPackageIds)->delete();
                DB::table('project_design_packages')->where('project_id', $projectId)->delete();

                // ── 3. Contract deep children ─────────────────────────────────
                $contractIds = DB::table('contracts')->where('project_id', $projectId)->pluck('id');
                $versionIds = DB::table('contract_versions')->whereIn('contract_id', $contractIds)->pluck('id');
                $scheduleIds = DB::table('contract_payment_schedules')
                    ->whereIn('contract_id', $contractIds)->pluck('id');
                // Self-referential schedule: clear supersedes_schedule_id first
                DB::table('contract_payment_schedules')
                    ->whereIn('id', $scheduleIds)->update(['supersedes_schedule_id' => null]);
                DB::table('contract_financial_events')->whereIn('contract_id', $contractIds)->delete();
                DB::table('contract_installments')->whereIn('contract_payment_schedule_id', $scheduleIds)->delete();
                DB::table('contract_payment_schedules')->whereIn('id', $scheduleIds)->delete();
                // Clear amendment FKs to versions before deleting versions
                DB::table('contract_amendments')
                    ->whereIn('contract_id', $contractIds)
                    ->update(['base_contract_version_id' => null, 'resulting_contract_version_id' => null]);
                DB::table('contract_approvals')->whereIn('contract_version_id', $versionIds)->delete();
                DB::table('contract_parties')->whereIn('contract_version_id', $versionIds)->delete();
                DB::table('contract_signatures')->whereIn('contract_version_id', $versionIds)->delete();
                DB::table('contract_terms')->whereIn('contract_version_id', $versionIds)->delete();
                DB::table('contract_status_histories')
                    ->whereIn('contract_id', $contractIds)
                    ->orWhereIn('contract_version_id', $versionIds)
                    ->delete();
                DB::table('contract_versions')->whereIn('id', $versionIds)->delete();
                DB::table('contract_amendments')->whereIn('contract_id', $contractIds)->delete();

                // ── 4. Financial obligations ───────────────────────────────────
                $commitmentIds = DB::table('financial_commitments')->where('project_id', $projectId)->pluck('id');
                DB::table('financial_commitment_amendments')->whereIn('financial_commitment_id', $commitmentIds)->delete();
                DB::table('actual_costs')->where('project_id', $projectId)->delete();
                DB::table('financial_commitments')->where('project_id', $projectId)->delete();

                // ── 5. Payments ────────────────────────────────────────────────
                DB::table('payment_allocations')->where('project_id', $projectId)->delete();
                DB::table('payments')->where('project_id', $projectId)->delete();

                // ── 6. Budget structure ────────────────────────────────────────
                $budgetIds = DB::table('project_budgets')->where('project_id', $projectId)->pluck('id');
                $categoryIds = DB::table('budget_categories')->whereIn('project_budget_id', $budgetIds)->pluck('id');
                // Clear self-referential FKs on budget_categories
                DB::table('budget_categories')
                    ->whereIn('id', $categoryIds)
                    ->update(['parent_id' => null, 'cloned_from_id' => null]);
                DB::table('budget_items')->whereIn('budget_category_id', $categoryIds)->delete();
                DB::table('budget_categories')->whereIn('id', $categoryIds)->delete();
                DB::table('project_budgets')->where('project_id', $projectId)->delete();
                DB::table('budget_lines')->where('project_id', $projectId)->delete();

                // ── 7. Buildings / floors / planned units ──────────────────────
                $buildingIds = DB::table('project_buildings')->where('project_id', $projectId)->pluck('id');
                $floorIds = DB::table('project_building_floors')->whereIn('project_building_id', $buildingIds)->pluck('id');
                DB::table('project_planned_units')->whereIn('project_building_floor_id', $floorIds)->delete();
                DB::table('project_building_floors')->whereIn('id', $floorIds)->delete();
                DB::table('project_buildings')->where('project_id', $projectId)->delete();

                // ── 8. Contracts (now safe) ────────────────────────────────────
                DB::table('contracts')->where('project_id', $projectId)->delete();

                // ── 9. Direct children ─────────────────────────────────────────
                DB::table('units')->where('project_id', $projectId)->delete();
                DB::table('project_members')->where('project_id', $projectId)->delete();
                DB::table('project_properties')->where('project_id', $projectId)->delete();
            });
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
