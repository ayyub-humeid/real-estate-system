<?php

namespace Tests\Feature;

use App\Models\{Company, DesignPackageSubmission, DocumentVersion, Party, Project, ProjectDesignPackage, User};
use App\Models\Role;
use App\Services\DesignEngineeringService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class DesignEngineeringWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private function permissions(): array
    {
        return [
            'update_project', 'create_project::design::package', 'update_project::design::package',
            'assign_design_package', 'submit_design_package', 'review_design_submission',
            'create_design_finding', 'waive_design_finding', 'request_design_revision',
            'submit_design_revision', 'approve_design_package', 'close_design_package',
            'create_design_package_scope_item', 'update_design_package_scope_item',
            'create_design_package_activity', 'create_document_version',
        ];
    }

    private function actor(Company $company, ?array $permissions = null): User
    {
        $user = User::factory()->create(['company_id' => $company->id]);
        foreach ($permissions ?? $this->permissions() as $permission) {
            $user->givePermissionTo(Permission::findOrCreate($permission, 'web'));
        }
        return $user;
    }

    private function project(Company $company): Project
    {
        return Project::withoutGlobalScopes()->create(['company_id' => $company->id, 'name' => 'Palm Heights', 'project_type' => 'residential']);
    }

    private function office(Company $company, string $name = 'Atlas Engineering'): Party
    {
        return Party::withoutGlobalScopes()->create(['company_id' => $company->id, 'type' => 'company', 'name' => $name, 'is_active' => true]);
    }

    private function readyPackage(DesignEngineeringService $service, User $actor, Project $project, Party $office): array
    {
        $package = $service->createPackage($actor, $project, ['name' => 'Architectural Design', 'code' => 'ARC-01', 'discipline' => 'architectural']);
        $assignment = $service->assignOffice($actor, $package, $office);
        $scope = $service->addScopeItem($actor, $assignment, ['title' => 'Plans and elevations']);
        $service->transitionScopeItem($actor, $scope, 'in_progress');
        $scope = $service->transitionScopeItem($actor, $scope->fresh(), 'ready');
        return [$package->fresh(), $assignment, $scope];
    }

    public function test_full_design_workflow_preserves_submission_document_and_revision_history(): void
    {
        $company = Company::create(['name' => 'A', 'email' => 'a@test.test']);
        $actor = $this->actor($company);
        $service = app(DesignEngineeringService::class);
        [$package, $assignment, $scope] = $this->readyPackage($service, $actor, $this->project($company), $this->office($company));

        $v1 = $service->addDocumentVersion($actor, $package, ['title' => 'Architectural drawings', 'file_name' => 'arc-v1.pdf', 'file_path' => 'design/arc-v1.pdf']);
        $first = $service->submit($actor, $package->fresh(), [$v1->id], 'Initial issue');
        $review = $service->startReview($actor, $first, $actor);
        $finding = $service->createFinding($actor, $review, ['severity' => 'critical', 'title' => 'Fire stair detail missing', 'description' => 'Provide the required detail.', 'design_package_scope_item_id' => $scope->id, 'document_version_id' => $v1->id]);
        $service->completeReview($actor, $review, 'Revision required');
        $this->assertSame('revision_required', $package->fresh()->status);

        $revision = $service->requestRevision($actor, $first->fresh(), [$finding->id], 'Address the review finding');
        $service->transitionRevision($actor, $revision, 'in_progress');
        $revision = $service->transitionRevision($actor, $revision->fresh(), 'ready');
        $v2 = $service->addDocumentVersion($actor, $package->fresh(), ['title' => 'Architectural drawings', 'file_name' => 'arc-v2.pdf', 'file_path' => 'design/arc-v2.pdf'], $v1->document);
        $second = $service->submit($actor, $package->fresh(), [$v2->id], 'Revised issue', $revision);
        $secondReview = $service->startReview($actor, $second, $actor);
        $service->completeReview($actor, $secondReview, 'Accepted');

        $service->waiveFinding($actor, $finding->fresh(), 'Accepted by the project authority.');
        $approval = $service->approve($actor, $package->fresh(), $second->fresh(), 'Approved issue for construction');
        $service->close($actor, $package->fresh());

        $this->assertSame(2, $package->submissions()->count());
        $this->assertSame(1, $first->fresh()->submission_number);
        $this->assertSame($v1->id, $first->fresh()->documents()->sole()->document_version_id);
        try { $v1->document->delete(); $this->fail('A document pinned to formal history was deleted.'); } catch (ValidationException) { $this->assertDatabaseHas('documents', ['id' => $v1->document_id]); }
        $this->assertSame('approved', $approval->submission->status);
        $this->assertSame($second->id, $approval->design_package_submission_id);
        $this->assertSame('closed', $package->fresh()->status);
        $this->assertSame(2, DocumentVersion::withoutGlobalScopes()->where('document_id', $v1->document_id)->count());
    }

    public function test_scope_is_bound_to_assignment_and_cross_company_office_is_rejected(): void
    {
        $companyA = Company::create(['name' => 'A', 'email' => 'a@test.test']);
        $companyB = Company::create(['name' => 'B', 'email' => 'b@test.test']);
        $actor = $this->actor($companyA);
        $service = app(DesignEngineeringService::class);
        $package = $service->createPackage($actor, $this->project($companyA), ['name' => 'Structure']);

        try { $service->assignOffice($actor, $package, $this->office($companyB)); $this->fail('A cross-company office was assigned.'); } catch (ValidationException) { $this->assertSame(0, $package->assignments()->count()); }
        $first = $service->assignOffice($actor, $package, $this->office($companyA, 'First office'));
        $scope = $service->addScopeItem($actor, $first, ['title' => 'Foundation drawings']);
        $second = $service->assignOffice($actor, $package->fresh(), $this->office($companyA, 'Replacement office'));
        $this->assertSame('replaced', $first->fresh()->status);
        $this->assertSame($first->id, $scope->design_package_assignment_id);
        $this->assertSame('active', $second->status);
    }

    public function test_design_documents_are_not_exposed_across_companies_through_the_generic_document_policy(): void
    {
        $companyA = Company::create(['name' => 'A', 'email' => 'a@test.test']);
        $companyB = Company::create(['name' => 'B', 'email' => 'b@test.test']);
        $actorA = $this->actor($companyA);
        $actorA->givePermissionTo(Permission::findOrCreate('view_document', 'web'));
        $actorB = $this->actor($companyB);
        $service = app(DesignEngineeringService::class);
        [$package] = $this->readyPackage($service, $actorB, $this->project($companyB), $this->office($companyB));
        $version = $service->addDocumentVersion($actorB, $package, ['title' => 'Private drawing', 'file_name' => 'private.pdf', 'file_path' => 'design/private.pdf']);

        $this->assertFalse($actorA->can('view', $version->document));
    }

    public function test_submission_requires_ready_scope_and_pins_only_its_package_versions(): void
    {
        $company = Company::create(['name' => 'A', 'email' => 'a@test.test']);
        $actor = $this->actor($company);
        $service = app(DesignEngineeringService::class);
        $project = $this->project($company);
        $package = $service->createPackage($actor, $project, ['name' => 'Mechanical']);
        $assignment = $service->assignOffice($actor, $package, $this->office($company));
        $service->addScopeItem($actor, $assignment, ['title' => 'HVAC']);
        try { $service->submit($actor, $package->fresh(), []); $this->fail('Submitted before scope was ready.'); } catch (ValidationException) { $this->assertSame(0, $package->submissions()->count()); }

        [$other] = $this->readyPackage($service, $actor, $project, $this->office($company, 'Other office'));
        $otherVersion = $service->addDocumentVersion($actor, $other, ['title' => 'Other', 'file_name' => 'other.pdf', 'file_path' => 'design/other.pdf']);
        $item = $assignment->scopeItems()->sole(); $service->transitionScopeItem($actor, $item, 'in_progress'); $service->transitionScopeItem($actor, $item->fresh(), 'ready');
        try { $service->submit($actor, $package->fresh(), [$otherVersion->id]); $this->fail('Pinned a version from another package.'); } catch (ValidationException) { $this->assertSame(0, $package->submissions()->count()); }
    }

    public function test_unauthorized_and_generic_status_bypass_are_rejected(): void
    {
        $company = Company::create(['name' => 'A', 'email' => 'a@test.test']);
        $service = app(DesignEngineeringService::class);
        $project = $this->project($company);
        $denied = $this->actor($company, []);
        $this->expectException(AuthorizationException::class);
        $service->createPackage($denied, $project, ['name' => 'Denied']);
    }

    public function test_super_admin_can_work_with_the_parent_company_and_missing_context_is_denied(): void
    {
        $company = Company::create(['name' => 'A', 'email' => 'a@test.test']);
        $project = $this->project($company);
        $service = app(DesignEngineeringService::class);
        $withoutContext = User::factory()->create(['company_id' => null]);
        foreach (['update_project', 'create_project::design::package'] as $permission) $withoutContext->givePermissionTo(Permission::findOrCreate($permission, 'web'));
        try { $service->createPackage($withoutContext, $project, ['name' => 'No context']); $this->fail('A normal user without company context created a package.'); } catch (AuthorizationException) { $this->assertSame(0, $project->designPackages()->count()); }

        $superAdmin = User::factory()->create(['company_id' => null]);
        $superAdmin->assignRole(Role::platform()->firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']));
        foreach (['update_project', 'create_project::design::package'] as $permission) $superAdmin->givePermissionTo(Permission::findOrCreate($permission, 'web'));
        $package = $service->createPackage($superAdmin, $project, ['name' => 'Super Admin package']);
        $this->assertSame($company->id, $package->company_id);
    }

    public function test_workflow_status_is_not_mass_assignable_and_approval_blocks_open_critical_finding(): void
    {
        $company = Company::create(['name' => 'A', 'email' => 'a@test.test']);
        $actor = $this->actor($company);
        $service = app(DesignEngineeringService::class);
        [$package, , $scope] = $this->readyPackage($service, $actor, $this->project($company), $this->office($company));
        $package->update(['status' => 'approved']);
        $this->assertSame('in_progress', $package->fresh()->status);
        $version = $service->addDocumentVersion($actor, $package->fresh(), ['title' => 'Drawings', 'file_name' => 'v1.pdf', 'file_path' => 'design/v1.pdf']);
        $submission = $service->submit($actor, $package->fresh(), [$version->id]);
        $review = $service->startReview($actor, $submission, $actor);
        $service->createFinding($actor, $review, ['severity' => 'critical', 'title' => 'Critical', 'description' => 'Fix', 'design_package_scope_item_id' => $scope->id, 'document_version_id' => $version->id]);
        $service->completeReview($actor, $review);
        try { $service->approve($actor, $package->fresh(), $submission->fresh()); $this->fail('Approved with an open critical finding.'); } catch (ValidationException) { $this->assertSame('revision_required', $package->fresh()->status); }
    }
}
