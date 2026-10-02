<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\DueDiligenceCase;
use App\Models\DueDiligenceItem;
use App\Models\Location;
use App\Models\Property;
use App\Models\PropertyAcquisition;
use App\Models\User;
use App\Services\PropertyAcquisitionService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class DueDiligenceWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private function actor(Company $company, array $permissions, bool $superAdmin = false): User
    {
        $user = User::factory()->create(['company_id' => $superAdmin ? null : $company->id]);
        if ($superAdmin) {
            $user->assignRole(Role::findOrCreate('super_admin', 'web'));
        }
        foreach ($permissions as $permission) {
            $user->givePermissionTo(Permission::findOrCreate($permission, 'web'));
        }
        return $user;
    }

    private function property(Company $company): Property
    {
        $location = Location::withoutGlobalScopes()->create(['company_id' => $company->id, 'name' => "Location {$company->id}", 'type' => 'city']);
        return Property::withoutGlobalScopes()->create(['company_id' => $company->id, 'location_id' => $location->id, 'name' => 'Land', 'address' => 'Address']);
    }

    public function test_required_pending_or_failed_items_block_clearance_and_authorized_waiver_allows_it(): void
    {
        $company = Company::create(['name' => 'A', 'email' => 'a@test.com']);
        $actor = $this->actor($company, ['clear_due_diligence_case', 'waive_due_diligence_item']);
        $acquisition = PropertyAcquisition::withoutGlobalScopes()->create(['company_id' => $company->id, 'type' => 'cash_purchase']);
        $case = DueDiligenceCase::withoutGlobalScopes()->create(['company_id' => $company->id, 'property_acquisition_id' => $acquisition->id, 'opened_at' => now()]);
        $item = DueDiligenceItem::withoutGlobalScopes()->create(['company_id' => $company->id, 'due_diligence_case_id' => $case->id, 'title' => 'Title deed', 'is_required' => true]);
        $service = app(PropertyAcquisitionService::class);

        try {
            $service->clearCase($actor, $case);
            $this->fail('Pending required item must block clearance.');
        } catch (ValidationException) {
            $this->assertSame('blocked', $case->fresh()->status);
        }
        $service->waiveItem($actor, $item);
        $service->clearCase($actor, $case->refresh());
        $this->assertSame('cleared', $case->refresh()->status);
    }

    public function test_unauthorized_waive_and_clear_are_rejected(): void
    {
        $company = Company::create(['name' => 'A', 'email' => 'a@test.com']);
        $actor = $this->actor($company, []);
        $acquisition = PropertyAcquisition::withoutGlobalScopes()->create(['company_id' => $company->id, 'type' => 'cash_purchase']);
        $case = DueDiligenceCase::withoutGlobalScopes()->create(['company_id' => $company->id, 'property_acquisition_id' => $acquisition->id, 'opened_at' => now()]);
        $item = DueDiligenceItem::withoutGlobalScopes()->create(['company_id' => $company->id, 'due_diligence_case_id' => $case->id, 'title' => 'Title deed']);
        $service = app(PropertyAcquisitionService::class);

        foreach ([fn () => $service->waiveItem($actor, $item), fn () => $service->clearCase($actor, $case)] as $operation) {
            try {
                $operation();
                $this->fail('Unauthorized due-diligence operation was accepted.');
            } catch (AuthorizationException) {
                $this->assertTrue(true);
            }
        }
    }

    public function test_super_admin_child_context_uses_parent_company_and_rejects_cross_company_property(): void
    {
        $companyA = Company::create(['name' => 'A', 'email' => 'a@test.com']);
        $companyB = Company::create(['name' => 'B', 'email' => 'b@test.com']);
        $superAdmin = $this->actor($companyA, ['create_due_diligence_case'], true);
        $acquisition = PropertyAcquisition::withoutGlobalScopes()->create(['company_id' => $companyA->id, 'type' => 'cash_purchase']);
        $propertyA = $this->property($companyA);
        $propertyB = $this->property($companyB);
        $service = app(PropertyAcquisitionService::class);

        $case = $service->createDueDiligenceCase($superAdmin, $acquisition, ['property_id' => $propertyA->id]);
        $this->assertSame($companyA->id, $case->company_id);
        $this->assertSame($propertyA->id, $case->property_id);

        $this->expectException(ValidationException::class);
        $service->createDueDiligenceCase($superAdmin, $acquisition, ['property_id' => $propertyB->id]);
    }
}
