<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\UnitResource;
use App\Models\Unit;
use Illuminate\Http\Request;

class UnitController extends Controller
{
    public function index(Request $request)
    {
        $filters = $request->only(['search', 'type', 'min_price', 'max_price', 'bedrooms', 'amenities']);
        $sort = $request->input('sort', 'newest_to_oldest');

        $query = Unit::query()
            ->operationallyReady()
            ->whereDoesntHave('currentLease')
            ->filter($filters)
            ->with([
                'property:id,name,address,description,location_id,company_id',
                'property.location',
                'primaryImage',
                'features',
                'currentLease',
            ])
            ->withAvg('ratings', 'rating')
            ->withCount('ratings');

        if ($sort === 'newest_to_oldest') {
            $query->latest();
        } elseif ($sort === 'oldest_to_newest') {
            $query->oldest();
        } elseif ($sort === 'top_rated') {
            $query->orderByDesc('ratings_avg_rating');
        }

        $units = $query->paginate(12);

        return UnitResource::collection($units);
    }

    public function featured()
    {
        $units = Unit::with(['property:id,name,address,description,location_id,company_id', 'property.location', 'property.company:id,name,email'])
            ->with(['primaryImage', 'currentLease'])
            ->operationallyReady()
            ->whereDoesntHave('currentLease')
            ->featured()
            ->latest()
            ->limit(5)
            ->get();


        return UnitResource::collection($units);

    }

    public function show(Unit $unit)
    {

        $unit->load([
            'property:id,name,address,description,location_id,company_id',
            'property.location',
            'property.company:id,name,email',
            'images',
            'features',
            'currentLease',
        ]);


        return new UnitResource($unit);
    }

    public function rate(Request $request, Unit $unit)
    {
        $validated = $request->validate([
            'rating' => 'required|integer|min:1|max:5',
            'review' => 'nullable|string'
        ]);

        $unit->ratings()->create([
            'rating' => $validated['rating'],
            'review' => $validated['review'],
        ]);

        return response()->json(['message' => 'Rating submitted successfully.'], 201);
    }
}
