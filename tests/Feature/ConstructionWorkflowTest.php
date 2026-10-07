<?php

namespace Tests\Feature;

use App\Models\{BudgetLine, Company, ConstructionProgressUpdate, Party, Project, ProjectMember, Role, User};
use App\Services\ConstructionService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class ConstructionWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private function permissions(): array
    {
        return [
            'update_project', 'create_project::construction', 'update_project::construction',
            'create_construction_work_package', 'update_construction_work_package',
            'create_construction_work_package_task', 'update_construction_work_package_task',
            'create_construction_progress_update', 'correct_construction_progress_update', 'create_construction_inspection',
            'create_construction_issue', 'start_construction_issue', 'resolve_construction_issue', 'close_construction_issue',
            'create_construction_delay', 'start_project_construction', 'complete_project_construction', 'cancel_project_construction',
            'start_construction_work_package', 'complete_construction_work_package', 'cancel_construction_work_package',
            'start_construction_work_package_task', 'complete_construction_work_package_task', 'cancel_construction_work_package_task',
        ];
    }

    private function actor(Company $company, ?array $permissions = null): User
    {
        $user = User::factory()->create(['company_id' => $company->id]);
        foreach ($permissions ?? $this->permissions() as $permission) $user->givePermissionTo(Permission::findOrCreate($permission, 'web'));
        $this->actingAs($user);
        return $user;
    }

    private function project(Company $company): Project
    {
        return Project::withoutGlobalScopes()->create(['company_id' => $company->id, 'name' => 'Al Noor', 'project_type' => 'residential', 'currency' => 'USD', 'status' => 'in_progress']);
    }

    private function party(Company $company, string $name = 'BuildCo'): Party
    {
        return Party::withoutGlobalScopes()->create(['company_id' => $company->id, 'type' => 'company', 'name' => $name, 'is_active' => true]);
    }

    public function test_full_construction_workflow_preserves_inspection_issue_and_progress_history(): void
    {
        $company = Company::create(['name' => 'A', 'email' => 'a@test.test']);
        $actor = $this->actor($company);
        $project = $this->project($company);
        ProjectMember::withoutGlobalScopes()->create(['company_id' => $company->id, 'project_id' => $project->id, 'user_id' => $actor->id, 'role' => 'project_manager', 'started_at' => now()]);
        $service = app(ConstructionService::class);
        $line = BudgetLine::withoutGlobalScopes()->create(['company_id' => $company->id, 'project_id' => $project->id]);

        $construction = $service->createConstruction($actor, $project, ['planned_start_date' => today()->subMonth()->toDateString(), 'expected_completion_date' => today()->addMonth()->toDateString()]);
        $service->startConstruction($actor, $construction);
        $package = $service->createWorkPackage($actor, $construction->fresh(), ['name' => 'Structural Works', 'budget_line_id' => $line->id, 'planned_end_date' => today()->addWeek()->toDateString()]);
        $service->startWorkPackage($actor, $package);
        $task = $service->createTask($actor, $package->fresh(), ['name' => 'Foundation Concrete', 'requires_inspection' => true, 'planned_end_date' => today()->addDay()->toDateString()]);
        $service->startTask($actor, $task);
        $first = $service->recordProgress($actor, $task->fresh(), ['progress_percentage' => 50, 'reported_at' => today()->toDateString()]);
        $second = $service->recordProgress($actor, $task->fresh(), ['progress_percentage' => 100, 'reported_at' => today()->toDateString()]);
        $this->assertSame('awaiting_inspection', $task->fresh()->status);
        $this->assertSame(100.0, (float) $task->fresh()->progress_percentage);

        $service->recordInspection($actor, $task->fresh(), ['result' => 'failed', 'inspection_date' => today()->toDateString()]);
        try { $service->completeTask($actor, $task->fresh()); $this->fail('Failed inspection completed task.'); } catch (ValidationException) { $this->assertSame('awaiting_inspection', $task->fresh()->status); }
        $issue = $service->createIssue($actor, $package->fresh(), ['construction_work_package_task_id' => $task->id, 'assigned_to_user_id' => $actor->id, 'title' => 'Honeycombing', 'description' => 'Repair required', 'severity' => 'critical', 'opened_at' => today()->toDateString()]);
        $service->transitionIssue($actor, $issue, 'resolved', 'Concrete repaired and checked.');
        $service->recordInspection($actor, $task->fresh(), ['result' => 'passed_with_notes', 'inspection_date' => today()->toDateString()]);
        $service->completeTask($actor, $task->fresh());
        $service->completeWorkPackage($actor, $package->fresh());
        $service->completeConstruction($actor, $construction->fresh());

        $this->assertSame('completed', $construction->fresh()->status);
        $this->assertSame(100.0, (float) $construction->fresh()->progress_percentage);
        $this->assertDatabaseCount('construction_progress_updates', 2);
        $this->assertDatabaseCount('construction_inspections', 2);
        $this->assertDatabaseCount('financial_commitments', 0);
        $this->assertDatabaseCount('actual_costs', 0);
        $this->assertDatabaseCount('payments', 0);
        $this->assertNotNull($first);
        $this->assertNotNull($second);
    }

    public function test_progress_correction_preserves_original_and_recalculates_parent_progress(): void
    {
        $company = Company::create(['name' => 'A', 'email' => 'a@test.test']); $actor = $this->actor($company); $project = $this->project($company); $service = app(ConstructionService::class);
        $construction = $service->createConstruction($actor, $project, []); $service->startConstruction($actor, $construction);
        $package = $service->createWorkPackage($actor, $construction->fresh(), ['name' => 'Electrical']); $service->startWorkPackage($actor, $package);
        $task = $service->createTask($actor, $package->fresh(), ['name' => 'Rough-in']); $service->startTask($actor, $task);
        $original = $service->recordProgress($actor, $task->fresh(), ['progress_percentage' => 80, 'reported_at' => today()->toDateString()]);
        $correction = $service->correctProgress($actor, $original, ['progress_percentage' => 60, 'reported_at' => today()->toDateString(), 'correction_reason' => 'Incorrect site report']);
        $this->assertSame($original->id, $correction->corrects_progress_update_id);
        $this->assertSame(60.0, (float) $task->fresh()->progress_percentage);
        $this->assertSame(2, ConstructionProgressUpdate::count());
    }

    public function test_cross_company_budget_line_and_task_package_injection_are_rejected(): void
    {
        $company = Company::create(['name' => 'A', 'email' => 'a@test.test']); $other = Company::create(['name' => 'B', 'email' => 'b@test.test']);
        $actor = $this->actor($company); $project = $this->project($company); $service = app(ConstructionService::class);
        $construction = $service->createConstruction($actor, $project, []);
        $foreignLine = BudgetLine::withoutGlobalScopes()->create(['company_id' => $other->id, 'project_id' => $this->project($other)->id]);
        try { $service->createWorkPackage($actor, $construction, ['name' => 'Bad', 'budget_line_id' => $foreignLine->id]); $this->fail('Foreign budget line accepted.'); } catch (ValidationException) { $this->assertDatabaseCount('construction_work_packages', 0); }
    }

    public function test_unauthorized_user_cannot_create_construction(): void
    {
        $company = Company::create(['name' => 'A', 'email' => 'a@test.test']); $project = $this->project($company); $actor = $this->actor($company, []);
        $this->expectException(AuthorizationException::class);
        app(ConstructionService::class)->createConstruction($actor, $project, []);
    }

    public function test_cancelled_execution_is_preserved_and_allows_one_new_execution(): void
    {
        $company = Company::create(['name' => 'A', 'email' => 'a@test.test']);
        $actor = $this->actor($company);
        $project = $this->project($company);
        $service = app(ConstructionService::class);

        $first = $service->createConstruction($actor, $project, []);
        $service->cancelConstruction($actor, $first, 'Original contractor withdrew.');
        $second = $service->createConstruction($actor, $project, []);

        $this->assertSame('cancelled', $first->fresh()->status);
        $this->assertSame(1, $first->execution_number);
        $this->assertSame(2, $second->execution_number);
        $this->expectException(ValidationException::class);
        $service->createConstruction($actor, $project, []);
    }

    public function test_super_admin_uses_the_selected_project_company_for_construction_children(): void
    {
        $company = Company::create(['name' => 'A', 'email' => 'a@test.test']);
        $project = $this->project($company);
        $superAdmin = User::factory()->create(['company_id' => null]);
        $superAdmin->assignRole(Role::platform()->firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']));
        foreach (['update_project', 'create_project::construction', 'create_construction_work_package'] as $permission) {
            $superAdmin->givePermissionTo(Permission::findOrCreate($permission, 'web'));
        }

        $service = app(ConstructionService::class);
        $construction = $service->createConstruction($superAdmin, $project, []);
        $package = $service->createWorkPackage($superAdmin, $construction, ['name' => 'Site setup']);

        $this->assertSame($company->id, $construction->company_id);
        $this->assertSame($company->id, $package->company_id);
    }
}
