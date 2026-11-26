<?php

namespace Tests\Feature;

use App\Enums\ExpenseCategory;
use App\Filament\Resources\ExpenseResource\Pages\CreateExpense;
use App\Models\{Company, Employee, Expense, Location, Plan, Property, Subscription, Unit, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/** The create screen as a company admin uses it: the company is never picked on the form. */
class ExpenseCompanyAdminTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;

    private Property $property;

    private Unit $unit;

    private Employee $employee;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::create(['name' => 'Admin Co', 'email' => 'admin-co@example.test', 'is_active' => true]);
        $location = Location::withoutGlobalScopes()->create(['company_id' => $this->company->id, 'name' => 'City', 'type' => 'city']);
        $this->property = Property::withoutGlobalScopes()->create(['company_id' => $this->company->id, 'location_id' => $location->id, 'name' => 'Tower', 'address' => 'Street']);
        $this->unit = Unit::withoutGlobalScopes()->create(['company_id' => $this->company->id, 'property_id' => $this->property->id, 'unit_number' => 'A-1', 'type' => 'apartment', 'status' => 'ready']);
        $this->employee = Employee::withoutGlobalScopes()->create([
            'company_id' => $this->company->id, 'salary' => 1500,
            'user_id' => User::factory()->create(['company_id' => $this->company->id])->id,
        ]);

        Subscription::create([
            'company_id' => $this->company->id,
            'plan_id' => Plan::create([
                'name' => 'Plan '.str()->uuid(), 'slug' => 'p-'.str()->uuid(), 'price' => 1, 'billing_cycle' => 'monthly', 'is_active' => true,
                'features' => ['expense_tracking' => true],
            ])->id,
            'status' => 'active', 'starts_at' => now()->subDay(), 'ends_at' => now()->addMonth(),
        ]);

        $admin = User::factory()->create(['company_id' => $this->company->id]);
        foreach (['view_any_expense', 'view_expense', 'create_expense', 'update_expense'] as $permission) {
            $admin->givePermissionTo(Permission::findOrCreate($permission, 'web'));
        }
        $this->actingAs($admin);
    }

    public function test_a_company_admin_records_an_expense_for_a_property_and_unit(): void
    {
        Livewire::test(CreateExpense::class)
            ->fillForm(['category' => ExpenseCategory::Utilities->value, 'title' => 'Water', 'amount' => 50, 'property_id' => $this->property->id, 'unit_id' => $this->unit->id])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('expenses', ['title' => 'Water', 'company_id' => $this->company->id, 'unit_id' => $this->unit->id]);
    }

    public function test_a_company_admin_records_a_salary_and_is_told_why_a_second_one_is_refused(): void
    {
        $salary = ['category' => ExpenseCategory::Salaries->value, 'title' => 'Salary', 'employee_id' => $this->employee->id, 'amount' => 1500, 'salary_month' => now()->toDateString()];

        Livewire::test(CreateExpense::class)->fillForm($salary)->call('create')->assertHasNoFormErrors();

        Livewire::test(CreateExpense::class)
            ->fillForm($salary)
            ->call('create')
            ->assertHasFormErrors(['salary_month'])
            ->assertNotified(__('expense_domain.errors.save_failed'));

        $this->assertSame(1, Expense::count());
    }
}
