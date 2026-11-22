<?php

namespace Tests\Feature;

use App\Enums\{CrmInquirySource, OfferStatus, OpportunityType, ReservationStatus, UnitStatus};
use App\Filament\Resources\CrmOfferResource\Pages\ViewCrmOffer;
use App\Filament\Resources\LeadResource\Pages\ViewLead;
use App\Models\{Company, CrmOffer, Lead, Location, Opportunity, Party, Property, Unit, UnitReservation, User};
use App\Services\{LeadIntakeService, LeadPartyConversionService, OfferNegotiationService, OpportunityService, ReservationWorkflowService};
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * No duplicate clients, and accepted offer -> reservation in one action:
 * the client is found or created, the unit and price come from the offer.
 */
class OfferToReservationOneStepTest extends TestCase
{
    use RefreshDatabase;

    private const BASE = ['create_lead', 'view_any_lead', 'view_lead', 'update_lead', 'convert_lead_to_party', 'create_opportunity', 'view_any_opportunity', 'view_opportunity',
        'create_crm::offer', 'view_any_crm::offer', 'view_crm::offer', 'create_offer_revision', 'view_offer_revision', 'view_any_offer_revision', 'view_any_offer_approval',
        'finalize_offer_revision', 'confirm_offer_agreement', 'accept_offer', 'convert_offer_to_reservation', 'create_unit::reservation', 'view_unit::reservation', 'view_any_unit::reservation'];
    private const APPROVER = ['send_reservation_for_approval', 'approve_and_activate_reservation'];

    private function company(string $name = 'Madar'): Company
    {
        return Company::create(['name' => $name, 'email' => strtolower($name).'@test.local']);
    }

    private function actor(Company $company, array $permissions = self::BASE): User
    {
        $user = User::factory()->create(['company_id' => $company->id]);
        foreach ($permissions as $permission) {
            $user->givePermissionTo(Permission::findOrCreate($permission, 'web'));
        }

        return $user;
    }

    private function unit(Company $company, string $number = 'A-101'): Unit
    {
        $location = Location::withoutGlobalScopes()->firstOrCreate(['company_id' => $company->id, 'name' => 'City'], ['type' => 'city']);
        $property = Property::withoutGlobalScopes()->firstOrCreate(['company_id' => $company->id, 'name' => 'Tower'], ['location_id' => $location->id, 'address' => 'Address']);

        return Unit::withoutGlobalScopes()->create(['company_id' => $company->id, 'property_id' => $property->id, 'unit_number' => $number, 'type' => 'apartment', 'status' => UnitStatus::Ready->value]);
    }

    private function lead(User $actor, string $phone = '0591234567', ?string $email = null, string $name = 'Nour'): Lead
    {
        return app(LeadIntakeService::class)->createLead($actor, ['name' => $name, 'phone' => $phone, 'email' => $email, 'source' => CrmInquirySource::Manual->value]);
    }

    private function clients(Company $company): int
    {
        return Party::withoutGlobalScopes()->where('company_id', $company->id)->count();
    }

    private function client(Company $company, array $attributes = []): Party
    {
        return Party::withoutGlobalScopes()->create(['company_id' => $company->id, 'type' => 'individual', 'name' => 'Existing Client', 'is_active' => true, ...$attributes]);
    }

    /** An ACCEPTED offer. By default the opportunity carries a unit; pass withUnit:false for an offer without one. */
    private function acceptedOffer(User $actor, Lead $lead, ?Unit $unit, float $amount = 110000): CrmOffer
    {
        $opportunity = app(OpportunityService::class)->create($actor, ['lead_id' => $lead->id, 'type' => OpportunityType::Sale->value, 'unit_id' => $unit?->id]);
        $offers = app(OfferNegotiationService::class);
        $offer = $offers->createQuickOffer($actor, ['opportunity_id' => $opportunity->id, 'amount' => $amount, 'currency' => 'USD', 'send_now' => true]);
        $offers->confirmAgreement($actor, $offer->revisions()->firstOrFail());

        return $offer->fresh();
    }

    // ───────────────────────── 3.D  no duplicate clients ─────────────────────────

    public function test_converting_a_lead_links_the_existing_client_with_the_same_phone_instead_of_duplicating(): void
    {
        $company = $this->company();
        $actor = $this->actor($company);
        $existing = $this->client($company, ['phone' => '+970 59 123 4567']); // typed differently from the lead
        $lead = $this->lead($actor, '0591234567');
        $before = $this->clients($company);

        $party = app(LeadPartyConversionService::class)->convert($actor, $lead);

        $this->assertSame($existing->id, $party->id);
        $this->assertFalse($party->wasRecentlyCreated);
        $this->assertSame($lead->id, $party->fresh()->lead_id);
        $this->assertSame($before, $this->clients($company), 'No second client may be created.');
    }

    public function test_the_existing_client_can_also_be_found_by_email(): void
    {
        $company = $this->company();
        $actor = $this->actor($company);
        $existing = $this->client($company, ['email' => 'Nour@Example.test']);
        $lead = $this->lead($actor, '0599999999', 'nour@example.test');

        $this->assertSame($existing->id, app(LeadPartyConversionService::class)->convert($actor, $lead)->id);
    }

    public function test_a_new_client_is_created_when_nobody_matches_and_gets_normalized_contact_fields(): void
    {
        $company = $this->company();
        $actor = $this->actor($company);
        $this->client($company, ['name' => 'Someone Else', 'phone' => '0598888888']);
        $before = $this->clients($company);

        $party = app(LeadPartyConversionService::class)->convert($actor, $this->lead($actor, '0591234567', 'Nour@Example.test'));

        $this->assertTrue($party->wasRecentlyCreated);
        $this->assertSame('+970591234567', $party->normalized_phone);
        $this->assertSame('nour@example.test', $party->normalized_email);
        $this->assertSame($before + 1, $this->clients($company));
    }

    public function test_editing_a_clients_phone_keeps_the_normalized_value_in_sync(): void
    {
        $company = $this->company();
        $party = $this->client($company, ['phone' => '0591234567']);
        $this->assertSame('+970591234567', $party->normalized_phone);

        $party->update(['phone' => '00972 59 777 7777']);
        $this->assertSame('+972597777777', $party->fresh()->normalized_phone);

        $party->update(['phone' => null]);
        $this->assertNull($party->fresh()->normalized_phone);
    }

    public function test_unclear_matches_are_refused_with_a_message_and_nothing_changes(): void
    {
        $company = $this->company();
        $actor = $this->actor($company);
        $service = app(LeadPartyConversionService::class);

        // Linked to someone else's lead.
        $otherLead = $this->lead($actor, '0590000001', null, 'Other Lead');
        $taken = $this->client($company, ['name' => 'Taken Client', 'phone' => '0591234567', 'lead_id' => $otherLead->id]);
        try {
            $service->convert($actor, $this->lead($actor, '0591234567'));
            $this->fail('A client already tied to another lead must not be taken over.');
        } catch (ValidationException $exception) {
            $this->assertStringContainsString('Other Lead', $exception->errors()['lead'][0]);
        }
        $this->assertSame($otherLead->id, $taken->fresh()->lead_id);

        // Two different clients match (one by phone, one by email).
        $this->client($company, ['name' => 'By Phone', 'phone' => '0597000000']);
        $this->client($company, ['name' => 'By Email', 'email' => 'dup@example.test']);
        try {
            $service->convert($actor, $this->lead($actor, '0597000000', 'dup@example.test', 'Two Matches'));
            $this->fail('Two matching clients must be refused.');
        } catch (ValidationException $exception) {
            $this->assertStringContainsString('By Phone', $exception->errors()['lead'][0]);
            $this->assertStringContainsString('By Email', $exception->errors()['lead'][0]);
        }

        // An inactive match.
        $this->client($company, ['name' => 'Retired Client', 'phone' => '0596000000', 'is_active' => false]);
        $this->expectException(ValidationException::class);
        $service->convert($actor, $this->lead($actor, '0596000000', null, 'Retired Lead'));
    }

    public function test_lead_page_links_the_existing_client_tells_the_user_and_stays_on_the_lead(): void
    {
        $company = $this->company();
        $actor = $this->actor($company);
        $existing = $this->client($company, ['name' => 'Returning Client', 'phone' => '0591234567']);
        $lead = $this->lead($actor);
        $this->actingAs($actor);

        Livewire::test(ViewLead::class, ['record' => $lead->getRouteKey()])
            ->assertActionVisible('convertToParty')
            ->callAction('convertToParty')
            ->assertNoRedirect()
            ->assertNotified(__('sales.party.linked_title'))
            ->assertActionHidden('convertToParty')
            ->assertSee('Returning Client');

        $this->assertSame($lead->id, $existing->fresh()->lead_id);
    }

    // ───────────────────────── 3.C  offer -> reservation in one step ─────────────────────────

    public function test_one_step_creates_the_client_and_a_draft_reservation_from_the_offer(): void
    {
        $company = $this->company();
        $actor = $this->actor($company);
        $unit = $this->unit($company);
        $lead = $this->lead($actor);
        $offer = $this->acceptedOffer($actor, $lead, $unit, 110000);

        $reservation = app(ReservationWorkflowService::class)->createFromOffer($actor, $offer);

        $this->assertSame(ReservationStatus::Draft, $reservation->status);
        $this->assertSame($unit->id, $reservation->unit_id, 'The unit comes from the offer, not picked again.');
        $this->assertSame('110000.00', (string) $reservation->agreed_amount);
        $this->assertSame($offer->id, $reservation->offer_id);
        $this->assertSame($lead->id, $reservation->customer->lead_id);
        $this->assertTrue($reservation->valid_until->between(now()->addDays(6), now()->addDays(8)));
        $this->assertSame(UnitStatus::Ready->value, $unit->fresh()->status, 'A draft does not hold the unit yet.');
    }

    public function test_activate_now_reserves_the_unit_for_users_who_may_approve(): void
    {
        $company = $this->company();
        $actor = $this->actor($company, [...self::BASE, ...self::APPROVER]);
        $unit = $this->unit($company);
        $offer = $this->acceptedOffer($actor, $this->lead($actor), $unit);

        $reservation = app(ReservationWorkflowService::class)->createFromOffer($actor, $offer, ['activate_now' => true, 'valid_until' => now()->addDays(3)]);

        $this->assertSame(ReservationStatus::Active, $reservation->status);
        $this->assertSame(UnitStatus::Reserved->value, $unit->fresh()->status);
        $this->assertDatabaseHas('reservation_status_histories', ['reservation_id' => $reservation->id, 'to_status' => 'pending']);
        $this->assertDatabaseHas('reservation_status_histories', ['reservation_id' => $reservation->id, 'to_status' => 'active']);
    }

    public function test_activating_without_the_approve_permission_fails_as_a_whole_and_leaves_nothing_behind(): void
    {
        $company = $this->company();
        $actor = $this->actor($company); // may convert, but may not approve
        $offer = $this->acceptedOffer($actor, $this->lead($actor), $this->unit($company));
        $before = $this->clients($company);

        try {
            app(ReservationWorkflowService::class)->createFromOffer($actor, $offer, ['activate_now' => true]);
            $this->fail('Activating needs the approve permissions.');
        } catch (AuthorizationException) {
        }

        $this->assertSame(0, UnitReservation::withoutGlobalScopes()->count());
        $this->assertSame($before, $this->clients($company), 'The client created on the way must be rolled back too.');
    }

    public function test_the_same_offer_cannot_be_converted_twice_unless_the_first_reservation_died(): void
    {
        $company = $this->company();
        $actor = $this->actor($company);
        $offer = $this->acceptedOffer($actor, $this->lead($actor), $this->unit($company));
        $service = app(ReservationWorkflowService::class);
        $first = $service->createFromOffer($actor, $offer);

        try {
            $service->createFromOffer($actor, $offer);
            $this->fail('A second reservation from the same offer must be refused.');
        } catch (ValidationException $exception) {
            $this->assertStringContainsString($first->reservation_number, $exception->errors()['offer_id'][0]);
        }

        $first->forceFill(['status' => ReservationStatus::Rejected])->save();
        $this->assertNotSame($first->id, $service->createFromOffer($actor, $offer)->id);
    }

    public function test_only_an_accepted_offer_can_become_a_reservation(): void
    {
        $company = $this->company();
        $actor = $this->actor($company);
        $opportunity = app(OpportunityService::class)->create($actor, ['lead_id' => $this->lead($actor)->id, 'type' => OpportunityType::Sale->value, 'unit_id' => $this->unit($company, 'N-1')->id]);
        $negotiating = app(OfferNegotiationService::class)->createQuickOffer($actor, ['opportunity_id' => $opportunity->id, 'amount' => 1000, 'currency' => 'USD', 'send_now' => true]);
        $this->assertSame(OfferStatus::Negotiating, $negotiating->status);

        $this->expectException(ValidationException::class);
        app(ReservationWorkflowService::class)->createFromOffer($actor, $negotiating);
    }

    public function test_an_offer_without_a_unit_is_refused_before_any_negotiation_starts(): void
    {
        $company = $this->company();
        $actor = $this->actor($company);

        // Refused at the start, not after pricing, approval and acceptance.
        try {
            $this->acceptedOffer($actor, $this->lead($actor), null);
            $this->fail('An offer without a unit was created.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('unit_id', $exception->errors());
            $this->assertSame(__('crm.offers.errors.unit_required'), $exception->errors()['unit_id'][0]);
        }
        $this->assertSame(0, \App\Models\CrmOffer::withoutGlobalScopes()->count());

        // With a unit, the accepted offer becomes a reservation for that same unit without asking again.
        $unit = $this->unit($company, 'B-202');
        $offer = $this->acceptedOffer($actor, $this->lead($actor, '0597654321'), $unit);
        $this->assertSame($unit->id, app(ReservationWorkflowService::class)->createFromOffer($actor, $offer)->unit_id);
    }

    public function test_a_user_without_convert_lead_permission_still_creates_the_client_during_reservation(): void
    {
        $company = $this->company();
        $seller = $this->actor($company);
        $offer = $this->acceptedOffer($seller, $this->lead($seller), $this->unit($company));
        $limited = $this->actor($company, array_diff(self::BASE, ['convert_lead_to_party']));
        $before = $this->clients($company);

        $reservation = app(ReservationWorkflowService::class)->createFromOffer($limited, $offer);

        $this->assertSame(ReservationStatus::Draft, $reservation->status);
        $this->assertSame($before + 1, $this->clients($company), 'The client was created even without convert_lead_to_party permission.');
    }

    public function test_another_company_cannot_convert_the_offer(): void
    {
        $company = $this->company();
        $actor = $this->actor($company);
        $offer = $this->acceptedOffer($actor, $this->lead($actor), $this->unit($company));
        $foreign = $this->actor($this->company('Other'));

        $this->expectException(AuthorizationException::class);
        app(ReservationWorkflowService::class)->createFromOffer($foreign, $offer);
    }

    public function test_the_offer_page_converts_in_one_click_and_then_links_to_the_reservation(): void
    {
        $company = $this->company();
        $actor = $this->actor($company, [...self::BASE, ...self::APPROVER]);
        $offer = $this->acceptedOffer($actor, $this->lead($actor), $this->unit($company));
        $this->actingAs($actor);

        Livewire::test(ViewCrmOffer::class, ['record' => $offer->getRouteKey()])
            ->assertActionVisible('convert_to_reservation')
            ->assertActionHidden('open_reservation')
            ->assertSee(__('sales.offer_next.accepted', ['amount' => '110,000 USD']))
            ->callAction('convert_to_reservation', ['valid_until' => now()->addDays(5)->toDateTimeString(), 'activate_now' => true])
            ->assertHasNoActionErrors()
            ->assertNotified(__('sales.reservation_from_offer.done'))
            ->assertRedirect();

        $reservation = UnitReservation::withoutGlobalScopes()->firstOrFail();
        $this->assertSame(ReservationStatus::Active, $reservation->status);

        Livewire::test(ViewCrmOffer::class, ['record' => $offer->getRouteKey()])
            ->assertActionHidden('convert_to_reservation')
            ->assertActionVisible('open_reservation')
            ->assertSee($reservation->reservation_number)
            ->assertSee(__('sales.offer_next.reserved', ['number' => $reservation->reservation_number]));
    }

    public function test_the_offer_page_hides_the_conversion_from_people_without_the_permission(): void
    {
        $company = $this->company();
        $seller = $this->actor($company);
        $offer = $this->acceptedOffer($seller, $this->lead($seller), $this->unit($company));
        $this->actingAs($this->actor($company, array_diff(self::BASE, ['convert_offer_to_reservation'])));

        Livewire::test(ViewCrmOffer::class, ['record' => $offer->getRouteKey()])->assertActionHidden('convert_to_reservation');
    }
}
