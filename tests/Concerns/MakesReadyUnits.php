<?php

namespace Tests\Concerns;

use App\Models\{Location, Property, Unit};

/** Offers must name a unit that can be sold or rented, so offer tests need a ready unit to point at. */
trait MakesReadyUnits
{
    protected function readyUnitId(int $companyId): int
    {
        $location = Location::withoutGlobalScopes()->create(['company_id' => $companyId, 'name' => 'City '.uniqid(), 'type' => 'city']);
        $property = Property::withoutGlobalScopes()->create(['company_id' => $companyId, 'location_id' => $location->id, 'name' => 'Property '.uniqid(), 'address' => 'Address']);

        return Unit::withoutGlobalScopes()->create([
            'company_id' => $companyId, 'property_id' => $property->id, 'unit_number' => 'U-'.uniqid(),
            'type' => 'apartment', 'status' => 'ready',
        ])->id;
    }
}
