<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RentalRequestResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'description' => $this->description,
            'status' => $this->status,
            'priority' => $this->priority,
            'preferred_type' => $this->preferred_type,
            'max_budget' => (float) $this->max_budget,
            'desired_move_in' => $this->desired_move_in?->format('Y-m-d'),
            'duration_months' => (int) $this->duration_months,
            'admin_notes' => $this->admin_notes,
            'reviewed_at' => $this->reviewed_at?->format('Y-m-d H:i:s'),
            'reviewed_by' => $this->reviewed_by,
            'tenant' => $this->whenLoaded('tenant', function () {
                return [
                    'id' => $this->tenant->id,
                    'status' => $this->tenant->status,
                    'user' => $this->tenant->relationLoaded('user') && $this->tenant->user ? [
                        'id' => $this->tenant->user->id,
                        'name' => $this->tenant->user->name,
                        'email' => $this->tenant->user->email,
                    ] : null,
                ];
            }),
            'unit' => $this->whenLoaded('unit', function () {
                return [
                    'id' => $this->unit->id,
                    'unit_number' => $this->unit->unit_number,
                    'property_id' => $this->unit->property_id,
                    'property_name' => $this->unit->relationLoaded('property') && $this->unit->property ? $this->unit->property->name : null,
                ];
            }),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
