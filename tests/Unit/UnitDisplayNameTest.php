<?php

namespace Tests\Unit;

use App\Models\Property;
use App\Models\Unit;
use Tests\TestCase;

class UnitDisplayNameTest extends TestCase
{
    public function test_display_name_combines_number_place_and_type(): void
    {
        $unit = new Unit(['unit_number' => 'A-304', 'type' => 'apartment']);
        $unit->setRelation('property', new Property(['name' => 'Marina Tower']));

        $this->assertStringStartsWith('A-304 · Marina Tower', $unit->displayName());
    }

    public function test_display_name_falls_back_to_the_number_alone(): void
    {
        $unit = new Unit(['unit_number' => 'A-304']);
        $unit->setRelation('property', null);
        $unit->setRelation('project', null);

        $this->assertSame('A-304', $unit->displayName());
    }
}
