<?php

namespace Tests\Feature;

use App\Filament\Resources\ProjectResource\Pages\ListProjects;
use App\Models\{BudgetCategory, BudgetItem, BudgetLine, Company, Project, ProjectBudget, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class ProjectDeletionTest extends TestCase
{
    use RefreshDatabase;

    private function project(Company $company): Project
    {
        return Project::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'name' => 'Tower',
            'project_type' => 'residential',
            'currency' => 'USD',
            'status' => 'planning',
        ]);
    }

    private function draftBudget(Project $project): ProjectBudget
    {
        $budget = ProjectBudget::withoutGlobalScopes()->create([
            'company_id' => $project->company_id, 'project_id' => $project->id, 'version_number' => 1, 'name' => 'V1',
        ]);
        $category = BudgetCategory::withoutGlobalScopes()->create([
            'company_id' => $project->company_id, 'project_budget_id' => $budget->id, 'name' => 'Land', 'sort_order' => 1,
        ]);
        $line = BudgetLine::withoutGlobalScopes()->create(['company_id' => $project->company_id, 'project_id' => $project->id]);
        BudgetItem::withoutGlobalScopes()->create([
            'company_id' => $project->company_id, 'budget_category_id' => $category->id, 'budget_line_id' => $line->id,
            'name' => 'Plot', 'planned_amount' => 1000, 'quantity' => 1,
        ]);

        return $budget;
    }

    private function planningStructure(Project $project, User $member): void
    {
        $now = now();
        $buildingId = DB::table('project_buildings')->insertGetId([
            'company_id' => $project->company_id, 'project_id' => $project->id, 'name' => 'A', 'created_at' => $now, 'updated_at' => $now,
        ]);
        $floorId = DB::table('project_building_floors')->insertGetId([
            'company_id' => $project->company_id, 'project_building_id' => $buildingId, 'floor_number' => 1, 'created_at' => $now, 'updated_at' => $now,
        ]);
        $unitId = DB::table('project_planned_units')->insertGetId([
            'company_id' => $project->company_id, 'project_building_floor_id' => $floorId, 'code' => 'A-101', 'unit_type' => 'apartment', 'created_at' => $now, 'updated_at' => $now,
        ]);
        DB::table('planned_unit_specifications')->insert([
            'company_id' => $project->company_id, 'project_planned_unit_id' => $unitId, 'name' => 'Rooms', 'value' => '3', 'created_at' => $now, 'updated_at' => $now,
        ]);
        DB::table('project_members')->insert([
            'company_id' => $project->company_id, 'project_id' => $project->id, 'user_id' => $member->id, 'role' => 'manager', 'started_at' => $now, 'created_at' => $now, 'updated_at' => $now,
        ]);
    }

    public function test_planning_only_project_is_deleted_with_its_planning_children(): void
    {
        $company = Company::create(['name' => 'A', 'email' => 'a@test.com']);
        $user = User::factory()->create(['company_id' => $company->id]);
        $project = $this->project($company);
        $this->draftBudget($project);
        $this->planningStructure($project, $user);

        $this->assertSame([], $project->deletionBlockers());

        $project->delete();

        $this->assertNull(Project::withoutGlobalScopes()->find($project->id));
        foreach (['project_budgets', 'budget_lines', 'project_buildings', 'project_members'] as $table) {
            $this->assertSame(0, DB::table($table)->where('project_id', $project->id)->count(), $table);
        }
        $this->assertSame(0, DB::table('budget_items')->count());
        $this->assertSame(0, DB::table('budget_categories')->count());
        $this->assertSame(0, DB::table('project_building_floors')->count());
        $this->assertSame(0, DB::table('project_planned_units')->count());
        $this->assertSame(0, DB::table('planned_unit_specifications')->count());
    }

    public function test_project_with_history_cannot_be_deleted_and_keeps_its_data(): void
    {
        $company = Company::create(['name' => 'A', 'email' => 'a@test.com']);
        $project = $this->project($company);
        $this->draftBudget($project)->forceFill(['status' => 'approved'])->save();

        $this->assertSame([__('project.delete.blockers.approved_budgets')], $project->deletionBlockers());

        try {
            $project->delete();
            $this->fail('Deleting a project with history must be rejected.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('project', $exception->errors());
        }

        $this->assertNotNull(Project::withoutGlobalScopes()->find($project->id));
        $this->assertSame(1, DB::table('budget_items')->count());
    }

    public function test_delete_action_explains_why_and_keeps_the_project(): void
    {
        $company = Company::create(['name' => 'A', 'email' => 'a@test.com']);
        $user = User::factory()->create(['company_id' => $company->id, 'role' => 'company_admin']);
        foreach (['view_any_project', 'view_project', 'delete_project'] as $permission) {
            $user->givePermissionTo(Permission::findOrCreate($permission, 'web'));
        }
        $this->actingAs($user);

        $project = $this->project($company);
        $this->draftBudget($project)->forceFill(['status' => 'approved'])->save();

        Livewire::test(ListProjects::class)
            ->callTableAction('delete', $project)
            ->assertNotified(__('project.delete.blocked_title'));

        $this->assertNotNull(Project::withoutGlobalScopes()->find($project->id));
    }
}
