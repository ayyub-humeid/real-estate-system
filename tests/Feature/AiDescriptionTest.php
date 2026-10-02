<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Location;
use App\Models\Property;
use App\Models\Unit;
use App\Models\UnitFeature;
use App\Models\User;
use App\Services\PropertyDescriptionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * AiDescriptionTest
 *
 * Feature test for the PropertyDescriptionService (AI integration).
 * All external HTTP calls are mocked via Http::fake() so no real API
 * key is required to run the tests.
 */
class AiDescriptionTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;
    private Property $property;
    private Unit $unit;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::create([
            'name'  => 'Demo Agency',
            'email' => 'demo-agency-' . uniqid() . '@test.com',
        ]);

        $user = User::factory()->create(['company_id' => $this->company->id]);
        $this->actingAs($user);

        $this->property = Property::create([
            'company_id' => $this->company->id,
            'location_id' => Location::create([
                'company_id' => $this->company->id,
                'name' => 'Ramallah',
                'type' => 'city',
            ])->id,
            'name'       => 'Sunset Towers',
            'address'    => 'Downtown, Ramallah',
        ]);

        $this->unit = Unit::create([
            'property_id' => $this->property->id,
            'company_id'  => $this->company->id,
            'unit_number' => 'B-12',
            'rent_price'  => 900,
            'status'      => 'available',
            'type'        => 'Apartment',
            'bedrooms'    => 3,
            'bathrooms'   => 2,
            'sqft'        => 140,
        ]);

        UnitFeature::create([
            'unit_id' => $this->unit->id,
            'name'    => 'Balcony',
            'value'   => 'true',
        ]);
        UnitFeature::create([
            'unit_id' => $this->unit->id,
            'name'    => 'Elevator',
            'value'   => 'true',
        ]);
    }

    #[Test]
    public function it_generates_an_ai_description_for_a_unit_and_saves_it(): void
    {
        // ── Arrange ──────────────────────────────────────────────────────────
        // Mock the outbound HTTP call to OpenRouter so the test runs offline.
        $fakeDescription = 'Welcome to Sunset Towers Unit B-12, a stunning 3-bedroom apartment '
            . 'in the heart of Downtown Ramallah. Enjoy breathtaking views from your private '
            . 'balcony, with easy elevator access. Available at $900/month. Contact us today!';

        Http::fake([
            'openrouter.ai/*' => Http::response([
                'choices' => [
                    [
                        'message' => [
                            'role'    => 'assistant',
                            'content' => $fakeDescription,
                        ],
                    ],
                ],
            ], 200),
        ]);

        // ── Act ───────────────────────────────────────────────────────────────
        /** @var PropertyDescriptionService $service */
        $service = app(PropertyDescriptionService::class);
        $generated = $service->generate($this->unit);
        $service->saveDescription($this->unit, $generated);

        // ── Assert ────────────────────────────────────────────────────────────
        // The returned string must equal our mocked response.
        $this->assertSame($fakeDescription, $generated);

        // The description must be persisted to the database.
        $this->assertDatabaseHas('units', [
            'id'          => $this->unit->id,
            'description' => $fakeDescription,
        ]);
    }

    #[Test]
    public function it_does_not_overwrite_existing_description_when_user_cancels(): void
    {
        // ── Arrange ──────────────────────────────────────────────────────────
        $originalDescription = 'This is the original, manually written description.';

        // Persist an existing description first
        $this->unit->update(['description' => $originalDescription]);

        // ── Act ───────────────────────────────────────────────────────────────
        // Simulate the user cancelling — saveDescription() is NEVER called.
        // The service generates a new one but we discard it (user hits "Cancel").
        Http::fake([
            'openrouter.ai/*' => Http::response([
                'choices' => [[
                    'message' => ['role' => 'assistant', 'content' => 'New AI description.'],
                ]],
            ], 200),
        ]);

        $service = app(PropertyDescriptionService::class);
        // generate() is called but we intentionally do NOT call saveDescription()
        $service->generate($this->unit);

        // ── Assert ────────────────────────────────────────────────────────────
        // The database must still hold the ORIGINAL description.
        $this->assertDatabaseHas('units', [
            'id'          => $this->unit->id,
            'description' => $originalDescription,
        ]);
    }
}
