<?php

namespace Tests\Feature;

use App\Enums\{ExpenseCategory, ExpenseStatus, MaintenanceStatus, UnitStatus};
use App\Filament\Resources\ExpenseResource\Pages\{CreateExpense, EditExpense, ListExpenses, ViewExpense};
use App\Filament\Resources\MaintenanceRequestResource\Pages\CreateMaintenanceRequest;
use App\Models\{Company, Employee, Expense, Location, MaintenanceRequest, Property, Unit, User};
use App\Services\ExpenseService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class MaintenanceAndExpenseTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;

    private User $admin;

    private Property $property;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::create(['name' => 'Ops Co', 'email' => 'ops@example.test']);
        $this->admin = User::factory()->create(['company_id' => null]);
        $this->admin->assignRole(Role::findOrCreate('super_admin', 'web'));
        foreach ([
            'view_any_expense', 'view_expense', 'create_expense', 'update_expense', 'pay_expense', 'cancel_expense',
            'view_any_maintenance::request', 'create_maintenance::request',
        ] as $permission) {
            $this->admin->givePermissionTo(\Spatie\Permission\Models\Permission::findOrCreate($permission, 'web'));
        }
        $this->actingAs($this->admin);

        $location = Location::withoutGlobalScopes()->create(['company_id' => $this->company->id, 'name' => 'City', 'type' => 'city']);
        $this->property = Property::withoutGlobalScopes()->create(['company_id' => $this->company->id, 'location_id' => $location->id, 'name' => 'Tower', 'address' => 'Street']);
    }

    private function unit(UnitStatus $status = UnitStatus::Ready): Unit
    {
        return Unit::withoutGlobalScopes()->create([
            'company_id' => $this->company->id, 'property_id' => $this->property->id,
            'unit_number' => 'U-'.str()->random(5), 'type' => 'apartment', 'status' => $status->value,
        ]);
    }

    private function request(Unit $unit): MaintenanceRequest
    {
        return MaintenanceRequest::create([
            'company_id' => $this->company->id, 'unit_id' => $unit->id, 'reported_by_id' => $this->admin->id,
            'title' => 'Leak', 'description' => 'Kitchen', 'status' => MaintenanceStatus::New->value, 'priority' => 'low',
        ]);
    }

    private function expense(array $attributes = []): Expense
    {
        return Expense::create($attributes + [
            'company_id' => $this->company->id, 'title' => 'Bill', 'category' => ExpenseCategory::Utilities->value,
            'amount' => 100, 'currency' => 'USD', 'status' => ExpenseStatus::Pending->value, 'expense_date' => now()->toDateString(),
        ]);
    }

    public function test_a_ready_unit_is_held_until_the_last_open_request_is_closed(): void
    {
        $unit = $this->unit();
        $first = $this->request($unit);
        $second = $this->request($unit);

        $this->assertSame(UnitStatus::Maintenance->value, $unit->fresh()->status);

        $first->update(['status' => MaintenanceStatus::Resolved->value]);
        $this->assertNotNull($first->fresh()->completed_at);
        $this->assertSame(UnitStatus::Maintenance->value, $unit->fresh()->status);

        $second->update(['status' => MaintenanceStatus::Cancelled->value]);
        $this->assertSame(UnitStatus::Ready->value, $unit->fresh()->status);
    }

    public function test_occupied_and_manually_blocked_units_are_left_alone(): void
    {
        $rented = $this->unit(UnitStatus::Rented);
        $this->request($rented);
        $this->assertSame(UnitStatus::Rented->value, $rented->fresh()->status);

        $manual = $this->unit(UnitStatus::Maintenance);
        $this->request($manual)->update(['status' => MaintenanceStatus::Resolved->value]);
        $this->assertSame(UnitStatus::Maintenance->value, $manual->fresh()->status);
    }

    public function test_the_create_page_holds_the_unit(): void
    {
        $unit = $this->unit();

        Livewire::test(CreateMaintenanceRequest::class)
            ->fillForm(['company_id' => $this->company->id, 'unit_id' => $unit->id, 'title' => 'AC', 'description' => 'Broken'])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertSame(UnitStatus::Maintenance->value, $unit->fresh()->status);
    }

    public function test_expense_screens_create_pay_and_cancel(): void
    {
        Livewire::test(CreateExpense::class)
            ->fillForm(['company_id' => $this->company->id, 'category' => ExpenseCategory::Rent->value, 'title' => 'Office rent', 'amount' => 900])
            ->call('create')
            ->assertHasNoFormErrors();

        $expense = Expense::firstOrFail();

        Livewire::test(ListExpenses::class)
            ->assertSuccessful()
            ->assertCanSeeTableRecords([$expense])
            ->callTableAction('markPaid', $expense, ['payment_method' => 'cash', 'paid_at' => now()->toDateString()])
            ->assertHasNoTableActionErrors();

        $this->assertSame(ExpenseStatus::Paid->value, $expense->fresh()->status);

        Livewire::test(EditExpense::class, ['record' => $expense->id])
            ->fillForm(['notes' => 'Receipt kept'])
            ->call('save')
            ->assertHasNoFormErrors();

        Livewire::test(ViewExpense::class, ['record' => $expense->id])
            ->callAction('cancelExpense', ['reason' => 'Recorded twice'])
            ->assertHasNoActionErrors();

        $this->assertSame(ExpenseStatus::Cancelled->value, $expense->fresh()->status);
        $this->assertNotEmpty($expense->fresh()->deletionBlockers());
    }

    public function test_paid_expenses_are_locked(): void
    {
        $expense = $this->expense();
        app(ExpenseService::class)->markPaid($this->admin, $expense, ['payment_method' => 'cash', 'paid_at' => now()->toDateString()]);

        $this->expectException(ValidationException::class);
        $expense->fresh()->update(['amount' => 5]);
    }

    public function test_a_salary_cannot_be_recorded_twice_for_one_month(): void
    {
        $employee = Employee::withoutGlobalScopes()->create([
            'company_id' => $this->company->id, 'salary' => 1000,
            'user_id' => User::factory()->create(['company_id' => $this->company->id])->id,
        ]);
        $salary = ['category' => ExpenseCategory::Salaries->value, 'employee_id' => $employee->id, 'salary_month' => now()->toDateString()];
        $this->expense($salary);

        $this->expectException(ValidationException::class);
        $this->expense($salary);
    }

    public function test_every_new_enum_case_is_translated_in_both_languages(): void
    {
        foreach (['en', 'ar'] as $locale) {
            app()->setLocale($locale);
            foreach ([\App\Enums\MaintenancePriority::class, ExpenseCategory::class, ExpenseStatus::class, \App\Enums\PaymentMethod::class] as $enum) {
                foreach ($enum::cases() as $case) {
                    $this->assertNotSame($case->value, $case->getLabel(), "$enum {$case->value} ($locale)");
                }
            }
        }
    }
}
