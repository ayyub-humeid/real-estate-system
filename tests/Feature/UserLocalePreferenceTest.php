<?php

namespace Tests\Feature;

use App\Filament\Pages\LanguagePreferences;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class UserLocalePreferenceTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_authenticated_user_can_persist_their_language_preference(): void
    {
        $user = User::factory()->create(['locale' => 'en']);

        Livewire::actingAs($user)
            ->test(LanguagePreferences::class)
            ->set('locale', 'ar')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame('ar', $user->fresh()->locale);
    }
}
