<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\RentalRequestResource;
use App\Models\RentalRequest;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class TenantRentalRequestController extends Controller
{
    /**
     * Display a listing of rental requests for the authenticated tenant.
     */
    public function index(Request $request): AnonymousResourceCollection|JsonResponse
    {
        $user = $request->user();

        if (!$user?->isTenant()) {
            return response()->json([
                'message' => 'User is not a tenant.',
            ], 403);
        }

        $tenantIds = $this->getTenantIds($user);

        $rentalRequests = RentalRequest::withoutGlobalScopes()
            ->whereIn('tenant_id', $tenantIds)
            ->select([
                'id',
                'tenant_id',
                'unit_id',
                'company_id',
                'title',
                'description',
                'status',
                'priority',
                'preferred_type',
                'max_budget',
                'desired_move_in',
                'duration_months',
                'admin_notes',
                'reviewed_at',
                'reviewed_by',
                'created_at',
                'updated_at',
            ])
            ->with([
                'unit:id,unit_number,property_id',
                'unit.property:id,name',
                'tenant:id,user_id,status',
                'tenant.user:id,name,email',
            ])
            ->orderByDesc('created_at')
            ->paginate($request->input('per_page', 8));

        return RentalRequestResource::collection($rentalRequests);
    }

    /**
     * Display the specified rental request.
     */
    public function show(Request $request, $id): RentalRequestResource|JsonResponse
    {
        $user = $request->user();

        if (!$user?->isTenant()) {
            return response()->json([
                'message' => 'User is not a tenant.',
            ], 403);
        }

        $tenantIds = $this->getTenantIds($user);

        $rentalRequest = RentalRequest::withoutGlobalScopes()
            ->whereIn('tenant_id', $tenantIds)
            ->select([
                'id',
                'tenant_id',
                'unit_id',
                'company_id',
                'title',
                'description',
                'status',
                'priority',
                'preferred_type',
                'max_budget',
                'desired_move_in',
                'duration_months',
                'admin_notes',
                'reviewed_at',
                'reviewed_by',
                'created_at',
                'updated_at',
            ])
            ->with([
                'unit:id,unit_number,property_id',
                'unit.property:id,name',
                'tenant:id,user_id,status',
                'tenant.user:id,name,email',
            ])
            ->find($id);

        if (!$rentalRequest) {
            return response()->json([
                'message' => 'Rental request not found or does not belong to the tenant.',
            ], 404);
        }

        return new RentalRequestResource($rentalRequest);
    }

    /**
     * Remove the specified rental request from storage.
     */
    public function destroy(Request $request, $id): JsonResponse
    {
        $user = $request->user();

        if (!$user?->isTenant()) {
            return response()->json([
                'message' => 'User is not a tenant.',
            ], 403);
        }

        $tenantIds = $this->getTenantIds($user);

        $rentalRequest = RentalRequest::withoutGlobalScopes()
            ->whereIn('tenant_id', $tenantIds)
            ->find($id);

        if (!$rentalRequest) {
            return response()->json([
                'message' => 'Rental request not found or does not belong to the tenant.',
            ], 404);
        }

        $rentalRequest->delete();

        return response()->json(null, 204);
    }

    private function getTenantIds(User $user)
    {
        return Tenant::withoutGlobalScopes()
            ->where('user_id', $user->id)
            ->pluck('id');
    }
}