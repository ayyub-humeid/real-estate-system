<?php

namespace Tests\Feature;

use App\Filament\Pages\HowSalesWork;
use App\Models\{Company, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class HowSalesWorkPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_user_with_crm_access_can_open_the_guide_page(): void
    {
        $company = Company::create(['name' => 'Guide Co', 'email' => 'guide@example.test']);
        $user = User::factory()->create(['company_id' => $company->id]);
        Permission::findOrCreate('view_any_lead', 'web');
        $user->givePermissionTo('view_any_lead');
        $this->actingAs($user);

        Livewire::test(HowSalesWork::class)->assertSuccessful();
    }

    public function test_a_user_without_any_crm_access_cannot_open_the_guide_page(): void
    {
        $company = Company::create(['name' => 'No Access Co', 'email' => 'noaccess@example.test']);
        $user = User::factory()->create(['company_id' => $company->id]);
        $this->actingAs($user);

        $this->assertFalse(HowSalesWork::canAccess());
    }
}
