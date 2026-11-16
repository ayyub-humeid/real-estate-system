<?php

namespace Tests\Feature;

use App\Enums\CrmInquirySource;
use App\Models\{Company, Lead, Party, User};
use App\Services\{LeadIntakeService, LeadPartyConversionService};
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class LeadPartyConversionTest extends TestCase
{
    use RefreshDatabase;

    private function company(string $name): Company
    {
        return Company::create(['name' => $name, 'email' => strtolower($name) . '@example.test']);
    }
    private function user(Company $company, array $permissions): User
    {
        $user = User::factory()->create(['company_id' => $company->id]);
        foreach ($permissions as $permission) $user->givePermissionTo(Permission::findOrCreate($permission, 'web'));
        return $user;
    }
    private function permissions(): array
    {
        return ['view_any_lead', 'view_lead', 'create_lead', 'convert_lead_to_party'];
    }
    private function intake(User $actor, array $data = []): Lead
    {
        return app(LeadIntakeService::class)->createLead($actor, array_merge(['name' => 'Ahmad', 'phone' => '0591234567', 'source' => CrmInquirySource::Manual->value], $data));
    }

    public function test_convert_lead_creates_party_and_links_lead_id(): void
    {
        $company = $this->company('A');
        $actor = $this->user($company, $this->permissions());
        $lead = $this->intake($actor, ['phone' => '0591234567', 'email' => 'ahmad@example.test']);

        $party = app(LeadPartyConversionService::class)->convert($actor, $lead);

        $this->assertSame($company->id, $party->company_id);
        $this->assertSame($lead->id, $party->lead_id);
        $this->assertSame($lead->name, $party->name);
        $this->assertSame($lead->phone, $party->phone);
        $this->assertSame($lead->email, $party->email);
        $this->assertTrue($party->is_active);
        $this->assertTrue($lead->fresh()->party->exists);
    }

    public function test_converting_an_already_converted_lead_is_rejected(): void
    {
        $company = $this->company('A');
        $actor = $this->user($company, $this->permissions());
        $lead = $this->intake($actor);
        app(LeadPartyConversionService::class)->convert($actor, $lead);

        $this->expectException(ValidationException::class);
        app(LeadPartyConversionService::class)->convert($actor, $lead->fresh());
    }

    public function test_cross_company_lead_conversion_is_rejected(): void
    {
        $a = $this->company('A');
        $b = $this->company('B');
        $actor = $this->user($a, $this->permissions());
        $foreignUser = $this->user($b, ['create_lead']);
        $foreignLead = app(LeadIntakeService::class)->createLead($foreignUser, ['name' => 'Foreign', 'phone' => '0599999999']);
        $this->expectException(AuthorizationException::class);
        app(LeadPartyConversionService::class)->convert($actor, $foreignLead);
    }

    public function test_user_without_convert_permission_cannot_convert(): void
    {
        $company = $this->company('A');
        $actor = $this->user($company, ['create_lead', 'view_lead']);
        $lead = $this->intake($actor);
        $this->expectException(AuthorizationException::class);
        app(LeadPartyConversionService::class)->convert($actor, $lead);
    }

    public function test_party_can_be_created_from_lead_with_no_email(): void
    {
        $company = $this->company('A');
        $actor = $this->user($company, $this->permissions());
        $lead = $this->intake($actor, ['email' => null]);
        $party = app(LeadPartyConversionService::class)->convert($actor, $lead);
        $this->assertNull($party->email);
        $this->assertSame($lead->id, $party->lead_id);
    }
}
