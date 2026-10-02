<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Lease;
use App\Models\Location;
use App\Models\Payment;
use App\Models\Property;
use App\Models\Tenant;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Carbon\Carbon;

class LeaseBalanceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->company = Company::create([
            'name' => 'Test Company',
            'email' => 'lease-test-' . uniqid() . '@test.com',
        ]);
        $this->user = User::factory()->create(['company_id' => $this->company->id]);
        $this->actingAs($this->user);

        $this->property = Property::create([
            'company_id' => $this->company->id,
            'location_id' => Location::create([
                'company_id' => $this->company->id,
                'name' => 'Test City',
                'type' => 'city',
            ])->id,
            'name' => 'Test Property',
            'address' => '123 Test St',
        ]);

        $this->unit = Unit::create([
            'property_id' => $this->property->id,
            'rent_price' => 1000,
            'unit_number' => 'A1',
            'status' => 'available',
        ]);

        $this->tenant = Tenant::create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
        ]);
    }

    /** @test */
    public function it_calculates_outstanding_balance_correctly_including_only_due_payments()
    {
        // 1. Create a lease for 12 months
        $lease = Lease::create([
            'company_id' => $this->company->id,
            'unit_id' => $this->unit->id,
            'tenant_id' => $this->tenant->id,
            'start_date' => Carbon::today(),
            'end_date' => Carbon::today()->addYear(),
            'rent_amount' => 1000,
            'payment_frequency' => 'monthly',
            'payment_day' => 1,
            'status' => 'active',
        ]);

        // 2. Generate schedule (12 payments)
        $lease->generatePaymentSchedule();

        // 3. Verify outstanding balance only counts due payments
        // Since today is one of the due dates (if today is the 1st, or close), 
        // there should be exactly one or zero due today depending on logic.
        
        $dueTodayCount = Payment::where('lease_id', $lease->id)
            ->where('due_date', '<=', Carbon::today())
            ->count();

        $expectedOutstanding = $dueTodayCount * 1000;
        
        $lease->refresh();
        $this->assertEquals($expectedOutstanding, (float) $lease->outstanding_balance);
        
        // 4. Verify total remaining balance counts everything
        $this->assertEquals(12000, (float) $lease->remaining_balance);
    }

    /** @test */
    public function it_updates_outstanding_balance_when_payment_is_recorded()
    {
        $lease = Lease::create([
            'company_id' => $this->company->id,
            'unit_id' => $this->unit->id,
            'tenant_id' => $this->tenant->id,
            'start_date' => Carbon::today(),
            'rent_amount' => 1000,
            'payment_day' => Carbon::today()->day,
            'payment_frequency' => 'monthly',
            'status' => 'active',
        ]);

        $lease->generatePaymentSchedule();
        
        // Get the payment due today
        $payment = Payment::where('lease_id', $lease->id)
            ->where('due_date', '<=', Carbon::today())
            ->first();

        // Record partial payment
        $payment->recordPayment(600, 'cash');

        $lease->refresh();
        
        // Remaining for this payment should be 400
        $this->assertEquals(400, (float) $payment->remaining_amount);
        
        // Lease outstanding balance should be 400 (if only 1 is due)
        $this->assertEquals(400, (float) $lease->outstanding_balance);
        
        // Total remaining for 12 months should be (12000 - 600) = 11400
        $this->assertEquals(11400, (float) $lease->remaining_balance);
    }
}
