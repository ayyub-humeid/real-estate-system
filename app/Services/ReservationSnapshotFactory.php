<?php

namespace App\Services;

use App\Models\{Unit, UnitReservation};

class ReservationSnapshotFactory
{
    public function make(UnitReservation $reservation, Unit $unit): array
    {
        $unit->loadMissing(['property', 'project', 'plannedUnit.floor.building', 'features']);
        $floor = $unit->plannedUnit?->floor;
        $building = $floor?->building;

        return [
            'unit_data' => [
                'unit_id' => $unit->id, 'unit_number' => $unit->unit_number, 'type' => $unit->type,
                'actual_area' => $unit->actual_area, 'area_unit' => $unit->area_unit,
                'bedrooms' => $unit->bedrooms, 'bathrooms' => $unit->bathrooms,
                'property_name' => $unit->property?->name, 'project_id' => $unit->project_id,
                'project_name' => $unit->project?->name, 'building_name' => $building?->name,
                'floor_label' => $floor?->label ?? $floor?->floor_number,
                'features' => $unit->features->map(fn ($feature) => ['key' => $feature->feature_key, 'name' => $feature->name, 'value' => $feature->value])->values()->all(),
            ],
            'commercial_terms' => [
                'transaction_type' => $reservation->transaction_type->value, 'agreed_amount' => $reservation->agreed_amount,
                'currency' => $reservation->currency, 'down_payment_amount' => $reservation->down_payment_amount,
                'payment_method' => $reservation->payment_method, 'payment_terms' => $reservation->payment_terms,
                'rent_frequency' => $reservation->rent_frequency?->value, 'lease_term_value' => $reservation->lease_term_value,
                'lease_term_unit' => $reservation->lease_term_unit, 'deposit_amount' => $reservation->deposit_amount,
                'additional_terms' => $reservation->terms_json, 'valid_until' => $reservation->valid_until?->toISOString(),
            ],
            'source_data' => [
                'source' => $reservation->source->value, 'opportunity_id' => $reservation->opportunity_id,
                'offer_id' => $reservation->offer_id, 'offer_reference' => $reservation->offer?->reference_number,
                'offer_revision_id' => $reservation->offer_revision_id, 'offer_revision_number' => $reservation->offerRevision?->revision_number,
            ],
        ];
    }
}
