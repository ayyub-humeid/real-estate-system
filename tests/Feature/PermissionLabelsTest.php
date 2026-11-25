<?php

namespace Tests\Feature;

use App\Filament\Resources\RoleResource;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * An Arabic admin ticking permissions on the Roles screen must never meet an English
 * sentence or a raw translation key, and every permission must sit in a named group
 * rather than the "other" pile.
 */
class PermissionLabelsTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_custom_permission_has_an_arabic_label_and_a_group(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        app()->setLocale('ar');

        $options = RoleResource::getCustomPermissionOptions();
        [, $other] = (new \ReflectionMethod(RoleResource::class, 'groupCustomPermissionOptions'))->invoke(null, $options);

        // Page and widget permissions are labelled by their own tabs, not the custom list.
        $custom = collect($options)->reject(fn ($label, $name) => str_starts_with($name, 'page_') || str_starts_with($name, 'widget_'));

        $untranslated = $custom->reject(fn (string $label): bool => (bool) preg_match('/\p{Arabic}/u', $label))->keys()->all();
        $this->assertSame([], $untranslated, 'These custom permissions still read in English or show a raw key: '.implode(', ', $untranslated));

        $raw = $custom->filter(fn (string $label): bool => str_contains($label, 'permissions.'))->keys()->all();
        $this->assertSame([], $raw, 'These custom permissions show a missing translation key: '.implode(', ', $raw));

        $ungrouped = collect($other)->keys()->reject(fn ($name) => str_starts_with($name, 'page_') || str_starts_with($name, 'widget_'))->all();
        $this->assertSame([], $ungrouped, 'These custom permissions are not in any Roles-screen group: '.implode(', ', $ungrouped));
    }
}
