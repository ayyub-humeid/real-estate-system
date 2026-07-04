<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\PropertyResource;
use App\Models\Property;
use Illuminate\Http\Request;

class PropertyController extends Controller
{
    /**
     * Display a listing of properties.
     */
    public function index()
    {
        $properties = Property::with(['location', 'images', 'company'])
            ->latest()
            ->paginate(10);

        return PropertyResource::collection($properties);
    }

    /**
     * Display the specified property.
     */
    public function show(Property $property)
    {
        $property->load(['location', 'images', 'units', 'company']);
        
        return new PropertyResource($property);
    }
}
