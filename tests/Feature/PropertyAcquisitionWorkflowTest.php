<?php

namespace Tests\Feature;

use App\Models\AcquisitionParty;
use App\Models\Company;
use App\Models\Location;
use App\Models\Party;
use App\Models\Property;
use App\Models\PropertyAcquisition;
use App\Models\User;
use App\Services\PropertyAcquisitionService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class PropertyAcquisitionWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private function actor(Company $company, array $permissions): User
    {
        $user = User::factory()->create(['company_id' => $company->id, 'role' => 'company_admin']);
        foreach ($permissions as $permission) {
            $user->givePermissionTo(Permission::findOrCreate($permission, 'web'));
        }

        return $user;
    }

    private function property(Company $company): Property
    {
        $location = Location::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'name' => 'Test city',
            'type' => 'city',
        ]);

        return Property::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'location_id' => $location->id,
            'name' => 'Land',
            'address' => 'Address',
        ]);
    }

    private function party(Company $company, string $name): Party
    {
        return Party::withoutGlobalScopes()->create(['company_id' => $company->id, 'type' => 'individual', 'name' => $name]);
    }

    public function test_acquisition_accepts_multiple_same_company_properties_and_parties(): void
    {
        $company = Company::create(['name' => 'A', 'email' => 'a@test.com']);
        $actor = $this->actor($company, ['update_property::acquisition']);
        $acquisition = PropertyAcquisition::withoutGlobalScopes()->create(['company_id' => $company->id, 'type' => 'cash_purchase']);
        $service = app(PropertyAcquisitionService::class);

        $service->attachProperty($actor, $acquisition, $this->property($company));
        $service->attachProperty($actor, $acquisition, $this->property($company));
        $service->attachParty($actor, $acquisition, $this->party($company, 'Seller'), 'seller');

        $this->assertSame(2, $acquisition->acquisitionProperties()->count());
        $this->assertSame(1, $acquisition->acquisitionParties()->count());
    }

    public function test_cross_company_attachment_is_rejected(): void
    {
        $companyA = Company::create(['name' => 'A', 'email' => 'a@test.com']);
        $companyB = Company::create(['name' => 'B', 'email' => 'b@test.com']);
        $actor = $this->actor($companyA, ['update_property::acquisition']);
        $acquisition = PropertyAcquisition::withoutGlobalScopes()->create(['company_id' => $companyA->id, 'type' => 'cash_purchase']);

        $this->expectException(ValidationException::class);
        app(PropertyAcquisitionService::class)->attachProperty($actor, $acquisition, $this->property($companyB));
    }

    public function test_unauthorized_workflow_actions_are_rejected(): void
    {
        $company = Company::create(['name' => 'A', 'email' => 'a@test.com']);
        $actor = $this->actor($company, []);
        $service = app(PropertyAcquisitionService::class);

        foreach (['approved', 'completed', 'cancelled'] as $state) {
            $acquisition = PropertyAcquisition::withoutGlobalScopes()->create([
                'company_id' => $company->id,
                'type' => 'cash_purchase',
                'status' => $state === 'approved' ? 'under_due_diligence' : ($state === 'completed' ? 'approved' : 'draft'),
            ]);
            try {
                $service->transition($actor, $acquisition, $state, 'Test');
                $this->fail("{$state} should require its workflow permission.");
            } catch (AuthorizationException) {
                $this->assertSame($state === 'completed' ? 'approved' : ($state === 'approved' ? 'under_due_diligence' : 'draft'), $acquisition->fresh()->status);
            }
        }
    }

    public function test_cancelled_acquisition_cannot_complete(): void
    {
        $company = Company::create(['name' => 'A', 'email' => 'a@test.com']);
        $actor = $this->actor($company, ['cancel_property_acquisition', 'complete_property_acquisition']);
        $acquisition = PropertyAcquisition::withoutGlobalScopes()->create(['company_id' => $company->id, 'type' => 'cash_purchase']);
        $service = app(PropertyAcquisitionService::class);

        $service->transition($actor, $acquisition, 'cancelled', 'Stopped');
        $this->expectException(ValidationException::class);
        $service->transition($actor, $acquisition->refresh(), 'completed');
    }

    public function test_start_due_diligence_explains_that_a_property_and_party_are_required(): void
    {
        $company = Company::create(['name' => 'A', 'email' => 'a@test.com']);
        $actor = $this->actor($company, ['update_property::acquisition']);
        $acquisition = PropertyAcquisition::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'type' => 'cash_purchase',
        ]);

        try {
            app(PropertyAcquisitionService::class)->transition($actor, $acquisition, 'under_due_diligence');
            $this->fail('Due diligence must require both a property and a party.');
        } catch (ValidationException $exception) {
            $this->assertSame(
                'At least one property and one involved party are required before due diligence.',
                $exception->errors()['status'][0],
            );
            $this->assertSame('draft', $acquisition->fresh()->status);
        }
    }

    public function test_completed_and_cancelled_acquisitions_cannot_be_deleted(): void
    {
        $company = Company::create(['name' => 'A', 'email' => 'a@test.com']);
        $actor = $this->actor($company, ['delete_property::acquisition']);

        foreach (['completed', 'cancelled'] as $status) {
            $acquisition = new PropertyAcquisition(['company_id' => $company->id, 'type' => 'cash_purchase', 'status' => $status]);
            $this->assertFalse($actor->can('delete', $acquisition));
        }
        $this->assertTrue($actor->can('delete', new PropertyAcquisition(['company_id' => $company->id, 'type' => 'cash_purchase', 'status' => 'draft'])));
        $this->assertFalse($actor->can('deleteAny', PropertyAcquisition::class));
    }

    public function test_acquisition_party_share_is_scoped_per_role_and_edit_excludes_current_record(): void
    {
        $company = Company::create(['name' => 'A', 'email' => 'a@test.com']);
        $actor = $this->actor($company, ['update_property::acquisition', 'update_acquisition_party']);
        $acquisition = PropertyAcquisition::withoutGlobalScopes()->create(['company_id' => $company->id, 'type' => 'cash_purchase']);
        $service = app(PropertyAcquisitionService::class);
        $seller = $service->attachParty($actor, $acquisition, $this->party($company, 'Seller'), 'seller', ['share_percentage' => 100]);
        $buyer = $service->attachParty($actor, $acquisition, $this->party($company, 'Buyer'), 'buyer', ['share_percentage' => 100]);

        $this->assertSame('100.00', $seller->share_percentage);
        $this->assertSame('100.00', $buyer->share_percentage);
        $this->assertSame(100.0, (float) $service->updateAcquisitionParty($actor, $seller, ['share_percentage' => 100])->share_percentage);

        $this->expectException(ValidationException::class);
        $service->updateAcquisitionParty($actor, $seller, ['role' => 'buyer']);
    }

    public function test_ownership_replacement_preserves_history_and_requires_one_hundred_percent(): void
    {
        $company = Company::create(['name' => 'A', 'email' => 'a@test.com']);
        $actor = $this->actor($company, ['change_property_ownership']);
        $property = $this->property($company);
        $one = $this->party($company, 'One');
        $two = $this->party($company, 'Two');
        $service = app(PropertyAcquisitionService::class);

        $service->replaceOwnership($actor, $property, [['party' => $one, 'percentage' => 100]], '2026-01-01');
        $service->replaceOwnership($actor, $property, [['party' => $one, 'percentage' => 60], ['party' => $two, 'percentage' => 40]], '2026-02-01');

        $this->assertSame(2, $property->activeOwnerships()->count());
        $this->assertSame(3, $property->ownerships()->count());
    }

    public function test_acquisition_history_creation_requires_create_permission_and_inherits_parent_company(): void
    {
        $company = Company::create(['name' => 'A', 'email' => 'a@test.com']);
        $property = $this->property($company);
        $service = app(PropertyAcquisitionService::class);
        $unauthorized = $this->actor($company, []);

        try {
            $service->createForProperty($unauthorized, $property, ['type' => 'cash_purchase']);
            $this->fail('Create permission must be enforced server-side.');
        } catch (AuthorizationException) {
            $this->assertSame(0, PropertyAcquisition::withoutGlobalScopes()->count());
        }

        $authorized = $this->actor($company, ['create_property::acquisition']);
        $acquisition = $service->createForProperty($authorized, $property, ['type' => 'cash_purchase']);
        $this->assertSame($company->id, $acquisition->company_id);
        $this->assertTrue($property->propertyAcquisitions()->whereKey($acquisition)->exists());
    }

    public function test_company_uses_one_canonical_self_party_even_after_renaming(): void
    {
        $company = Company::create(['name' => 'Company Owner', 'email' => 'owner@test.com']);
        $property = $this->property($company);
        $service = app(PropertyAcquisitionService::class);
        $party = $service->partyForCompany($property, $company);

        $this->assertSame('company', $party->type);
        $this->assertSame($company->id, $party->company_id);
        $this->assertSame($party->id, $company->fresh()->self_party_id);
        $this->assertSame($party->id, $service->partyForCompany($property, $company)->id);

        $company->update(['name' => 'Renamed Company Owner']);
        $this->assertSame($party->id, $service->partyForCompany($property, $company->fresh())->id);
        $this->assertSame(1, Party::withoutGlobalScopes()->whereKey($party->id)->count());

        $otherCompany = Company::create(['name' => 'Other Company', 'email' => 'other-owner@test.com']);
        $otherParty = $service->partyForCompany($this->property($otherCompany), $otherCompany);
        $this->assertNotSame($party->id, $otherParty->id);
        $this->assertSame($otherCompany->id, $otherParty->company_id);
    }

    public function test_current_company_and_external_company_can_share_an_acquisition_as_partners(): void
    {
        $company = Company::create(['name' => 'Our Company', 'email' => 'ours@test.com']);
        $actor = $this->actor($company, ['update_property::acquisition']);
        $acquisition = PropertyAcquisition::withoutGlobalScopes()->create(['company_id' => $company->id, 'type' => 'partnership']);
        $service = app(PropertyAcquisitionService::class);
        $currentCompanyParty = $service->selfPartyForCompany($company);
        $externalCompanyParty = Party::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'type' => 'company',
            'name' => 'External Development Company',
        ]);

        $service->attachParty($actor, $acquisition, $currentCompanyParty, 'partner', ['share_percentage' => 30]);
        $service->attachParty($actor, $acquisition, $externalCompanyParty, 'partner', ['share_percentage' => 70]);

        $this->assertSame(2, $acquisition->acquisitionParties()->count());
        $this->assertSame(100.0, (float) $acquisition->acquisitionParties()->where('role', 'partner')->sum('share_percentage'));
    }
}
