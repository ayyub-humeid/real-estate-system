<?php

namespace Tests\Feature;

use App\Enums\{CrmInquirySource, OfferProposedBySide, OfferStatus, OpportunityType, ReservationSource};
use App\Filament\Resources\CrmOfferResource\Pages\ViewCrmOffer;
use App\Models\{Company, CrmOffer, CrmOfferApproval, Listing, Location, Party, Project, ProjectBuilding, ProjectBuildingFloor, ProjectPlannedUnit, Property, Unit, User};
use App\Services\{LeadIntakeService, OfferNegotiationService, OpportunityService, ReservationWorkflowService};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * An offer names a unit that can really be reserved, checked when the offer is created and again
 * when the customer agrees, so the reservation never fails at the very last step.
 */
class OfferUnitRuleTest extends TestCase
{
    use RefreshDatabase;

    private const PERMISSIONS = ['create_lead', 'create_opportunity', 'create_crm::offer', 'view_any_crm::offer', 'view_crm::offer', 'update_crm::offer',
        'create_offer_revision', 'finalize_offer_revision', 'confirm_offer_agreement', 'accept_offer', 'convert_offer_to_reservation',
        'create_unit::reservation', 'view_any_unit::reservation', 'view_unit::reservation'];

    private function company(): Company
    {
        return Company::create(['name' => 'Madar '.uniqid(), 'email' => uniqid().'@example.test']);
    }

    private function actor(Company $company): User
    {
        $user = User::factory()->create(['company_id' => $company->id]);
        foreach (self::PERMISSIONS as $permission) {
            $user->givePermissionTo(Permission::findOrCreate($permission, 'web'));
        }

        return $user;
    }

    private function unit(Company $company, string $status = 'ready'): Unit
    {
        $location = Location::withoutGlobalScopes()->create(['company_id' => $company->id, 'name' => 'City '.uniqid(), 'type' => 'city']);
        $property = Property::withoutGlobalScopes()->create(['company_id' => $company->id, 'location_id' => $location->id, 'name' => 'Property '.uniqid(), 'address' => 'Address']);

        return Unit::withoutGlobalScopes()->create(['company_id' => $company->id, 'property_id' => $property->id, 'unit_number' => 'U-'.uniqid(), 'type' => 'apartment', 'status' => $status]);
    }

    private function plannedUnit(Company $company): ProjectPlannedUnit
    {
        $project = Project::withoutGlobalScopes()->create(['company_id' => $company->id, 'name' => 'Project '.uniqid(), 'project_type' => 'residential', 'currency' => 'USD', 'status' => 'in_progress']);
        $building = ProjectBuilding::withoutGlobalScopes()->create(['company_id' => $company->id, 'project_id' => $project->id, 'name' => 'Tower']);
        $floor = ProjectBuildingFloor::withoutGlobalScopes()->create(['company_id' => $company->id, 'project_building_id' => $building->id, 'floor_number' => 1]);

        return ProjectPlannedUnit::withoutGlobalScopes()->create(['company_id' => $company->id, 'project_building_floor_id' => $floor->id, 'code' => 'P-'.uniqid(), 'unit_type' => 'apartment', 'status' => 'approved']);
    }

    private function listing(Company $company, User $actor, string $type, int $id): Listing
    {
        return Listing::withoutGlobalScopes()->create(['company_id' => $company->id, 'listable_type' => $type, 'listable_id' => $id, 'listing_type' => 'sale',
            'title' => 'Listing '.uniqid(), 'asking_price' => 120000, 'currency' => 'USD', 'created_by' => $actor->id]);
    }

    private function opportunity(User $actor, ?Listing $listing = null): \App\Models\Opportunity
    {
        $lead = app(LeadIntakeService::class)->createLead($actor, ['name' => 'Nour', 'phone' => '059'.random_int(1000000, 9999999), 'source' => CrmInquirySource::Manual->value]);

        return app(OpportunityService::class)->create($actor, ['lead_id' => $lead->id, 'listing_id' => $listing?->id, 'type' => OpportunityType::Sale->value, 'source' => CrmInquirySource::Manual->value]);
    }

    private function accept(User $actor, CrmOffer $offer): void
    {
        $offers = app(OfferNegotiationService::class);
        $revision = $offers->createDraftRevision($actor, $offer, ['proposed_by_side' => OfferProposedBySide::Customer->value, 'amount' => 110000, 'currency' => 'USD']);
        $offers->confirmAgreement($actor, $offers->submitRevision($actor, $revision));
    }

    private function refusedWith(callable $action, string $field, string $message): void
    {
        try {
            $action();
            $this->fail("Expected the action to be refused on [$field].");
        } catch (ValidationException $exception) {
            $this->assertSame($message, $exception->errors()[$field][0] ?? null);
        }
    }

    public function test_a_listing_saved_with_the_full_class_name_still_finds_its_unit_all_the_way_to_the_reservation(): void
    {
        $company = $this->company();
        $actor = $this->actor($company);
        $unit = $this->unit($company);
        $listing = $this->listing($company, $actor, Unit::class, $unit->id);

        $offer = app(OfferNegotiationService::class)->createOffer($actor, ['opportunity_id' => $this->opportunity($actor, $listing)->id, 'listing_id' => $listing->id, 'transaction_type' => 'sale']);
        $this->assertSame($unit->id, (int) $offer->unit_id, 'The offer takes its unit from the listing.');

        $this->accept($actor, $offer->fresh());
        $party = Party::withoutGlobalScopes()->create(['company_id' => $company->id, 'type' => 'individual', 'name' => 'Customer', 'is_active' => true]);
        $reservation = app(ReservationWorkflowService::class)->createDraft($actor, ['source' => ReservationSource::AcceptedOffer->value, 'offer_id' => $offer->id, 'customer_party_id' => $party->id]);

        $this->assertSame($unit->id, (int) $reservation->unit_id);
    }

    public function test_an_offer_cannot_name_a_different_unit_than_its_listing(): void
    {
        $company = $this->company();
        $actor = $this->actor($company);
        $listing = $this->listing($company, $actor, 'unit', $this->unit($company)->id);
        $other = $this->unit($company);

        $this->refusedWith(
            fn () => app(OfferNegotiationService::class)->createOffer($actor, ['opportunity_id' => $this->opportunity($actor, $listing)->id, 'listing_id' => $listing->id, 'transaction_type' => 'sale', 'unit_id' => $other->id]),
            'unit_id', __('crm.offers.errors.unit_listing_mismatch'),
        );
        $this->assertSame(0, CrmOffer::withoutGlobalScopes()->count());
    }

    public function test_an_offer_cannot_be_made_on_a_unit_that_is_not_available(): void
    {
        $company = $this->company();
        $actor = $this->actor($company);
        $reserved = $this->unit($company, 'reserved');

        $this->refusedWith(
            fn () => app(OfferNegotiationService::class)->createOffer($actor, ['opportunity_id' => $this->opportunity($actor)->id, 'transaction_type' => 'sale', 'unit_id' => $reserved->id]),
            'unit_id', __('crm.offers.errors.unit_not_available', ['unit' => $reserved->unit_number, 'status' => status_label('reserved')]),
        );
    }

    public function test_the_customer_cannot_accept_once_the_unit_was_taken_in_the_meantime(): void
    {
        $company = $this->company();
        $actor = $this->actor($company);
        $unit = $this->unit($company);
        $offer = app(OfferNegotiationService::class)->createOffer($actor, ['opportunity_id' => $this->opportunity($actor)->id, 'transaction_type' => 'sale', 'unit_id' => $unit->id]);

        $unit->forceFill(['status' => 'reserved'])->save();

        $this->refusedWith(fn () => $this->accept($actor, $offer), 'unit_id',
            __('crm.offers.errors.unit_not_available', ['unit' => $unit->unit_number, 'status' => status_label('reserved')]));
        $this->assertNotSame(OfferStatus::Accepted, $offer->fresh()->status);
    }

    /** Every step of the negotiation re-checks the unit, so the work stops the moment someone else takes it. */
    public function test_a_unit_taken_mid_negotiation_stops_the_very_next_step_not_the_reservation(): void
    {
        $offers = app(OfferNegotiationService::class);
        $steps = [
            'send the price' => function (User $actor, CrmOffer $offer) use ($offers): void {
                $offers->submitRevision($actor, $offers->createDraftRevision($actor, $offer, ['proposed_by_side' => OfferProposedBySide::Company->value, 'amount' => 120000, 'currency' => 'USD']));
            },
            'ask for approval' => function (User $actor, CrmOffer $offer) use ($offers): void {
                $revision = $offers->submitRevision($actor, $offers->createDraftRevision($actor, $offer, ['proposed_by_side' => OfferProposedBySide::Company->value, 'amount' => 120000, 'currency' => 'USD']));
                $offers->requestApproval($actor, $revision);
            },
        ];

        foreach ($steps as $step => $run) {
            $company = $this->company();
            $actor = $this->actor($company);
            $actor->givePermissionTo(Permission::findOrCreate('request_offer_approval', 'web'));
            $unit = $this->unit($company);
            $offer = $offers->createOffer($actor, ['opportunity_id' => $this->opportunity($actor)->id, 'transaction_type' => 'sale', 'unit_id' => $unit->id]);

            $unit->forceFill(['status' => 'sold'])->save();

            $this->refusedWith(fn () => $run($actor, $offer->fresh()), 'unit_id',
                __('crm.offers.errors.unit_not_available', ['unit' => $unit->unit_number, 'status' => status_label('sold')]),
            );
            $this->assertSame(0, CrmOfferApproval::withoutGlobalScopes()->count(), "Step [$step] let the deal carry on.");
        }
    }

    public function test_the_offer_page_says_the_unit_is_gone_instead_of_leaving_the_seller_to_find_out(): void
    {
        $company = $this->company();
        $actor = $this->actor($company);
        $unit = $this->unit($company);
        $offer = app(OfferNegotiationService::class)->createOffer($actor, ['opportunity_id' => $this->opportunity($actor)->id, 'transaction_type' => 'sale', 'unit_id' => $unit->id]);
        $this->actingAs($actor);

        $warning = __('sales.offer_next.unit_taken', ['unit' => $unit->unit_number, 'status' => status_label('sold')]);
        Livewire::test(ViewCrmOffer::class, ['record' => $offer->getRouteKey()])->assertDontSee($warning);

        $unit->forceFill(['status' => 'sold'])->save();

        Livewire::test(ViewCrmOffer::class, ['record' => $offer->getRouteKey()])
            ->assertSee($warning)
            ->assertSee(status_label('sold'));
    }

    public function test_an_off_plan_offer_can_be_agreed_but_explains_that_the_unit_must_be_built_before_reserving(): void
    {
        $company = $this->company();
        $actor = $this->actor($company);
        $listing = $this->listing($company, $actor, 'planned_unit', $this->plannedUnit($company)->id);
        $offer = app(OfferNegotiationService::class)->createOffer($actor, ['opportunity_id' => $this->opportunity($actor, $listing)->id, 'listing_id' => $listing->id, 'transaction_type' => 'sale']);
        $this->accept($actor, $offer);
        $this->assertSame(OfferStatus::Accepted, $offer->fresh()->status);

        $this->actingAs($actor);
        Livewire::test(ViewCrmOffer::class, ['record' => $offer->getRouteKey()])
            ->assertActionVisible('convert_to_reservation')
            ->assertActionDisabled('convert_to_reservation')
            ->assertSee(__('sales.offer_next.awaiting_unit', ['amount' => '110,000 USD']));

        $this->refusedWith(fn () => app(ReservationWorkflowService::class)->createFromOffer($actor, $offer->fresh()),
            'offer_id', __('reservations.errors.offer_unit_not_built'));
    }

    public function test_an_older_offer_whose_unit_differs_from_its_listing_is_explained_not_silently_reserved(): void
    {
        $company = $this->company();
        $actor = $this->actor($company);
        $listing = $this->listing($company, $actor, 'unit', $this->unit($company)->id);
        $offer = app(OfferNegotiationService::class)->createOffer($actor, ['opportunity_id' => $this->opportunity($actor, $listing)->id, 'listing_id' => $listing->id, 'transaction_type' => 'sale']);
        $this->accept($actor, $offer);
        // Saved before the rule existed: the offer points at another unit.
        $offer->forceFill(['unit_id' => $this->unit($company)->id])->save();

        $this->refusedWith(fn () => app(ReservationWorkflowService::class)->createFromOffer($actor, $offer->fresh()),
            'offer_id', __('reservations.errors.offer_unit_mismatch'));
    }
}
