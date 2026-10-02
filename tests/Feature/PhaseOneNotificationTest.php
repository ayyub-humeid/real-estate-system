<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Location;
use App\Models\Party;
use App\Models\Property;
use App\Models\User;
use App\Notifications\AcquisitionWorkflowNotification;
use App\Services\PropertyAcquisitionService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class PhaseOneNotificationTest extends TestCase
{
    private function user(Company $company, array $permissions): User
    {
        $user = User::factory()->create(['company_id' => $company->id]);
        foreach ($permissions as $permission) {
            $user->givePermissionTo(Permission::findOrCreate($permission, 'web'));
        }
        return $user;
    }

    private function property(Company $company, string $suffix): Property
    {
        $location = Location::withoutGlobalScopes()->create(['company_id' => $company->id, 'name' => "Location {$suffix}", 'type' => 'city']);
        return Property::withoutGlobalScopes()->create(['company_id' => $company->id, 'location_id' => $location->id, 'name' => "Property {$suffix}", 'address' => 'Address']);
    }

    public function test_ownership_notification_is_sent_after_commit_to_the_actor_and_relevant_same_company_user_only(): void
    {
        $suffix = uniqid('notification_', true);
        $company = Company::create(['name' => "Company {$suffix}", 'email' => "{$suffix}@test.com"]);
        $otherCompany = Company::create(['name' => "Other {$suffix}", 'email' => "other_{$suffix}@test.com"]);
        $actor = $this->user($company, ['change_property_ownership']);
        $ownershipViewer = $this->user($company, ['view_property_ownership']);
        $unrelatedUser = $this->user($company, []);
        $otherCompanyViewer = $this->user($otherCompany, ['view_property_ownership']);
        $property = $this->property($company, $suffix);
        $party = Party::withoutGlobalScopes()->create(['company_id' => $company->id, 'type' => 'individual', 'name' => 'Owner']);

        Notification::fake();
        app(PropertyAcquisitionService::class)->replaceOwnership($actor, $property, [['party' => $party, 'percentage' => 100]], '2026-01-01');

        Notification::assertSentTo($actor, AcquisitionWorkflowNotification::class);
        Notification::assertSentTo($ownershipViewer, AcquisitionWorkflowNotification::class);
        Notification::assertNotSentTo($unrelatedUser, AcquisitionWorkflowNotification::class);
        Notification::assertNotSentTo($otherCompanyViewer, AcquisitionWorkflowNotification::class);
    }

    public function test_ownership_notification_is_not_sent_when_the_outer_transaction_rolls_back(): void
    {
        $suffix = uniqid('rollback_', true);
        $company = Company::create(['name' => "Company {$suffix}", 'email' => "{$suffix}@test.com"]);
        $actor = $this->user($company, ['change_property_ownership']);
        $property = $this->property($company, $suffix);
        $party = Party::withoutGlobalScopes()->create(['company_id' => $company->id, 'type' => 'individual', 'name' => 'Owner']);

        Notification::fake();
        try {
            DB::transaction(function () use ($actor, $property, $party): void {
                app(PropertyAcquisitionService::class)->replaceOwnership($actor, $property, [['party' => $party, 'percentage' => 100]], '2026-01-01');
                throw new \RuntimeException('Force rollback');
            });
        } catch (\RuntimeException) {
            $this->assertSame(0, $property->ownerships()->count());
        }

        Notification::assertNothingSent();
    }
}
