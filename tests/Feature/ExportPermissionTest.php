<?php

namespace Tests\Feature;

use App\Filament\Exports\{ContractExporter, ExpenseExporter, LeadExporter, PaymentExporter, ProjectExporter, UnitExporter};
use App\Filament\Resources\ContractResource\Pages\ListContracts;
use App\Filament\Resources\ExpenseResource\Pages\ListExpenses;
use App\Filament\Resources\LeadResource\Pages\ListLeads;
use App\Filament\Resources\PaymentResource\Pages\ListPayments;
use App\Filament\Resources\ProjectResource\Pages\ListProjects;
use App\Filament\Resources\RoleResource;
use App\Filament\Resources\UnitResource\Pages\ListUnits;
use App\Models\{Company, Plan, Role as AppRole, Subscription, User};
use Database\Seeders\RolesAndPermissionsSeeder;
use Filament\Actions\Exports\Models\Export;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Livewire\Livewire;
use Spatie\Permission\Models\{Permission, Role};
use Tests\TestCase;

/**
 * Downloading company data is its own right. Seeing a list must not be enough to take
 * a copy of it away, so each export button needs its own permission, and that
 * permission must be handed out, labelled and grouped like every other one.
 */
class ExportPermissionTest extends TestCase
{
    use RefreshDatabase;

    private const EXPORT_PERMISSIONS = [
        'export_contract', 'export_unit', 'export_lead', 'export_project', 'export_expense', 'export_payment',
    ];

    /** @return array<string, array{0: class-string, 1: string, 2: string, 3: class-string}> */
    public static function companyLists(): array
    {
        return [
            'contracts' => [ListContracts::class, 'view_any_contract', 'export_contract', ContractExporter::class],
            'units' => [ListUnits::class, 'view_any_unit', 'export_unit', UnitExporter::class],
            'leads' => [ListLeads::class, 'view_any_lead', 'export_lead', LeadExporter::class],
            'projects' => [ListProjects::class, 'view_any_project', 'export_project', ProjectExporter::class],
            'expenses' => [ListExpenses::class, 'view_any_expense', 'export_expense', ExpenseExporter::class],
            'payments' => [ListPayments::class, 'view_any_payment', 'export_payment', PaymentExporter::class],
        ];
    }

    public function test_the_seeder_creates_every_export_permission_and_hands_them_to_the_right_roles(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        foreach (self::EXPORT_PERMISSIONS as $name) {
            $this->assertTrue(Permission::where('name', $name)->where('guard_name', 'web')->exists(), "$name must be seeded");
        }

        $roles = fn (string $name): array => AppRole::platform()->where('name', $name)->firstOrFail()
            ->permissions->pluck('name')->all();

        $this->assertEqualsCanonicalizing(self::EXPORT_PERMISSIONS, array_intersect(self::EXPORT_PERMISSIONS, $roles('company_admin')));
        $this->assertEqualsCanonicalizing(
            ['export_contract', 'export_expense', 'export_payment'],
            array_values(array_intersect(self::EXPORT_PERMISSIONS, $roles('financial_manager'))),
        );
        $this->assertSame(['export_unit'], array_values(array_intersect(self::EXPORT_PERMISSIONS, $roles('property_manager'))));
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('companyLists')]
    public function test_a_user_who_can_view_the_list_but_not_export_gets_no_export_button(string $page, string $viewPermission, string $exportPermission, string $exporter): void
    {
        Bus::fake();
        $user = $this->userWith($viewPermission);
        $this->actingAs($user);

        $component = Livewire::test($page)->assertSuccessful();

        $this->assertTrue($component->instance()->getTable()->getAction('export')->isHidden(), 'The export button must be hidden without the permission.');

        // The test helper refuses hidden actions, so send the raw Livewire calls a forged request would use.
        $component->call('mountTableAction', 'export')->call('callMountedTableAction');

        $this->assertSame(0, Export::count(), 'Calling a hidden export action must not start an export.');
        $this->assertFalse($exporter::canExport());
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('companyLists')]
    public function test_granting_the_export_permission_shows_the_button_and_starts_the_export(string $page, string $viewPermission, string $exportPermission, string $exporter): void
    {
        Bus::fake();
        $user = $this->userWith($viewPermission, $exportPermission);
        $this->actingAs($user);

        Livewire::test($page)
            ->assertTableActionVisible('export')
            ->callTableAction('export')
            ->assertHasNoTableActionErrors();

        $this->assertDatabaseHas('exports', ['exporter' => $exporter, 'user_id' => $user->id]);
        $this->assertTrue($exporter::canExport());
    }

    public function test_one_export_permission_does_not_unlock_another_list(): void
    {
        $this->actingAs($this->userWith('export_payment'));

        $this->assertTrue(PaymentExporter::canExport());
        $this->assertFalse(ContractExporter::canExport());
        $this->assertFalse(UnitExporter::canExport());
    }

    public function test_super_admin_may_export_without_holding_the_permission_explicitly(): void
    {
        $admin = User::factory()->create(['company_id' => null]);
        $admin->assignRole(Role::findOrCreate('super_admin', 'web'));
        $this->actingAs($admin);

        foreach ([ContractExporter::class, UnitExporter::class, LeadExporter::class, ProjectExporter::class, ExpenseExporter::class, PaymentExporter::class] as $exporter) {
            $this->assertTrue($exporter::canExport(), "$exporter should open for super admin");
        }
    }

    public function test_guests_cannot_export(): void
    {
        $this->assertFalse(ContractExporter::canExport());
    }

    public function test_the_roles_screen_groups_and_labels_the_export_permissions_in_both_languages(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $group = new \ReflectionMethod(RoleResource::class, 'groupCustomPermissionOptions');

        foreach (['en', 'ar'] as $locale) {
            app()->setLocale($locale);
            $options = RoleResource::getCustomPermissionOptions();
            [$domains, $other] = $group->invoke(null, $options);

            $this->assertArrayHasKey('Data Export', $domains);
            $this->assertEqualsCanonicalizing(self::EXPORT_PERMISSIONS, array_keys($domains['Data Export']['Export company data']));
            $this->assertEmpty(array_intersect(self::EXPORT_PERMISSIONS, array_keys($other)), 'Export permissions must not land in "other".');

            foreach (self::EXPORT_PERMISSIONS as $name) {
                $label = $options[$name];
                $this->assertNotSame(str($name)->headline()->toString(), $label, "$name has no $locale label");
                $this->assertStringNotContainsString('permissions.', $label);
                if ($locale === 'ar') {
                    $this->assertMatchesRegularExpression('/\p{Arabic}/u', $label, "$name label is not Arabic");
                }
            }

            $this->assertNotSame('permissions.groups.data_export', __('permissions.groups.data_export'));
            $this->assertNotSame('permissions.groups.export_company_data', __('permissions.groups.export_company_data'));
        }
    }

    private function userWith(string ...$permissions): User
    {
        $company = Company::create(['name' => 'Perm Co '.str()->random(4), 'email' => str()->uuid().'@x.test', 'is_active' => true]);
        Subscription::create([
            'company_id' => $company->id,
            'plan_id' => Plan::create([
                'name' => 'Starter '.str()->uuid(), 'slug' => 's-'.str()->uuid(), 'price' => 99, 'billing_cycle' => 'monthly', 'is_active' => true,
                'features' => ['maintenance_tracking' => true, 'rental_requests' => true, 'expense_tracking' => true, 'max_units' => 50, 'max_projects' => 2],
            ])->id,
            'status' => 'active', 'starts_at' => now()->subDay(), 'ends_at' => now()->addMonth(),
        ]);
        $user = User::factory()->create(['company_id' => $company->id]);

        foreach ($permissions as $permission) {
            $user->givePermissionTo(Permission::findOrCreate($permission, 'web'));
        }

        return $user;
    }
}
