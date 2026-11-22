<?php

namespace Tests\Feature;

use App\Enums\{CrmInquirySource, OfferProposedBySide, OpportunityType, ReservationSource};
use App\Models\{Company, Location, Party, Property, Unit, UnitReservation, User};
use App\Notifications\WorkflowNotification;
use App\Services\{LeadIntakeService, OfferNegotiationService, OpportunityService, ReservationWorkflowService, SalesNotifier};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/** Sales events reach the right person, in that person's own language, and only once. */
class WorkflowNotificationsTest extends TestCase
{
    use RefreshDatabase;

    private function company(string $name): Company
    {
        return Company::create(['name' => $name, 'email' => strtolower($name).uniqid().'@example.test']);
    }

    private function user(Company $company, array $permissions, string $locale = 'en'): User
    {
        $user = User::factory()->create(['company_id' => $company->id, 'locale' => $locale]);
        foreach ($permissions as $permission) {
            $user->givePermissionTo(Permission::findOrCreate($permission, 'web'));
        }

        return $user;
    }

    private function unit(Company $company): Unit
    {
        $location = Location::withoutGlobalScopes()->create(['company_id' => $company->id, 'name' => 'City '.$company->id, 'type' => 'city']);
        $property = Property::withoutGlobalScopes()->create(['company_id' => $company->id, 'location_id' => $location->id, 'name' => 'Property '.$company->id, 'address' => 'Address']);

        return Unit::withoutGlobalScopes()->create(['company_id' => $company->id, 'property_id' => $property->id, 'unit_number' => 'U-'.uniqid(), 'type' => 'apartment', 'status' => 'ready']);
    }

    private function customer(Company $company): Party
    {
        return Party::withoutGlobalScopes()->create(['company_id' => $company->id, 'type' => 'individual', 'name' => 'Customer '.$company->id, 'is_active' => true]);
    }

    private function draft(User $actor, Company $company): UnitReservation
    {
        return app(ReservationWorkflowService::class)->createDraft($actor, [
            'unit_id' => $this->unit($company)->id, 'customer_party_id' => $this->customer($company)->id,
            'source' => ReservationSource::Direct->value, 'transaction_type' => OpportunityType::Sale->value,
            'agreed_amount' => 100000, 'currency' => 'usd', 'down_payment_amount' => 10000,
        ]);
    }

    private function sellerPermissions(): array
    {
        return ['create_unit::reservation', 'view_any_unit::reservation', 'view_unit::reservation', 'update_unit::reservation', 'send_reservation_for_approval',
            'cancel_unit_reservation', 'create_lead', 'create_opportunity', 'create_crm::offer', 'create_offer_revision', 'finalize_offer_revision', 'request_offer_approval', 'assign_lead', 'assign_opportunity'];
    }

    private function inbox(User $user, ?string $event = null): \Illuminate\Support\Collection
    {
        return $user->notifications()->where('type', WorkflowNotification::class)->get()
            ->filter(fn ($n) => $event === null || ($n->data['event'] ?? null) === 'notifications.'.$event)->values();
    }

    public function test_a_reservation_sent_for_approval_tells_only_approvers_of_the_same_company(): void
    {
        $company = $this->company('A');
        $seller = $this->user($company, $this->sellerPermissions());
        $approver = $this->user($company, ['approve_and_activate_reservation'], 'ar');
        $bystander = $this->user($company, ['view_any_unit::reservation']);
        $foreignApprover = $this->user($this->company('B'), ['approve_and_activate_reservation']);

        $reservation = app(ReservationWorkflowService::class)->sendForApproval($seller, $this->draft($seller, $company));

        $this->assertCount(1, $this->inbox($approver, 'reservation_approval_requested'));
        $this->assertCount(0, $this->inbox($seller), 'The person who acted is not told about their own action.');
        $this->assertCount(0, $this->inbox($bystander));
        $this->assertCount(0, $this->inbox($foreignApprover), 'A notification never crosses companies.');

        $data = $this->inbox($approver)->first()->data;
        $this->assertSame(__('notifications.reservation_approval_requested_title', [], 'ar'), $data['title']);
        $this->assertStringContainsString($reservation->reservation_number, $data['body']);
        $this->assertStringContainsString($seller->name, $data['body']);
        $this->assertNotEmpty($data['actions'][0]['url'] ?? null, 'The notification links to the reservation.');
    }

    public function test_the_decision_on_a_reservation_goes_back_to_whoever_sent_it(): void
    {
        $company = $this->company('A');
        $seller = $this->user($company, $this->sellerPermissions());
        $approver = $this->user($company, ['approve_and_activate_reservation', 'reject_reservation_request']);
        $service = app(ReservationWorkflowService::class);

        $approved = $service->sendForApproval($seller, $this->draft($seller, $company));
        $service->approveAndActivate($approver, $approved);
        $rejected = $service->sendForApproval($seller, $this->draft($seller, $company));
        $service->rejectRequest($approver, $rejected, 'Customer withdrew');

        $this->assertCount(1, $this->inbox($seller, 'reservation_activated'));
        $this->assertCount(1, $this->inbox($seller, 'reservation_rejected'));
        $this->assertStringContainsString('Customer withdrew', $this->inbox($seller, 'reservation_rejected')->first()->data['body']);
        $this->assertCount(0, $this->inbox($approver, 'reservation_activated'), 'The approver made the decision, so is not told about it.');
    }

    public function test_nothing_is_sent_when_the_action_is_rolled_back(): void
    {
        $company = $this->company('A');
        $seller = $this->user($company, $this->sellerPermissions());
        $approver = $this->user($company, ['approve_and_activate_reservation']);
        $draft = $this->draft($seller, $company);

        try {
            DB::transaction(function () use ($seller, $draft): void {
                app(ReservationWorkflowService::class)->sendForApproval($seller, $draft);
                throw new \RuntimeException('later step failed');
            });
        } catch (\RuntimeException) {
        }

        $this->assertCount(0, $this->inbox($approver));
    }

    public function test_active_reservations_get_one_warning_before_they_expire_and_one_notice_when_they_do(): void
    {
        $company = $this->company('A');
        $seller = $this->user($company, $this->sellerPermissions());
        $approver = $this->user($company, ['approve_and_activate_reservation']);
        $service = app(ReservationWorkflowService::class);
        $active = $service->approveAndActivate($approver, $service->sendForApproval($seller, $this->draft($seller, $company)));
        $active->forceFill(['valid_until' => now()->addDay()])->save();

        $notifier = app(SalesNotifier::class);
        $this->assertSame(1, $notifier->notifyExpiringSoon());
        $this->assertSame(0, $notifier->notifyExpiringSoon(), 'The warning is sent once per end date.');
        $this->assertCount(1, $this->inbox($seller, 'reservation_expiring'));

        $active->forceFill(['valid_until' => now()->addHours(5)])->save();
        $this->assertSame(1, $notifier->notifyExpiringSoon(), 'Extending to a new date re-arms the warning.');

        $active->forceFill(['valid_until' => now()->subMinute()])->save();
        $this->assertSame(1, $service->expireDueReservations());
        $this->assertCount(1, $this->inbox($seller, 'reservation_expired'));
    }

    public function test_offer_price_approval_goes_to_approvers_and_the_decision_back_to_the_requester(): void
    {
        $company = $this->company('A');
        $seller = $this->user($company, $this->sellerPermissions());
        $manager = $this->user($company, ['approve_offer_revision', 'reject_offer_revision'], 'ar');
        $lead = app(LeadIntakeService::class)->createLead($seller, ['name' => 'Nour', 'phone' => '0591234567', 'source' => CrmInquirySource::Manual->value]);
        $opportunity = app(OpportunityService::class)->create($seller, ['lead_id' => $lead->id, 'type' => OpportunityType::Sale->value, 'source' => CrmInquirySource::Manual->value]);
        $offers = app(OfferNegotiationService::class);
        $offer = $offers->createOffer($seller, ['opportunity_id' => $opportunity->id, 'transaction_type' => 'sale', 'unit_id' => $this->unit($company)->id]);
        $revision = $offers->submitRevision($seller, $offers->createDraftRevision($seller, $offer, ['proposed_by_side' => OfferProposedBySide::Company->value, 'amount' => 95000, 'currency' => 'USD']));

        $approval = $offers->requestApproval($seller, $revision);

        $this->assertCount(1, $this->inbox($manager, 'offer_approval_requested'));
        $this->assertStringContainsString('95,000.00', $this->inbox($manager)->first()->data['body']);
        $this->assertCount(0, $this->inbox($seller));

        $offers->decideApproval($manager, $approval, false, 'Too low');
        $this->assertCount(1, $this->inbox($seller, 'offer_approval_rejected'));
        $this->assertCount(0, $this->inbox($manager, 'offer_approval_rejected'));
    }

    public function test_assigning_a_lead_tells_the_new_owner_only_when_the_owner_changes(): void
    {
        $company = $this->company('A');
        $manager = $this->user($company, $this->sellerPermissions());
        $owner = $this->user($company, ['view_any_lead'], 'ar');
        $lead = app(LeadIntakeService::class)->createLead($manager, ['name' => 'Nour', 'phone' => '0591234567', 'source' => CrmInquirySource::Manual->value, 'assigned_to' => $manager->id]);

        app(LeadIntakeService::class)->assign($manager, $lead, $owner->id);
        app(LeadIntakeService::class)->assign($manager, $lead->fresh(), $owner->id);

        $this->assertCount(1, $this->inbox($owner, 'lead_assigned'));
        $this->assertSame(__('notifications.lead_assigned_title', [], 'ar'), $this->inbox($owner)->first()->data['title']);
        $this->assertStringContainsString('Nour', $this->inbox($owner)->first()->data['body']);
    }

    public function test_one_notification_reads_in_each_recipients_language(): void
    {
        $notification = new WorkflowNotification('notifications.budget_approved', ['version' => 3]);

        $en = $notification->toArray((object) ['locale' => 'en']);
        $ar = $notification->toArray((object) ['locale' => 'ar']);

        $this->assertSame('Budget approved', $en['title']);
        $this->assertSame('Budget V3 is now the current baseline.', $en['body']);
        $this->assertSame('تم اعتماد الميزانية', $ar['title']);
        $this->assertStringContainsString('V3', $ar['body']);
    }

    public function test_statuses_and_roles_are_shown_as_words_in_the_recipients_language(): void
    {
        $status = (new WorkflowNotification('project.notifications.status_changed', ['project' => 'Tower', 'status' => 'in_progress']))->toArray((object) ['locale' => 'ar']);
        $this->assertStringContainsString('Tower', $status['body']);
        $this->assertStringNotContainsString('in_progress', $status['body']);

        $role = (new WorkflowNotification('project.notifications.membership_assigned', ['project' => 'Tower', 'role' => 'Project Manager']))->toArray((object) ['locale' => 'en']);
        $this->assertStringNotContainsString('project.member_roles', $role['body']);
    }

    public function test_every_notification_event_has_a_title_and_body_in_both_languages(): void
    {
        $missing = [];
        foreach (['en', 'ar'] as $locale) {
            foreach (array_keys(trans('notifications', [], $locale)) as $key) {
                if (str_ends_with($key, '_title') && ! \Illuminate\Support\Facades\Lang::has('notifications.'.substr($key, 0, -6).'_body', $locale, false)) {
                    $missing[] = "$locale:$key";
                }
            }
        }
        $this->assertSame([], $missing);
    }

    public function test_services_never_store_finished_english_text_in_a_notification(): void
    {
        $offenders = [];
        foreach (glob(app_path('Services/*.php')) as $file) {
            $source = file_get_contents($file);
            if (preg_match('/new ProjectWorkflowNotification\(\s*[\'"]/', $source)) {
                $offenders[] = basename($file);
            }
            if (preg_match('/->notify\(\$actor,[^,]+,\s*[\'"][A-Z]/', $source)) {
                $offenders[] = basename($file);
            }
        }
        $this->assertSame([], array_values(array_unique($offenders)), 'Notifications must store a translation key, not a finished sentence.');
    }
}
