<?php

namespace Tests\Feature;

use App\Enums\{CrmInquirySource, OfferProposedBySide, OpportunityType, ReservationSource, ReservationStatus};
use App\Filament\Resources\UnitReservationResource;
use App\Models\{Company, CrmOffer, Listing, Location, Party, Project, ProjectBuilding, ProjectBuildingFloor, ProjectPlannedUnit, ProjectProperty, Property, Unit, UnitReservation, User};
use App\Services\{LeadIntakeService, OfferNegotiationService, OpportunityService, ReservationWorkflowService, UnitSetupService};
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class ReservationWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private function company(string $name): Company { return Company::create(['name' => $name, 'email' => strtolower($name).'@example.test']); }
    private function user(Company $company, array $permissions): User { $user = User::factory()->create(['company_id' => $company->id]); foreach ($permissions as $permission) $user->givePermissionTo(Permission::findOrCreate($permission, 'web')); return $user; }
    private function permissions(): array { return ['create_unit::reservation', 'view_any_unit::reservation', 'view_unit::reservation', 'update_unit::reservation', 'delete_unit::reservation', 'send_reservation_for_approval', 'approve_and_activate_reservation', 'reject_reservation_request', 'cancel_unit_reservation', 'extend_unit_reservation', 'expire_unit_reservation', 'create_lead', 'create_opportunity', 'create_crm::offer', 'create_offer_revision', 'finalize_offer_revision', 'confirm_offer_agreement', 'create_unit', 'create_actual_unit_from_planned_unit', 'change_unit_status']; }
    private function unit(Company $company): Unit { $location = Location::withoutGlobalScopes()->create(['company_id' => $company->id, 'name' => 'City '.$company->id, 'type' => 'city']); $property = Property::withoutGlobalScopes()->create(['company_id' => $company->id, 'location_id' => $location->id, 'name' => 'Property '.$company->id, 'address' => 'Address']); return Unit::withoutGlobalScopes()->create(['company_id' => $company->id, 'property_id' => $property->id, 'unit_number' => 'U-'.uniqid(), 'type' => 'apartment', 'status' => 'ready']); }
    private function plannedUnit(Company $company): ProjectPlannedUnit { $project = Project::withoutGlobalScopes()->create(['company_id' => $company->id, 'name' => 'Project '.$company->id, 'project_type' => 'residential', 'currency' => 'USD', 'status' => 'in_progress']); $building = ProjectBuilding::withoutGlobalScopes()->create(['company_id' => $company->id, 'project_id' => $project->id, 'name' => 'Tower']); $floor = ProjectBuildingFloor::withoutGlobalScopes()->create(['company_id' => $company->id, 'project_building_id' => $building->id, 'floor_number' => 1]); return ProjectPlannedUnit::withoutGlobalScopes()->create(['company_id' => $company->id, 'project_building_floor_id' => $floor->id, 'code' => 'P-'.uniqid(), 'unit_type' => 'apartment', 'status' => 'approved']); }
    private function customer(Company $company): Party { return Party::withoutGlobalScopes()->create(['company_id' => $company->id, 'type' => 'individual', 'name' => 'Customer '.$company->id, 'is_active' => true]); }
    private function direct(User $actor, Unit $unit, Party $customer): UnitReservation { return app(ReservationWorkflowService::class)->createDraft($actor, ['unit_id' => $unit->id, 'customer_party_id' => $customer->id, 'source' => ReservationSource::Direct->value, 'transaction_type' => OpportunityType::Sale->value, 'agreed_amount' => 100000, 'currency' => 'usd', 'down_payment_amount' => 10000]); }

    public function test_direct_reservation_transitions_to_active_and_creates_an_immutable_snapshot(): void
    {
        $company = $this->company('A'); $actor = $this->user($company, $this->permissions()); $unit = $this->unit($company); $reservation = $this->direct($actor, $unit, $this->customer($company)); $service = app(ReservationWorkflowService::class);
        $this->assertMatchesRegularExpression('/^RES-'.now()->format('Y').'-000001$/', $reservation->reservation_number);
        $pending = $service->sendForApproval($actor, $reservation); $active = $service->approveAndActivate($actor, $pending);
        $this->assertSame(ReservationStatus::Active, $active->status); $this->assertSame(1, $active->snapshot()->count());
        $snapshot = $active->snapshot; $this->assertSame($unit->unit_number, $snapshot->unit_data['unit_number']); $this->assertSame('USD', $snapshot->commercial_terms['currency']);
        $this->assertSame(['approve_and_activate', 'send_for_approval'], $active->statusHistories()->pluck('action')->all());
        $unit->update(['unit_number' => 'Changed after activation']);
        $this->assertSame($snapshot->unit_data['unit_number'], $active->fresh()->snapshot->unit_data['unit_number']);
    }

    public function test_pending_requests_do_not_block_but_second_active_reservation_is_rejected(): void
    {
        $company = $this->company('A'); $actor = $this->user($company, $this->permissions()); $unit = $this->unit($company); $first = $this->direct($actor, $unit, $this->customer($company)); $second = $this->direct($actor, $unit, $this->customer($company)); $service = app(ReservationWorkflowService::class);
        $service->sendForApproval($actor, $first); $service->sendForApproval($actor, $second); $service->approveAndActivate($actor, $first);
        try { $service->approveAndActivate($actor, $second); $this->fail('Second active reservation was allowed.'); } catch (ValidationException) { $this->assertSame(ReservationStatus::Pending, $second->fresh()->status); }
    }

    public function test_accepted_offer_reservation_derives_the_unit_and_agreed_terms_without_fake_records(): void
    {
        $company = $this->company('A'); $actor = $this->user($company, $this->permissions()); $unit = $this->unit($company);
        $listing = Listing::withoutGlobalScopes()->create(['company_id' => $company->id, 'listable_type' => 'unit', 'listable_id' => $unit->id, 'listing_type' => 'sale', 'title' => 'Unit listing', 'asking_price' => 120000, 'currency' => 'USD', 'created_by' => $actor->id]);
        $lead = app(LeadIntakeService::class)->createLead($actor, ['name' => 'Nour', 'phone' => '0591234567', 'source' => CrmInquirySource::Manual->value]);
        $opportunity = app(OpportunityService::class)->create($actor, ['lead_id' => $lead->id, 'listing_id' => $listing->id, 'type' => OpportunityType::Sale->value, 'source' => CrmInquirySource::Manual->value]);
        $offers = app(OfferNegotiationService::class); $offer = $offers->createOffer($actor, ['opportunity_id' => $opportunity->id, 'listing_id' => $listing->id, 'transaction_type' => 'sale']);
        $revision = $offers->createDraftRevision($actor, $offer, ['proposed_by_side' => OfferProposedBySide::Customer->value, 'amount' => 110000, 'currency' => 'USD']); $revision = $offers->submitRevision($actor, $revision); $offers->confirmAgreement($actor, $revision);
        $reservation = app(ReservationWorkflowService::class)->createDraft($actor, ['source' => ReservationSource::AcceptedOffer->value, 'offer_id' => $offer->id, 'customer_party_id' => $this->customer($company)->id]);
        $this->assertSame($unit->id, $reservation->unit_id); $this->assertSame($revision->id, $reservation->offer_revision_id); $this->assertSame('110000.00', $reservation->agreed_amount);
    }

    public function test_accepted_off_plan_offer_uses_the_actual_unit_created_from_its_planned_unit(): void
    {
        $company = $this->company('A'); $actor = $this->user($company, $this->permissions()); $planned = $this->plannedUnit($company); $property = $this->unit($company)->property; $project = $planned->floor->building->project;
        ProjectProperty::withoutGlobalScopes()->create(['company_id' => $company->id, 'project_id' => $project->id, 'property_id' => $property->id, 'attached_at' => now()]);
        $unit = app(UnitSetupService::class)->convertPlannedUnit($actor, $planned, ['property_id' => $property->id, 'unit_number' => 'A-'.uniqid(), 'actual_area' => 100, 'area_unit' => 'm2']);
        $unit = app(UnitSetupService::class)->transitionStatus($actor, $unit, 'ready');
        $listing = Listing::withoutGlobalScopes()->create(['company_id' => $company->id, 'listable_type' => 'planned_unit', 'listable_id' => $planned->id, 'listing_type' => 'sale', 'title' => 'Off-plan listing', 'asking_price' => 120000, 'currency' => 'USD', 'created_by' => $actor->id]);
        $lead = app(LeadIntakeService::class)->createLead($actor, ['name' => 'Nour', 'phone' => '0591234567', 'source' => CrmInquirySource::Manual->value]);
        $opportunity = app(OpportunityService::class)->create($actor, ['lead_id' => $lead->id, 'listing_id' => $listing->id, 'type' => OpportunityType::Sale->value, 'source' => CrmInquirySource::Manual->value]);
        $offers = app(OfferNegotiationService::class); $offer = $offers->createOffer($actor, ['opportunity_id' => $opportunity->id, 'listing_id' => $listing->id, 'transaction_type' => 'sale']);
        $revision = $offers->createDraftRevision($actor, $offer, ['proposed_by_side' => OfferProposedBySide::Customer->value, 'amount' => 110000, 'currency' => 'USD']); $offers->confirmAgreement($actor, $offers->submitRevision($actor, $revision));

        $reservation = app(ReservationWorkflowService::class)->createDraft($actor, ['source' => ReservationSource::AcceptedOffer->value, 'offer_id' => $offer->id, 'customer_party_id' => $this->customer($company)->id]);

        $this->assertSame($unit->id, $reservation->unit_id);
        $this->assertStringContainsString($unit->unit_number, UnitReservationResource::acceptedOfferOptions($company->id)[$offer->id]);
    }

    public function test_accepted_offer_without_a_listing_requires_and_uses_an_explicit_ready_actual_unit(): void
    {
        $company = $this->company('A'); $actor = $this->user($company, $this->permissions()); $unit = $this->unit($company);
        $lead = app(LeadIntakeService::class)->createLead($actor, ['name' => 'Nour', 'phone' => '0591234567', 'source' => CrmInquirySource::Manual->value]);
        $opportunity = app(OpportunityService::class)->create($actor, ['lead_id' => $lead->id, 'type' => OpportunityType::Sale->value, 'source' => CrmInquirySource::Manual->value]);
        $offers = app(OfferNegotiationService::class); $offer = $offers->createOffer($actor, ['opportunity_id' => $opportunity->id, 'transaction_type' => 'sale']);
        $revision = $offers->createDraftRevision($actor, $offer, ['proposed_by_side' => OfferProposedBySide::Customer->value, 'amount' => 110000, 'currency' => 'USD']); $offers->confirmAgreement($actor, $offers->submitRevision($actor, $revision));

        try {
            app(ReservationWorkflowService::class)->createDraft($actor, ['source' => ReservationSource::AcceptedOffer->value, 'offer_id' => $offer->id, 'customer_party_id' => $this->customer($company)->id]);
            $this->fail('An unlisted Offer created a reservation without an explicit Unit.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('unit_id', $exception->errors());
        }

        $reservation = app(ReservationWorkflowService::class)->createDraft($actor, ['source' => ReservationSource::AcceptedOffer->value, 'offer_id' => $offer->id, 'unit_id' => $unit->id, 'customer_party_id' => $this->customer($company)->id]);

        $this->assertSame($unit->id, $reservation->unit_id);
        $this->assertStringContainsString(__('reservations.labels.no_listing'), UnitReservationResource::acceptedOfferOptions($company->id)[$offer->id]);
    }

    public function test_cross_company_injection_and_unauthorized_activation_are_rejected(): void
    {
        $a = $this->company('A'); $b = $this->company('B'); $actor = $this->user($a, $this->permissions()); $foreign = $this->unit($b); $service = app(ReservationWorkflowService::class);
        try { $service->createDraft($actor, ['unit_id' => $foreign->id, 'customer_party_id' => $this->customer($a)->id, 'source' => 'direct', 'transaction_type' => 'sale', 'agreed_amount' => 1, 'currency' => 'USD']); $this->fail('Cross-company Unit accepted.'); } catch (ValidationException) { $this->assertTrue(true); }
        $reservation = $this->direct($actor, $this->unit($a), $this->customer($a)); $pending = $service->sendForApproval($actor, $reservation); $unauthorized = $this->user($a, []);
        $this->expectException(AuthorizationException::class); $service->approveAndActivate($unauthorized, $pending);
    }

    public function test_forged_past_validity_date_is_rejected_by_the_domain_not_only_the_form(): void
    {
        $company = $this->company('A'); $actor = $this->user($company, $this->permissions());
        $this->expectException(ValidationException::class);
        app(ReservationWorkflowService::class)->createDraft($actor, ['unit_id' => $this->unit($company)->id, 'customer_party_id' => $this->customer($company)->id, 'source' => 'direct', 'transaction_type' => 'sale', 'agreed_amount' => 100000, 'currency' => 'USD', 'valid_until' => now()->subMinute()->toDateTimeString()]);
    }

    public function test_active_reservations_can_be_extended_cancelled_and_expired_with_auditable_history(): void
    {
        $company = $this->company('A'); $actor = $this->user($company, $this->permissions()); $service = app(ReservationWorkflowService::class);
        $reservation = $this->direct($actor, $this->unit($company), $this->customer($company));
        $active = $service->approveAndActivate($actor, $service->sendForApproval($actor, $reservation));
        $extended = $service->extendReservation($actor, $active, now()->addDay()->toDateTimeString());
        $this->assertSame(ReservationStatus::Active, $extended->status); $this->assertNotNull($extended->valid_until);
        $cancelled = $service->cancelReservation($actor, $extended, 'Customer changed their plans');
        $this->assertSame(ReservationStatus::Cancelled, $cancelled->status); $this->assertSame($actor->id, $cancelled->cancelled_by);

        $due = $this->direct($actor, $this->unit($company), $this->customer($company));
        $due = $service->approveAndActivate($actor, $service->sendForApproval($actor, $due));
        $due->forceFill(['valid_until' => now()->subMinute()])->save();
        $this->assertSame(1, $service->expireDueReservations());
        $this->assertSame(ReservationStatus::Expired, $due->fresh()->status);
        $this->assertNull($due->fresh()->statusHistories()->first()->changed_by);
    }
}
