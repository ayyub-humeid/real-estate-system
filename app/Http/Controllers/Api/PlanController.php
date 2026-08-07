<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\PlanResource;
use App\Models\Plan;
use Illuminate\Http\Request;

class PlanController extends Controller
{
    public function index()
    {
        $plans = Plan::whereRaw('is_active = true')
            ->get();
        return PlanResource::collection($plans);
    }

    /**
     * Get the details of a specific plan.
     */
    public function show(Plan $plan)
    {
        // Ensure we only show active plans, optional, but good practice
        if (!$plan->is_active) {
            return response()->json(['message' => 'Plan not found.'], 404);
        }
        return new PlanResource($plan->loadCount('subscriptions'));
    }
}