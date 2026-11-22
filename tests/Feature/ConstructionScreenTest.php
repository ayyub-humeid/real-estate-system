<?php

namespace Tests\Feature;

use App\Filament\Resources\ProjectConstructionResource\Pages\ViewProjectConstruction;
use App\Filament\Resources\ProjectConstructionResource\RelationManagers\{DelaysRelationManager, IssuesRelationManager, TasksRelationManager, WorkPackagesRelationManager};
use App\Filament\Resources\ProjectResource\Pages\ViewProject;
use App\Filament\Resources\ProjectResource\RelationManagers\ProjectConstructionRelationManager;
use App\Models\{Company, ConstructionIssue, ConstructionWorkPackage, ConstructionWorkPackageTask, Project, ProjectConstruction, User};
use App\Services\ConstructionService;
use App\Support\ConstructionHealth;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * The construction screen: stages are the milestones, and every action opens on its own row.
 */
class ConstructionScreenTest extends TestCase
{
    use RefreshDatabase;

    private const ALL = [
        'update_project', 'view_any_project::construction', 'view_project::construction', 'create_project::construction',
        'update_project::construction', 'delete_project::construction',
        'create_construction_work_package', 'update_construction_work_package', 'delete_construction_work_package',
        'create_construction_work_package_task', 'update_construction_work_package_task', 'delete_construction_work_package_task',
        'create_construction_progress_update', 'correct_construction_progress_update', 'create_construction_inspection',
        'create_construction_issue', 'start_construction_issue', 'resolve_construction_issue', 'close_construction_issue',
        'create_construction_delay', 'start_project_construction', 'complete_project_construction', 'cancel_project_construction',
        'start_construction_work_package', 'complete_construction_work_package', 'cancel_construction_work_package',
        'start_construction_work_package_task', 'complete_construction_work_package_task', 'cancel_construction_work_package_task',
    ];

    private function actor(Company $company, array $permissions = self::ALL): User
    {
        $user = User::factory()->create(['company_id' => $company->id]);
        foreach ($permissions as $permission) {
            $user->givePermissionTo(Permission::findOrCreate($permission, 'web'));
        }
        $this->actingAs($user);

        return $user;
    }

    private function company(): Company
    {
        return Company::create(['name' => 'Atlas Builders', 'email' => 'atlas@example.test']);
    }

    private function construction(User $actor, Company $company): ProjectConstruction
    {
        $project = Project::withoutGlobalScopes()->create(['company_id' => $company->id, 'name' => 'Al Noor', 'project_type' => 'residential', 'currency' => 'USD', 'status' => 'in_progress']);

        return app(ConstructionService::class)->createConstruction($actor, $project, []);
    }

    private function stages(ProjectConstruction $construction): \Livewire\Features\SupportTesting\Testable
    {
        return Livewire::test(WorkPackagesRelationManager::class, ['ownerRecord' => $construction, 'pageClass' => ViewProjectConstruction::class]);
    }

    public function test_the_page_guides_from_planning_to_a_completed_construction(): void
    {
        $actor = $this->actor($company = $this->company());
        $construction = $this->construction($actor, $company);
        $page = fn () => Livewire::test(ViewProjectConstruction::class, ['record' => $construction->getRouteKey()]);

        $page()->assertSuccessful()
            ->assertSee(__('project.construction.next.planned_empty'))
            ->assertActionVisible('startConstruction')->assertActionHidden('completeConstruction');

        $this->stages($construction)->assertSuccessful()
            ->callTableAction('addPackage', data: ['name' => 'Foundation', 'planned_end_date' => today()->addMonth()->toDateString()])
            ->assertHasNoTableActionErrors()
            ->assertSee('Foundation');

        $page()->assertSee(__('project.construction.next.planned_ready'))
            ->callAction('startConstruction')->assertHasNoActionErrors();
        $this->assertSame('in_progress', $construction->fresh()->status);

        $stage = $construction->workPackages()->firstOrFail();
        $this->stages($construction->fresh())
            ->assertTableActionVisible('startPackage', $stage)->assertTableActionHidden('completePackage', $stage)
            ->callTableAction('startPackage', $stage)->assertHasNoTableActionErrors();
        $this->assertSame('in_progress', $stage->fresh()->status);

        // Task level is optional detail, and every step opens on its own row.
        $this->stages($construction->fresh())
            ->callTableAction('addTask', $stage, data: ['name' => 'Pour concrete'])->assertHasNoTableActionErrors();
        $task = ConstructionWorkPackageTask::withoutGlobalScopes()->firstOrFail();

        $tasks = fn () => Livewire::test(TasksRelationManager::class, ['ownerRecord' => $construction->fresh(), 'pageClass' => ViewProjectConstruction::class]);
        $tasks()->assertSuccessful()->assertSee('Pour concrete')
            ->assertTableActionHidden('recordProgress', $task)
            ->callTableAction('startTask', $task->fresh())->assertHasNoTableActionErrors();
        $tasks()->callTableAction('recordProgress', $task->fresh(), data: ['progress_percentage' => 60, 'reported_at' => today()->toDateString()])
            ->assertHasNoTableActionErrors();
        $this->assertSame(60.0, (float) $task->fresh()->progress_percentage);

        $tasks()->callTableAction('correctProgress', $task->fresh(), data: ['progress_percentage' => 40, 'reported_at' => today()->toDateString(), 'correction_reason' => 'Measured wrongly'])
            ->assertHasNoTableActionErrors();
        $this->assertSame(40.0, (float) $task->fresh()->progress_percentage);

        $tasks()->callTableAction('recordProgress', $task->fresh(), data: ['progress_percentage' => 100, 'reported_at' => today()->toDateString()])
            ->assertTableActionVisible('completeTask', $task->fresh())
            ->callTableAction('completeTask', $task->fresh())->assertHasNoTableActionErrors();
        $this->assertSame('completed', $task->fresh()->status);

        $this->stages($construction->fresh())->callTableAction('completePackage', $stage->fresh())->assertHasNoTableActionErrors();
        $page()->assertSee(__('project.construction.next.all_done'))
            ->callAction('completeConstruction')->assertHasNoActionErrors();

        $this->assertSame('completed', $construction->fresh()->status);
        $page()->assertSee(__('project.construction.next.completed'))->assertActionHidden('startConstruction');
    }

    public function test_a_rule_the_service_enforces_is_shown_as_a_clear_message_not_a_crash(): void
    {
        $actor = $this->actor($company = $this->company());
        $construction = $this->construction($actor, $company);
        $stage = app(ConstructionService::class)->createWorkPackage($actor, $construction, ['name' => 'Structure']);

        // The construction was never started, so the stage cannot start.
        $this->stages($construction->fresh())
            ->callTableAction('startPackage', $stage)
            ->assertNotified(__('crm.errors.action_failed'));
        $this->assertSame('planned', $stage->fresh()->status);
    }

    public function test_a_stage_without_tasks_can_be_completed_from_the_screen(): void
    {
        $actor = $this->actor($company = $this->company());
        $construction = $this->construction($actor, $company);
        $service = app(ConstructionService::class);
        $service->startConstruction($actor, $construction);
        $stage = $service->createWorkPackage($actor, $construction->fresh(), ['name' => 'Site clearing']);
        $service->startWorkPackage($actor, $stage);

        // Tasks are optional detail: with none, the stage closes in one click.
        $this->stages($construction->fresh())
            ->callTableAction('completePackage', $stage->fresh())
            ->assertHasNoTableActionErrors()
            ->assertNotified(__('project.construction.done.package_completed'));

        $this->assertSame('completed', $stage->fresh()->status);
        $this->assertSame(100.0, (float) $stage->fresh()->progress_percentage);
        $this->assertSame(0, ConstructionWorkPackageTask::withoutGlobalScopes()->count());
    }

    public function test_a_stage_with_an_unfinished_task_still_cannot_be_completed(): void
    {
        $actor = $this->actor($company = $this->company());
        $construction = $this->construction($actor, $company);
        $service = app(ConstructionService::class);
        $service->startConstruction($actor, $construction);
        $stage = $service->createWorkPackage($actor, $construction->fresh(), ['name' => 'Structure']);
        $service->startWorkPackage($actor, $stage);
        $task = $service->createTask($actor, $stage->fresh(), ['name' => 'Pour slab']);
        $service->startTask($actor, $task);
        $service->recordProgress($actor, $task->fresh(), ['progress_percentage' => 100, 'reported_at' => today()->toDateString()]);

        // 100% progress is not the same as completed: the task itself must be completed first.
        $this->stages($construction->fresh())
            ->callTableAction('completePackage', $stage->fresh())
            ->assertNotified(__('crm.errors.action_failed'));
        $this->assertSame('in_progress', $stage->fresh()->status);

        $service->completeTask($actor, $task->fresh());
        $this->stages($construction->fresh())
            ->callTableAction('completePackage', $stage->fresh())
            ->assertNotified(__('project.construction.done.package_completed'));
        $this->assertSame('completed', $stage->fresh()->status);
    }

    public function test_schedule_health_flags_overdue_and_attention_stages(): void
    {
        $actor = $this->actor($company = $this->company());
        $construction = $this->construction($actor, $company);
        $service = app(ConstructionService::class);
        $service->startConstruction($actor, $construction);
        $overdue = $service->createWorkPackage($actor, $construction->fresh(), ['name' => 'Late stage', 'planned_end_date' => today()->subWeek()->toDateString()]);
        $risky = $service->createWorkPackage($actor, $construction->fresh(), ['name' => 'Risky stage', 'planned_end_date' => today()->addMonth()->toDateString()]);
        $fine = $service->createWorkPackage($actor, $construction->fresh(), ['name' => 'Fine stage', 'planned_end_date' => today()->addMonth()->toDateString()]);
        $service->createIssue($actor, $risky, ['title' => 'Leak', 'description' => 'Water on site', 'severity' => 'critical', 'opened_at' => today()->toDateString()]);

        $health = fn (ConstructionWorkPackage $package) => ConstructionHealth::forPackage(
            ConstructionWorkPackage::withoutGlobalScopes()->withCount(['delays', 'issues as serious_open_issues_count' => fn ($q) => $q->whereIn('severity', ['high', 'critical'])->whereNotIn('status', ['resolved', 'closed'])])->find($package->id)
        );
        $this->assertSame('overdue', $health($overdue));
        $this->assertSame('attention', $health($risky));
        $this->assertSame('on_track', $health($fine));

        $this->stages($construction->fresh())->assertSuccessful()
            ->assertSee(__('project.construction.schedule.overdue'))
            ->assertSee(__('project.construction.schedule.attention'))
            ->assertSee(__('project.construction.schedule.on_track'));
        Livewire::test(ViewProjectConstruction::class, ['record' => $construction->getRouteKey()])
            ->assertSee(__('project.construction.next.running_overdue', ['progress' => 0, 'overdue' => 1]));
    }

    public function test_issues_and_delays_are_reported_from_a_stage_and_worked_in_their_tabs(): void
    {
        $actor = $this->actor($company = $this->company());
        $construction = $this->construction($actor, $company);
        $service = app(ConstructionService::class);
        $service->startConstruction($actor, $construction);
        $stage = $service->createWorkPackage($actor, $construction->fresh(), ['name' => 'Structure', 'planned_end_date' => today()->addMonth()->toDateString()]);

        $this->stages($construction->fresh())
            ->callTableAction('reportIssue', $stage, data: ['title' => 'Crack in slab', 'description' => 'Hairline crack', 'severity' => 'high', 'opened_at' => today()->toDateString()])
            ->assertHasNoTableActionErrors()
            ->callTableAction('recordDelay', $stage, data: ['revised_end_date' => today()->addMonths(2)->toDateString(), 'reason_code' => 'weather', 'reported_at' => today()->toDateString()])
            ->assertHasNoTableActionErrors();

        $issue = ConstructionIssue::withoutGlobalScopes()->firstOrFail();
        $issues = fn () => Livewire::test(IssuesRelationManager::class, ['ownerRecord' => $construction->fresh(), 'pageClass' => ViewProjectConstruction::class]);
        $issues()->assertSuccessful()->assertSee('Crack in slab')
            ->assertTableActionHidden('closeIssue', $issue->fresh())
            ->callTableAction('resolveIssue', $issue->fresh(), data: ['resolution_notes' => ''])->assertHasTableActionErrors(['resolution_notes'])
            ->callTableAction('resolveIssue', $issue->fresh(), data: ['resolution_notes' => 'Repaired and checked'])->assertHasNoTableActionErrors();
        $this->assertSame('resolved', $issue->fresh()->status);
        $issues()->callTableAction('closeIssue', $issue->fresh())->assertHasNoTableActionErrors();
        $this->assertSame('closed', $issue->fresh()->status);

        Livewire::test(DelaysRelationManager::class, ['ownerRecord' => $construction->fresh(), 'pageClass' => ViewProjectConstruction::class])
            ->assertSuccessful()->assertSee(__('project.construction.delay_reasons.weather'));
    }

    public function test_a_viewer_without_rights_sees_the_data_but_no_actions(): void
    {
        $owner = $this->actor($company = $this->company());
        $construction = $this->construction($owner, $company);
        $service = app(ConstructionService::class);
        $service->startConstruction($owner, $construction);
        $stage = $service->createWorkPackage($owner, $construction->fresh(), ['name' => 'Finishing']);
        $this->actor($company, ['view_any_project::construction', 'view_project::construction']);

        Livewire::test(ViewProjectConstruction::class, ['record' => $construction->getRouteKey()])
            ->assertSuccessful()
            ->assertActionHidden('startConstruction')->assertActionHidden('completeConstruction')
            ->assertActionHidden('editConstruction')->assertActionHidden('cancelConstruction');

        $this->stages($construction->fresh())->assertSuccessful()->assertSee('Finishing')
            ->assertTableActionHidden('startPackage', $stage)->assertTableActionHidden('addTask', $stage)
            ->assertTableActionHidden('editPackage', $stage)->assertTableActionHidden('cancelPackage', $stage);
    }

    public function test_the_project_tab_is_a_short_list_that_opens_the_construction_page(): void
    {
        $actor = $this->actor($company = $this->company());
        $construction = $this->construction($actor, $company);

        Livewire::test(ProjectConstructionRelationManager::class, ['ownerRecord' => $construction->project, 'pageClass' => ViewProject::class])
            ->assertSuccessful()
            ->assertSee('#'.$construction->execution_number)
            ->assertTableActionVisible('open', $construction)
            ->assertTableActionHasUrl('open', \App\Filament\Resources\ProjectConstructionResource::getUrl('view', ['record' => $construction]), $construction);
    }

    public function test_another_company_cannot_open_the_construction_page(): void
    {
        $owner = $this->actor($company = $this->company());
        $construction = $this->construction($owner, $company);
        $this->actor(Company::create(['name' => 'Other', 'email' => 'other@example.test']), self::ALL);

        $this->assertFalse(auth()->user()->can('view', $construction));
    }
}
