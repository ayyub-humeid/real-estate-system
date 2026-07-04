<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PropertyResource extends JsonResource
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
            'name' => $this->name,
            'address' => $this->address,
            'description' => $this->description,
            'rent_price' => $this->whenHas('rent_price'),
            'main_image_url' => $this->whenLoaded('images', function () {
                $primaryImage = $this->images->where('is_primary', true)->first() ?? $this->images->first();
                return $primaryImage ? $primaryImage->url : null;
            }),
            'location' => new LocationResource($this->whenLoaded('location')),
            'images' => ImageResource::collection($this->whenLoaded('images')),
            'company' => new CompanyResource($this->whenLoaded('company')),
        ];
    }
}
