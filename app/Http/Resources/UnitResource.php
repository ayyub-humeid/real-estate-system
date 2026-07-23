<?php

namespace App\Http\Resources;

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
        return [
            'id' => $this->id,
            'unit_number' => $this->unit_number,
            'main_image_url' => $this->relationLoaded('primaryImage')
                ? ($this->primaryImage ? $this->primaryImage->url : null)
                : $this->whenLoaded('images', function () {
                    $primaryImage = $this->images->where('is_primary', true)->first() ?? $this->images->first();
                    return $primaryImage ? $primaryImage->url : null;
                }),
            'rent_price' => $this->rent_price,
            'description' => $this->description ?? "",
            'status' => $this->status,
            'type' => $this->type,
            'bedrooms' => $this->bedrooms,
            'bathrooms' => $this->bathrooms,
            'average_rating' => $this->when(isset($this->ratings_avg_rating), round($this->ratings_avg_rating, 1)),
            'reviews_count' => $this->when(isset($this->ratings_count), $this->ratings_count),
            'features' => $this->whenLoaded('features', function () {
                return $this->features->map(function ($feature) {
                    return [
                        'id' => $feature->id,
                        'name' => $feature->name,
                        'value' => $feature->value,
                    ];
                });
            }),
            'sqft' => $this->sqft,
            'is_featured' => $this->is_featured,
            'status_color' => config('units.status_colors')[$this->status] ?? 'bg-gray-500',
            'property' => new PropertyResource($this->whenLoaded('property')),
            // 'features' => $this->whenLoaded('features'),
            'images' => ImageResource::collection($this->whenLoaded('images')),
        ];
    }
}