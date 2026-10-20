<?php

namespace Tests\Feature;

use App\Enums\{ContractStatus, ContractType, ContractVersionStatus, OpportunityType, ReservationSource, ReservationStatus};
use App\Filament\Resources\{ContractResource, ContractTemplateResource};
use App\Models\{Company, Contract, ContractTemplate, Document, DocumentVersion, Location, Party, Project, ProjectBuilding, ProjectBuildingFloor, ProjectPlannedUnit, Property, Unit, UnitReservation, User};
use App\Services\{ContractDraftService, ContractTemplateService, ContractWorkflowService};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class ContractFoundationTest extends TestCase
{
    use RefreshDatabase;

    public function test_contract_statuses_and_create_feedback_are_localized(): void
    {
        app()->setLocale('ar');

        $this->assertSame('لا يمكن إنشاء العقد', __('Cannot create contract'));
        $this->assertSame('قيد المراجعة', ContractStatus::UnderReview->getLabel());
        $this->assertSame('مرسل للمراجعة', ContractVersionStatus::Submitted->getLabel());

        app()->setLocale('en');
        $this->assertSame('Cannot create contract', __('Cannot create contract'));
        $this->assertSame('Under review', ContractStatus::UnderReview->getLabel());
    }

    public function test_contract_resource_labels_are_scalar_and_localized(): void
    {
        app()->setLocale('ar');

        $this->assertSame('العقود', ContractResource::getNavigationLabel());
        $this->assertSame('العقود', ContractResource::getPluralModelLabel());
        $this->assertSame('قوالب العقود', ContractTemplateResource::getNavigationLabel());

        app()->setLocale('en');

        $this->assertSame('Contracts', ContractResource::getNavigationLabel());
        $this->assertSame('Contract templates', ContractTemplateResource::getNavigationLabel());
    }

    public function test_company_user_creates_numbered_draft_with_target_party_and_snapshots(): void
    {
        [$company, $user, $unit] = $this->companyUserAndUnit();
        $this->grant($user, ['create_contract']);
        $party = Party::create(['company_id' => $company->id, 'type' => 'person', 'name' => 'Buyer A', 'is_active' => true]);

        $contract = app(ContractDraftService::class)->create($user, [
            'contract_type' => ContractType::Sale,
            'unit_id' => $unit->id,
            'total_amount' => 1000,
            'currency' => 'USD',
            'parties' => [['party_id' => $party->id, 'role' => 'buyer', 'share_percentage' => 100]],
            'terms' => [['term_type' => 'commercial', 'title' => 'Purchase price', 'text' => 'USD 1,000']],
        ]);

        $this->assertSame(ContractStatus::Draft, $contract->status);
        $this->assertSame('CON-'.now()->format('Y').'-000001', $contract->contract_number);
        $version = $contract->versions()->firstOrFail();
        $this->assertSame(1, $version->version_number);
        $this->assertSame(ContractVersionStatus::Draft, $version->status);
        $this->assertSame($unit->id, $contract->unit_id);
        $this->assertSame($unit->unit_number, $version->unit_snapshot['unit_number']);
        $this->assertDatabaseHas('contract_parties', ['party_id' => $party->id, 'role' => 'buyer']);
        $this->assertDatabaseHas('contract_terms', ['contract_version_id' => $version->id, 'title' => 'Purchase price']);
    }

    public function test_company_user_cannot_inject_a_party_or_unit_from_another_company(): void
    {
        [$company, $user, $unit] = $this->companyUserAndUnit();
        [$otherCompany, , $otherUnit] = $this->companyUserAndUnit('Other');
        $this->grant($user, ['create_contract']);
        $otherParty = Party::create(['company_id' => $otherCompany->id, 'type' => 'person', 'name' => 'Other Buyer', 'is_active' => true]);

        try {
            app(ContractDraftService::class)->create($user, [
                'contract_type' => ContractType::Sale,
                'unit_id' => $unit->id,
                'parties' => [['party_id' => $otherParty->id, 'role' => 'buyer']],
            ]);
            $this->fail('Cross-company party was accepted.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('parties', $exception->errors());
        }

        $localParty = Party::create(['company_id' => $company->id, 'type' => 'person', 'name' => 'Local Buyer', 'is_active' => true]);
        try {
            app(ContractDraftService::class)->create($user, [
                'contract_type' => ContractType::Sale,
                'unit_id' => $otherUnit->id,
                'parties' => [['party_id' => $localParty->id, 'role' => 'buyer']],
            ]);
            $this->fail('Cross-company unit was accepted.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('unit_id', $exception->errors());
        }
    }

    public function test_template_versions_are_append_only_and_are_copied_into_the_contract_draft(): void
    {
        [$company, $user, $unit] = $this->companyUserAndUnit('Template');
        $this->grant($user, ['create_contract', 'update_contract::template']);
        $buyer = Party::create(['company_id' => $company->id, 'type' => 'person', 'name' => 'Template Buyer', 'is_active' => true]);
        $template = ContractTemplate::create([
            'company_id' => $company->id,
            'name' => 'Sale agreement',
            'contract_type' => ContractType::Sale,
            'is_active' => true,
        ]);

        $templates = app(ContractTemplateService::class);
        $v1 = $templates->createVersion($user, $template, [
            'body' => 'V1 body',
            'clauses' => [['type' => 'legal', 'title' => 'Deposit', 'text' => 'A 10% deposit is due.']],
        ]);
        $contract = app(ContractDraftService::class)->create($user, [
            'contract_type' => ContractType::Sale,
            'unit_id' => $unit->id,
            'template_version_id' => $v1->id,
            'parties' => [['party_id' => $buyer->id, 'role' => 'buyer']],
        ]);
        $v2 = $templates->createVersion($user, $template, [
            'body' => 'V2 body',
            'clauses' => [['type' => 'legal', 'title' => 'Deposit', 'text' => 'A 20% deposit is due.']],
        ]);

        $this->assertSame(1, $v1->version_number);
        $this->assertSame(2, $v2->version_number);
        $this->assertSame('A 10% deposit is due.', $contract->versions()->firstOrFail()->terms()->firstOrFail()->text);
    }

    public function test_active_reservation_can_supply_the_target_and_snapshot_for_a_contract(): void
    {
        [$company, $user, $unit] = $this->companyUserAndUnit('Reservation source');
        $this->grant($user, ['create_contract']);
        $buyer = Party::create(['company_id' => $company->id, 'type' => 'person', 'name' => 'Reservation Buyer', 'is_active' => true]);
        $reservation = UnitReservation::create([
            'company_id' => $company->id,
            'reservation_number' => 'RES-TEST-0001',
            'source' => ReservationSource::Direct,
            'transaction_type' => OpportunityType::Sale,
            'unit_id' => $unit->id,
            'customer_party_id' => $buyer->id,
            'agreed_amount' => 125000,
            'currency' => 'USD',
            'valid_until' => now()->addWeek(),
        ]);
        $reservation->forceFill(['status' => ReservationStatus::Active])->save();

        $contract = app(ContractDraftService::class)->create($user, [
            'contract_type' => ContractType::Sale,
            'reservation_id' => $reservation->id,
            'parties' => [['party_id' => $buyer->id, 'role' => 'buyer']],
            'terms' => [['term_type' => 'commercial', 'title' => 'Reservation amount']],
        ]);

        $this->assertSame($reservation->id, $contract->reservation_id);
        $this->assertSame($unit->id, $contract->unit_id);
        $this->assertSame('RES-TEST-0001', $contract->versions()->firstOrFail()->reservation_snapshot['reservation_number']);
    }

    public function test_off_plan_sale_uses_a_planned_unit_without_requiring_an_actual_unit(): void
    {
        [$company, $user] = $this->companyUserAndUnit('Off plan');
        $this->grant($user, ['create_contract']);
        $buyer = Party::create(['company_id' => $company->id, 'type' => 'person', 'name' => 'Off-plan Buyer', 'is_active' => true]);
        $project = Project::create(['company_id' => $company->id, 'name' => 'Future Residence', 'project_type' => 'residential', 'status' => 'planning']);
        $building = ProjectBuilding::create(['company_id' => $company->id, 'project_id' => $project->id, 'name' => 'Tower A', 'code' => 'A']);
        $floor = ProjectBuildingFloor::create(['company_id' => $company->id, 'project_building_id' => $building->id, 'floor_number' => 1]);
        $planned = ProjectPlannedUnit::create(['company_id' => $company->id, 'project_building_floor_id' => $floor->id, 'code' => 'A-101', 'unit_type' => 'apartment', 'status' => 'planned']);

        $contract = app(ContractDraftService::class)->create($user, [
            'contract_type' => ContractType::OffPlanSale,
            'planned_unit_id' => $planned->id,
            'parties' => [['party_id' => $buyer->id, 'role' => 'buyer']],
            'terms' => [['term_type' => 'commercial', 'title' => 'Off-plan price']],
        ]);

        $this->assertSame(ContractType::OffPlanSale, $contract->contract_type);
        $this->assertSame($planned->id, $contract->planned_unit_id);
        $this->assertNull($contract->unit_id);
        $this->assertSame($project->id, $contract->project_id);
    }

    public function test_only_draft_version_can_be_edited_and_revision_keeps_v1_history(): void
    {
        [$company, $user, $unit] = $this->companyUserAndUnit('Revision');
        $this->grant($user, ['create_contract', 'update_contract_version', 'submit_contract_version', 'approve_contract_version', 'create_contract_revision']);
        $buyer = Party::create(['company_id' => $company->id, 'type' => 'person', 'name' => 'Buyer', 'is_active' => true]);
        $coBuyer = Party::create(['company_id' => $company->id, 'type' => 'person', 'name' => 'Co buyer', 'is_active' => true]);
        $contract = $this->draft($user, $unit, $buyer, 1000);
        $v1 = $contract->versions()->firstOrFail();

        app(ContractDraftService::class)->updateDraft($user, $v1, [
            'total_amount' => 1200,
            'currency' => 'USD',
            'parties' => [
                ['party_id' => $buyer->id, 'role' => 'buyer', 'share_percentage' => 50],
                ['party_id' => $coBuyer->id, 'role' => 'buyer', 'share_percentage' => 50],
            ],
            'terms' => [['term_type' => 'commercial', 'title' => 'Revised price', 'text' => 'USD 1,200']],
        ]);
        $this->assertSame('1200.00', $v1->fresh()->total_amount);
        $this->assertSame(2, $v1->fresh()->parties()->count());

        $workflow = app(ContractWorkflowService::class);
        $workflow->submit($user, $v1->fresh());
        $workflow->approve($user, $v1->fresh());
        $v2 = $workflow->createRevision($user, $v1->fresh());

        $this->assertSame(2, $v2->version_number);
        $this->assertSame(ContractVersionStatus::Draft, $v2->status);
        $this->assertSame(ContractVersionStatus::Superseded, $v1->fresh()->status);
        $this->assertSame(2, $v2->parties()->count());
        $this->assertSame('Revised price', $v2->terms()->firstOrFail()->title);

        $this->expectException(ValidationException::class);
        app(ContractDraftService::class)->updateDraft($user, $v1->fresh(), ['parties' => [], 'terms' => []]);
    }

    public function test_approved_version_requires_its_own_document_and_every_party_signature_before_activation(): void
    {
        Storage::fake(config('filesystems.default'));
        [$company, $user, $unit] = $this->companyUserAndUnit('Signing');
        $this->grant($user, ['create_contract', 'submit_contract_version', 'approve_contract_version', 'record_contract_signature', 'activate_contract']);
        $buyer = Party::create(['company_id' => $company->id, 'type' => 'person', 'name' => 'Buyer', 'is_active' => true]);
        $seller = Party::create(['company_id' => $company->id, 'type' => 'company', 'name' => 'Seller', 'is_active' => true]);
        $contract = $this->draft($user, $unit, $buyer, 1000, [['party_id' => $seller->id, 'role' => 'seller']]);
        $version = $contract->versions()->firstOrFail();
        $workflow = app(ContractWorkflowService::class);
        $workflow->submit($user, $version);
        $workflow->approve($user, $version->fresh());

        $document = Document::create(['documentable_type' => \App\Models\ContractVersion::class, 'documentable_id' => $version->id, 'title' => 'Signed agreement', 'created_by' => $user->id]);
        Storage::disk(config('filesystems.default'))->put('contracts/signed.pdf', 'signed bytes');
        $documentVersion = DocumentVersion::create(['company_id' => $company->id, 'document_id' => $document->id, 'version_number' => 1, 'file_name' => 'signed.pdf', 'file_path' => 'contracts/signed.pdf', 'uploaded_by' => $user->id]);

        $workflow->recordSignature($user, $version->fresh(), $version->parties()->where('party_id', $buyer->id)->firstOrFail(), $documentVersion, 'Buyer', 'manual', [], 'signatures/buyer.png');
        $this->assertDatabaseHas('contract_signatures', ['signatory_name' => 'Buyer', 'signature_image_path' => 'signatures/buyer.png']);
        $this->assertSame(1, $contract->documents()->count());
        $this->assertSame('Signed agreement', $contract->documents()->first()->title);
        try {
            $workflow->activate($user, $contract, $version->fresh());
            $this->fail('Contract activated without every party signing.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('signatures', $exception->errors());
        }
        $workflow->recordSignature($user, $version->fresh(), $version->parties()->where('party_id', $seller->id)->firstOrFail(), $documentVersion, 'Seller representative', 'manual');
        $activated = $workflow->activate($user, $contract, $version->fresh());

        $this->assertSame(ContractStatus::Active, $activated->status);
        $this->assertSame(ContractVersionStatus::Signed, $version->fresh()->status);
        $this->assertDatabaseHas('contract_status_histories', ['contract_id' => $contract->id, 'action' => 'activated']);
    }

    public function test_amendment_creates_a_new_draft_without_mutating_the_signed_version(): void
    {
        Storage::fake(config('filesystems.default'));
        [$company, $user, $unit] = $this->companyUserAndUnit('Amendment');
        $this->grant($user, ['create_contract', 'submit_contract_version', 'approve_contract_version', 'request_contract_revision', 'create_contract_revision', 'record_contract_signature', 'activate_contract', 'create_contract_amendment']);
        $party = Party::create(['company_id' => $company->id, 'type' => 'person', 'name' => 'Buyer', 'is_active' => true]);
        $contract = $this->draft($user, $unit, $party, 1000);
        $version = $contract->versions()->firstOrFail();
        $workflow = app(ContractWorkflowService::class);
        $workflow->submit($user, $version);
        $workflow->approve($user, $version->fresh());
        $document = Document::create(['documentable_type' => \App\Models\ContractVersion::class, 'documentable_id' => $version->id, 'title' => 'Agreement', 'created_by' => $user->id]);
        Storage::disk(config('filesystems.default'))->put('contracts/amendment-source.pdf', 'source');
        $documentVersion = DocumentVersion::create(['company_id' => $company->id, 'document_id' => $document->id, 'version_number' => 1, 'file_name' => 'source.pdf', 'file_path' => 'contracts/amendment-source.pdf', 'uploaded_by' => $user->id]);
        $workflow->recordSignature($user, $version->fresh(), $version->parties()->firstOrFail(), $documentVersion, 'Buyer', 'manual');
        $workflow->activate($user, $contract, $version->fresh());

        $amendment = $workflow->createAmendment($user, $contract->fresh(), $version->fresh(), 'commercial', 'Agreed price correction', now()->addDay()->toDateString());

        $this->assertSame(ContractVersionStatus::Signed, $version->fresh()->status);
        $this->assertSame(ContractVersionStatus::Draft, $amendment->resultingVersion->status);
        $this->assertSame(2, $amendment->resultingVersion->version_number);
        $this->assertSame($version->id, $amendment->base_contract_version_id);

        // The signed agreement remains active while its amendment follows its
        // own draft/review/revision workflow.
        $amendmentDraft = $amendment->resultingVersion;
        $workflow->submit($user, $amendmentDraft);
        $this->assertSame(ContractStatus::Active, $contract->fresh()->status);
        $workflow->requestRevision($user, $amendmentDraft->fresh(), 'Clarify the amendment text.');
        $revisedAmendmentDraft = $workflow->createRevision($user, $amendmentDraft->fresh());

        $this->assertSame(ContractStatus::Active, $contract->fresh()->status);
        $this->assertSame(ContractVersionStatus::Draft, $revisedAmendmentDraft->status);
        $this->assertSame($revisedAmendmentDraft->id, $amendment->fresh()->resulting_contract_version_id);
    }

    public function test_consecutive_leases_are_allowed_but_overlapping_leases_are_rejected_on_activation(): void
    {
        Storage::fake(config('filesystems.default'));
        [$company, $user, $unit] = $this->companyUserAndUnit('Lease availability');
        $this->grant($user, ['create_contract', 'submit_contract_version', 'approve_contract_version', 'record_contract_signature', 'activate_contract']);
        $tenant = Party::create(['company_id' => $company->id, 'type' => 'person', 'name' => 'Tenant', 'is_active' => true]);

        $first = $this->leaseDraft($user, $unit, $tenant, '2027-01-01', '2027-01-31');
        $this->signAndActivate($user, $first, 'first.pdf');

        $consecutive = $this->leaseDraft($user, $unit, $tenant, '2027-02-01', '2027-02-28');
        $this->signAndActivate($user, $consecutive, 'consecutive.pdf');
        $this->assertSame(ContractStatus::Active, $consecutive->fresh()->status);

        $overlap = $this->leaseDraft($user, $unit, $tenant, '2027-01-15', '2027-02-15');
        $version = $overlap->versions()->firstOrFail();
        $workflow = app(ContractWorkflowService::class);
        $workflow->submit($user, $version);
        $workflow->approve($user, $version->fresh());
        $document = Document::create(['documentable_type' => \App\Models\ContractVersion::class, 'documentable_id' => $version->id, 'title' => 'Overlap', 'created_by' => $user->id]);
        Storage::disk(config('filesystems.default'))->put('contracts/overlap.pdf', 'overlap');
        $documentVersion = DocumentVersion::create(['company_id' => $company->id, 'document_id' => $document->id, 'version_number' => 1, 'file_name' => 'overlap.pdf', 'file_path' => 'contracts/overlap.pdf', 'uploaded_by' => $user->id]);
        $workflow->recordSignature($user, $version->fresh(), $version->parties()->firstOrFail(), $documentVersion, 'Tenant', 'manual');

        $this->expectException(ValidationException::class);
        $workflow->activate($user, $overlap, $version->fresh());
    }

    public function test_conflicting_sale_of_the_same_actual_unit_is_rejected_on_activation(): void
    {
        Storage::fake(config('filesystems.default'));
        [$company, $user, $unit] = $this->companyUserAndUnit('Sale availability');
        $this->grant($user, ['create_contract', 'submit_contract_version', 'approve_contract_version', 'record_contract_signature', 'activate_contract']);
        $buyer = Party::create(['company_id' => $company->id, 'type' => 'person', 'name' => 'Buyer', 'is_active' => true]);
        $first = $this->draft($user, $unit, $buyer, 1000);
        $this->signAndActivate($user, $first, 'sale-first.pdf');

        $second = $this->draft($user, $unit, $buyer, 1200);
        $version = $second->versions()->firstOrFail();
        $workflow = app(ContractWorkflowService::class);
        $workflow->submit($user, $version);
        $workflow->approve($user, $version->fresh());
        $document = Document::create(['documentable_type' => \App\Models\ContractVersion::class, 'documentable_id' => $version->id, 'title' => 'Second sale', 'created_by' => $user->id]);
        Storage::disk(config('filesystems.default'))->put('contracts/sale-second.pdf', 'second');
        $documentVersion = DocumentVersion::create(['company_id' => $company->id, 'document_id' => $document->id, 'version_number' => 1, 'file_name' => 'sale-second.pdf', 'file_path' => 'contracts/sale-second.pdf', 'uploaded_by' => $user->id]);
        $workflow->recordSignature($user, $version->fresh(), $version->parties()->firstOrFail(), $documentVersion, 'Buyer', 'manual');

        $this->expectException(ValidationException::class);
        $workflow->activate($user, $second, $version->fresh());
    }

    /** @return array{0:Company,1:User,2:Unit} */
    private function companyUserAndUnit(string $prefix = 'Contract Test'): array
    {
        $company = Company::create(['name' => $prefix.' Co', 'email' => str($prefix)->slug().'@example.test']);
        $user = User::factory()->create(['company_id' => $company->id]);
        $location = Location::create(['company_id' => $company->id, 'name' => $prefix.' City', 'type' => 'city']);
        $property = Property::create(['company_id' => $company->id, 'location_id' => $location->id, 'name' => $prefix.' Property', 'address' => 'Test address']);
        $unit = Unit::create(['company_id' => $company->id, 'property_id' => $property->id, 'unit_number' => 'A-101', 'rent_price' => 0, 'status' => 'draft', 'type' => 'apartment']);

        return [$company, $user, $unit];
    }

    private function grant(User $user, array $permissions): void
    {
        foreach ($permissions as $permission) {
            Permission::findOrCreate($permission, 'web');
        }
        $user->givePermissionTo($permissions);
    }

    private function draft(User $user, Unit $unit, Party $buyer, int $amount, array $extraParties = []): Contract
    {
        return app(ContractDraftService::class)->create($user, [
            'contract_type' => ContractType::Sale,
            'unit_id' => $unit->id,
            'total_amount' => $amount,
            'currency' => 'USD',
            'parties' => array_merge([['party_id' => $buyer->id, 'role' => 'buyer', 'share_percentage' => 100]], $extraParties),
            'terms' => [['term_type' => 'commercial', 'title' => 'Price', 'text' => 'Agreed purchase price']],
        ]);
    }

    private function leaseDraft(User $user, Unit $unit, Party $tenant, string $start, string $end): Contract
    {
        return app(ContractDraftService::class)->create($user, [
            'contract_type' => ContractType::Lease,
            'unit_id' => $unit->id,
            'total_amount' => 1000,
            'currency' => 'USD',
            'start_date' => $start,
            'end_date' => $end,
            'parties' => [['party_id' => $tenant->id, 'role' => 'tenant']],
            'terms' => [['term_type' => 'commercial', 'title' => 'Rent', 'text' => 'Monthly rent']],
        ]);
    }

    private function signAndActivate(User $user, Contract $contract, string $file): void
    {
        $workflow = app(ContractWorkflowService::class);
        $version = $contract->versions()->firstOrFail();
        $workflow->submit($user, $version);
        $workflow->approve($user, $version->fresh());
        $document = Document::create(['documentable_type' => \App\Models\ContractVersion::class, 'documentable_id' => $version->id, 'title' => $file, 'created_by' => $user->id]);
        Storage::disk(config('filesystems.default'))->put('contracts/'.$file, $file);
        $documentVersion = DocumentVersion::create(['company_id' => $contract->company_id, 'document_id' => $document->id, 'version_number' => 1, 'file_name' => $file, 'file_path' => 'contracts/'.$file, 'uploaded_by' => $user->id]);
        $workflow->recordSignature($user, $version->fresh(), $version->parties()->firstOrFail(), $documentVersion, 'Tenant', 'manual');
        $workflow->activate($user, $contract, $version->fresh());
    }
}
