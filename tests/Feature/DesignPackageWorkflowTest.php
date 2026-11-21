<?php

namespace Tests\Feature;

use App\Enums\{DesignPackageStatus, DesignVersionDecision};
use App\Filament\Resources\ProjectDesignPackageResource\Pages\ViewProjectDesignPackage;
use App\Filament\Resources\ProjectResource\Pages\ViewProject;
use App\Filament\Resources\ProjectResource\RelationManagers\DesignPackagesRelationManager;
use App\Models\{Company, DesignPackageVersion, Party, Project, ProjectDesignPackage, User};
use App\Services\DesignPackageService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * Design packages follow the drawings an office sends: upload a version,
 * approve it or reject it with a comment. Status always follows the latest version.
 */
class DesignPackageWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private const ALL = [
        'view_project', 'view_any_project', 'update_project',
        'view_any_project::design::package', 'view_project::design::package', 'create_project::design::package',
        'update_project::design::package', 'delete_project::design::package',
        'upload_design_version', 'decide_design_version',
    ];

    private function actor(Company $company, array $permissions = self::ALL): User
    {
        $user = User::factory()->create(['company_id' => $company->id]);
        foreach ($permissions as $permission) {
            $user->givePermissionTo(Permission::findOrCreate($permission, 'web'));
        }

        return $user;
    }

    private function company(string $name = 'Atlas Developments'): Company
    {
        return Company::create(['name' => $name, 'email' => str($name)->slug().'@example.test']);
    }

    private function project(Company $company): Project
    {
        return Project::withoutGlobalScopes()->create(['company_id' => $company->id, 'name' => 'Palm Heights', 'project_type' => 'residential']);
    }

    private function office(Company $company): Party
    {
        return Party::withoutGlobalScopes()->create(['company_id' => $company->id, 'type' => 'company', 'name' => 'Atlas Engineering', 'is_active' => true]);
    }

    private function package(User $actor, Project $project): ProjectDesignPackage
    {
        return app(DesignPackageService::class)->createPackage($actor, $project, [
            'name' => 'Architecture – Tower A',
            'discipline' => 'architectural',
            'engineering_office_id' => $this->office($project->company)->id,
        ]);
    }

    private function upload(User $actor, ProjectDesignPackage $package, string $file = 'arc.pdf'): DesignPackageVersion
    {
        return app(DesignPackageService::class)->uploadVersion($actor, $package, ['file_path' => 'documents/design/'.$file, 'file_name' => $file]);
    }

    public function test_upload_approve_reject_loop_drives_the_package_status(): void
    {
        $actor = $this->actor($company = $this->company());
        $package = $this->package($actor, $this->project($company));
        $service = app(DesignPackageService::class);
        $this->assertSame(DesignPackageStatus::NoDrawings, $package->status);

        $v1 = $this->upload($actor, $package, 'arc-v1.pdf');
        $this->assertSame(1, $v1->version_number);
        $this->assertSame(DesignVersionDecision::Pending, $v1->decision);
        $this->assertSame(DesignPackageStatus::AwaitingDecision, $package->fresh()->status);
        $this->assertSame('arc-v1.pdf', $v1->documentVersion->file_name);

        $service->reject($actor, $v1, 'Stair detail is missing.');
        $this->assertSame(DesignVersionDecision::Rejected, $v1->fresh()->decision);
        $this->assertSame('Stair detail is missing.', $v1->fresh()->decision_comment);
        $this->assertSame(DesignPackageStatus::ChangesRequested, $package->fresh()->status);

        $v2 = $this->upload($actor, $package->fresh(), 'arc-v2.pdf');
        $this->assertSame(2, $v2->version_number);
        $service->approve($actor, $v2);
        $this->assertSame(DesignPackageStatus::Approved, $package->fresh()->status);
        $this->assertSame($actor->id, $v2->fresh()->decided_by);

        // A design change after approval re-opens the package; the old approval stays.
        $v3 = $this->upload($actor, $package->fresh(), 'arc-v3.pdf');
        $this->assertSame(DesignPackageStatus::AwaitingDecision, $package->fresh()->status);
        $this->assertSame(DesignVersionDecision::Approved, $v2->fresh()->decision);
        $this->assertSame(3, $v3->version_number);
    }

    public function test_a_new_upload_supersedes_a_version_still_waiting_and_only_the_latest_can_be_decided(): void
    {
        $actor = $this->actor($company = $this->company());
        $package = $this->package($actor, $this->project($company));
        $v1 = $this->upload($actor, $package);
        $this->upload($actor, $package->fresh(), 'arc-fixed.pdf');

        $this->assertSame(DesignVersionDecision::Superseded, $v1->fresh()->decision);

        try {
            app(DesignPackageService::class)->approve($actor, $v1->fresh());
            $this->fail('An older version was decided.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('version', $exception->errors());
        }
    }

    public function test_reject_needs_a_comment_and_a_version_is_decided_once(): void
    {
        $actor = $this->actor($company = $this->company());
        $version = $this->upload($actor, $this->package($actor, $this->project($company)));
        $service = app(DesignPackageService::class);

        try {
            $service->reject($actor, $version, '   ');
            $this->fail('A rejection without a comment was accepted.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('comment', $exception->errors());
        }

        $service->approve($actor, $version);
        $this->expectException(ValidationException::class);
        $service->reject($actor, $version->fresh(), 'Too late');
    }

    public function test_upload_and_decision_each_need_their_own_permission(): void
    {
        $company = $this->company();
        $package = $this->package($this->actor($company), $this->project($company));
        $viewer = $this->actor($company, ['view_project::design::package']);

        try {
            app(DesignPackageService::class)->uploadVersion($viewer, $package, ['file_path' => 'documents/design/x.pdf']);
            $this->fail('Upload without permission was accepted.');
        } catch (AuthorizationException) {
        }

        $version = $this->upload($this->actor($company, ['upload_design_version']), $package);
        $this->expectException(AuthorizationException::class);
        app(DesignPackageService::class)->approve($this->actor($company, ['upload_design_version']), $version);
    }

    public function test_another_company_cannot_touch_the_package(): void
    {
        $company = $this->company();
        $package = $this->package($this->actor($company), $this->project($company));
        $foreign = $this->actor($this->company('Other Co'));

        $this->expectException(AuthorizationException::class);
        app(DesignPackageService::class)->uploadVersion($foreign, $package, ['file_path' => 'documents/design/x.pdf']);
    }

    public function test_a_closed_project_refuses_new_versions(): void
    {
        $actor = $this->actor($company = $this->company());
        $project = $this->project($company);
        $package = $this->package($actor, $project);
        $project->forceFill(['status' => 'closed'])->save();

        $this->expectException(ValidationException::class);
        $this->upload($actor, $package->fresh());
    }

    public function test_an_office_from_another_company_is_refused(): void
    {
        $actor = $this->actor($company = $this->company());
        $foreignOffice = $this->office($this->company('Other Co'));

        $this->expectException(ValidationException::class);
        app(DesignPackageService::class)->createPackage($actor, $this->project($company), ['name' => 'Structure', 'engineering_office_id' => $foreignOffice->id]);
    }

    public function test_the_project_tab_renders_and_creates_a_package(): void
    {
        $actor = $this->actor($company = $this->company());
        $project = $this->project($company);
        $office = $this->office($company);
        $this->actingAs($actor);

        Livewire::test(DesignPackagesRelationManager::class, ['ownerRecord' => $project, 'pageClass' => ViewProject::class])
            ->assertSuccessful()
            ->callTableAction('createPackage', data: ['name' => 'Structure – Tower A', 'discipline' => 'structural', 'engineering_office_id' => $office->id])
            ->assertHasNoTableActionErrors()
            ->assertSee('Structure – Tower A')
            ->assertSee('Atlas Engineering');

        $this->assertSame(DesignPackageStatus::NoDrawings, ProjectDesignPackage::withoutGlobalScopes()->firstOrFail()->status);
    }

    public function test_the_package_page_renders_and_runs_the_whole_loop(): void
    {
        Storage::fake(config('filesystems.default'));
        $actor = $this->actor($company = $this->company());
        $package = $this->package($actor, $this->project($company));
        $this->actingAs($actor);

        $page = Livewire::test(ViewProjectDesignPackage::class, ['record' => $package->getRouteKey()])
            ->assertSuccessful()
            ->assertActionVisible('uploadVersion')
            ->assertActionHidden('approveVersion')
            ->assertActionHidden('rejectVersion')
            ->callAction('uploadVersion', ['file_path' => UploadedFile::fake()->create('arc-v1.pdf', 120, 'application/pdf'), 'notes' => 'First issue'])
            ->assertHasNoActionErrors();

        $version = DesignPackageVersion::withoutGlobalScopes()->firstOrFail();
        $this->assertSame('arc-v1.pdf', $version->documentVersion->file_name);

        $page->assertActionVisible('approveVersion')
            ->callAction('rejectVersion', ['comment' => ''])
            ->assertHasActionErrors(['comment'])
            ->callAction('rejectVersion', ['comment' => 'Add the stair detail'])
            ->assertHasNoActionErrors();
        $this->assertSame(DesignPackageStatus::ChangesRequested, $package->fresh()->status);

        // The history renders the real file name, decision and comment.
        Livewire::test(ViewProjectDesignPackage::class, ['record' => $package->getRouteKey()])
            ->assertSuccessful()
            ->assertSee('arc-v1.pdf')
            ->assertSee('Add the stair detail')
            ->assertActionHidden('approveVersion');
    }

    public function test_a_viewer_without_decision_rights_sees_no_approve_or_reject(): void
    {
        $company = $this->company();
        $owner = $this->actor($company);
        $package = $this->package($owner, $this->project($company));
        $this->upload($owner, $package);
        $viewer = $this->actor($company, ['view_any_project::design::package', 'view_project::design::package']);
        $this->actingAs($viewer);

        Livewire::test(ViewProjectDesignPackage::class, ['record' => $package->getRouteKey()])
            ->assertSuccessful()
            ->assertActionHidden('uploadVersion')
            ->assertActionHidden('approveVersion')
            ->assertActionHidden('rejectVersion');
    }
}
