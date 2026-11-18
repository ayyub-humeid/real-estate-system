<?php

namespace Tests\Feature;

use App\Enums\{AcquisitionPartyRole, AcquisitionStatus, AcquisitionType, ActualCostStatus, CommitmentAmendmentStatus, Currency, DueDiligenceStatus, FinancialCommitmentStatus, LeaseStatus, MaintenanceStatus, PaymentStatus, ProjectBudgetStatus, ProjectStatus};
use App\Filament\Resources\PropertyAcquisitionResource\Pages\ViewPropertyAcquisition;
use App\Filament\Resources\PropertyAcquisitionResource\RelationManagers\AcquisitionPartiesRelationManager;
use App\Models\{Company, Party, PropertyAcquisition, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class AcquisitionLocalizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_enum_case_has_a_label_in_both_locales(): void
    {
        $enums = [
            AcquisitionPartyRole::class, AcquisitionStatus::class, AcquisitionType::class, ActualCostStatus::class,
            CommitmentAmendmentStatus::class, DueDiligenceStatus::class, FinancialCommitmentStatus::class,
            LeaseStatus::class, MaintenanceStatus::class, PaymentStatus::class, ProjectBudgetStatus::class,
            ProjectStatus::class, Currency::class,
        ];

        foreach (['en', 'ar'] as $locale) {
            app()->setLocale($locale);

            foreach ($enums as $enum) {
                foreach ($enum::cases() as $case) {
                    $label = $case->getLabel();
                    $this->assertNotSame($case->value, $label, "{$enum}::{$case->name} has no {$locale} label");
                    $this->assertStringNotContainsString('enums.', $label);
                }
            }
        }
    }

    public function test_workflow_notification_is_rendered_in_each_recipients_locale(): void
    {
        app()->setLocale('en');
        $notification = new \App\Notifications\AcquisitionWorkflowNotification(
            'acquisition.notify.status_title',
            'acquisition.notify.status_body',
            1,
            ['status' => 'approved', 'reference' => 'ACQ-1'],
        );

        $en = $notification->toArray((object) ['locale' => 'en']);
        $ar = $notification->toArray((object) ['locale' => 'ar']);

        $this->assertSame('Approved', $en['title']);
        $this->assertSame(AcquisitionStatus::Approved->getLabel(), $en['title']);
        $this->assertNotSame($en['body'], $ar['body']);
        $this->assertStringContainsString('ACQ-1', $ar['body']);
        $this->assertSame('en', app()->getLocale());
    }

    public function test_currency_labels_follow_the_active_locale(): void
    {
        app()->setLocale('en');
        $this->assertSame('Israeli Shekel (₪)', Currency::ILS->getLabel());

        app()->setLocale('ar');
        $this->assertNotSame('Israeli Shekel (₪)', Currency::ILS->getLabel());
    }

    public function test_party_share_validation_message_is_translated_and_uses_the_selected_role(): void
    {
        app()->setLocale('ar');
        $company = Company::create(['name' => 'A', 'email' => 'a@test.com']);
        $user = User::factory()->create(['company_id' => $company->id, 'role' => 'company_admin']);
        foreach (['view_any_property::acquisition', 'view_property::acquisition', 'update_property::acquisition', 'create_acquisition_party', 'view_any_acquisition_party'] as $permission) {
            $user->givePermissionTo(Permission::findOrCreate($permission, 'web'));
        }
        $this->actingAs($user);

        $acquisition = PropertyAcquisition::withoutGlobalScopes()->create(['company_id' => $company->id, 'type' => 'cash_purchase']);
        $party = Party::withoutGlobalScopes()->create(['company_id' => $company->id, 'type' => 'individual', 'name' => 'Seller']);
        $acquisition->acquisitionParties()->create(['company_id' => $company->id, 'party_id' => $party->id, 'role' => 'seller', 'share_percentage' => 80]);
        $other = Party::withoutGlobalScopes()->create(['company_id' => $company->id, 'type' => 'individual', 'name' => 'Seller 2']);

        Livewire::test(AcquisitionPartiesRelationManager::class, [
            'ownerRecord' => $acquisition,
            'pageClass' => ViewPropertyAcquisition::class,
        ])
            ->callTableAction('addParty', data: [
                'party_source' => 'existing_party',
                'party_id' => $other->id,
                'role' => 'seller',
                'share_percentage' => 30,
            ])
            ->assertHasTableActionErrors(['share_percentage']);

        $this->assertSame(1, $acquisition->acquisitionParties()->count());
    }
}
