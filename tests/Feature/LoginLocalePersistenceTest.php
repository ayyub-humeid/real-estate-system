<?php

namespace Tests\Feature;

use App\Filament\Pages\Auth\Login;
use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class LoginLocalePersistenceTest extends TestCase
{
    use RefreshDatabase;

    private function login(User $user, ?string $sessionLocale): void
    {
        $user->assignRole(Role::findOrCreate('company_admin', 'web'));

        if ($sessionLocale !== null) {
            session(['locale' => $sessionLocale]);
        }

        Livewire::test(Login::class)
            ->set('data.email', $user->email)
            ->set('data.password', 'password')
            ->call('authenticate');
    }

    public function test_login_saves_the_locale_chosen_on_the_login_page(): void
    {
        $company = Company::create(['name' => 'A', 'email' => 'a@test.com']);
        $user = User::factory()->create(['company_id' => $company->id, 'password' => bcrypt('password'), 'locale' => 'ar']);

        $this->login($user, 'en');

        $this->assertAuthenticatedAs($user);
        $this->assertSame('en', $user->fresh()->locale);
    }

    public function test_login_without_a_chosen_locale_keeps_the_saved_preference(): void
    {
        $company = Company::create(['name' => 'A', 'email' => 'a@test.com']);
        $user = User::factory()->create(['company_id' => $company->id, 'password' => bcrypt('password'), 'locale' => 'en']);

        $this->login($user, null);

        $this->assertSame('en', $user->fresh()->locale);
    }
}
