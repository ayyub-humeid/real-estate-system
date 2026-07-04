<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LocationResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->whenHas('name', $this->name),
            'type' => $this->whenHas('type', $this->type),
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'full_path' => $this->full_path, // From getFullPathAttribute()
        ];
    }
}
