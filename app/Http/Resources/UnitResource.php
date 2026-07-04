<?php

namespace App\Http\Resources;

use App\Filament\Resources\UnitFeatureResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UnitResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return  [
            'id'            => $this->id,
            'unit_number'   => $this->unit_number,
              'image_url'     => $this->whenLoaded('images', function() {
            $primaryImage = $this->images->first();
            return $primaryImage ? $primaryImage->url : null;
        }),
            'rent_price'    => $this->rent_price,
            'status'        => $this->status,
            'bedrooms'      => $this->bedrooms,
            'bathrooms'     => $this->bathrooms,
            'sqft'          => $this->sqft,
            'is_featured' => $this->is_featured,
            'status_color' => config('units.status_colors')[$this->status] ?? 'bg-gray-500',
            'property' =>$this->whenLoaded('property'),
            'features' =>$this->whenLoaded('features'),

        ];
    }
}