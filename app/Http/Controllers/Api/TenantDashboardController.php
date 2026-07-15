<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Models\Lease;
use App\Models\RentalRequest;
use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class TenantDashboardController extends Controller
{
    /**
     * جلب إحصائيات وبيانات لوحة تحكم المستأجر الحالي.
     */
    public function index(Request $request)
    {
        $user = $request->user();

        // العثور على بروفايل المستأجر
        $tenant = Tenant::withoutGlobalScopes()
            ->where('user_id', $user->id)
            ->first();

        if (!$tenant) {
            return response()->json([
                'success' => false,
                'message' => 'Tenant profile not found for this user.'
            ], 404);
        }

        // 1. العقود والوحدات المستأجرة
        $leases = Lease::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->with(['unit.property.company', 'property'])
            ->get();

        // 2. طلبات الإيجار المقدمة
        $rentalRequests = RentalRequest::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->with(['unit.property'])
            ->latest()
            ->get();

        // 3. جدول المدفوعات (المستحقة والمدفوعة) عبر العقود
        $payments = Payment::withoutGlobalScopes()
            ->whereIn('lease_id', $leases->pluck('id'))
            ->with(['lease.unit.property'])
            ->orderBy('due_date', 'asc')
            ->get();

        // 4. تحديد الشركة العقارية التي تدير عقاره الحالي (المدرجة في الـ Sidebar)
        $currentLease = $leases->where('status', 'active')->first() ?? $leases->first();
        $managingCompany = null;
        if ($currentLease && $currentLease->unit && $currentLease->unit->property && $currentLease->unit->property->company) {
            $company = $currentLease->unit->property->company;
            $managingCompany = [
                'id'   => $company->id,
                'name' => $company->name,
                'logo' => $company->logo ? asset('storage/' . $company->logo) : null,
            ];
        }

        // 5. حساب الدفعة القادمة المستحقة (Next Payment Due)
        $nextPayment = $payments->where('status', 'pending')->first();
        $nextPaymentDue = null;
        if ($nextPayment) {
            $nextPaymentDue = [
                'amount'     => (float) $nextPayment->amount,
                'due_date'   => $nextPayment->due_date->format('Y-m-d'),
                'unit_name'  => ($nextPayment->lease && $nextPayment->lease->unit)
                    ? ($nextPayment->lease->unit->property->name . ' - Unit ' . $nextPayment->lease->unit->unit_number)
                    : 'Leased Unit',
                'id'         => $nextPayment->id
            ];
        }

        // تجهيز مصفوفة العقود للفرونتند
        $formattedLeases = $leases->map(function ($l) {
            return [
                'id'                => $l->id,
                'unit_id'           => $l->unit_id,
                'start_date'        => $l->start_date ? $l->start_date->format('Y-m-d') : null,
                'end_date'          => $l->end_date ? $l->end_date->format('Y-m-d') : null,
                'rent_amount'       => (float) $l->rent_amount,
                'status'            => $l->status,
                'property_name'     => $l->property ? $l->property->name : 'N/A',
                'property_address'  => $l->property ? $l->property->address : 'N/A',
                'unit_number'       => $l->unit ? $l->unit->unit_number : 'N/A',
            ];
        });

        // تجهيز مصفوفة طلبات الإيجار للفرونتند
        $formattedRequests = $rentalRequests->map(function ($r) {
            return [
                'id'              => $r->id,
                'title'           => $r->title ?? 'Rental Inquiry',
                'description'     => $r->description,
                'status'          => $r->status,
                'priority'        => $r->priority,
                'max_budget'      => (float) $r->max_budget,
                'desired_move_in' => $r->desired_move_in ? $r->desired_move_in->format('Y-m-d') : null,
                'unit_name'       => $r->unit
                    ? ($r->unit->property->name . ' - Unit ' . $r->unit->unit_number)
                    : 'N/A',
            ];
        });

        // تجهيز مصفوفة المدفوعات للفرونتند
        $formattedPayments = $payments->map(function ($p) {
            return [
                'id'               => $p->id,
                'amount'           => (float) $p->amount,
                'paid_amount'      => (float) $p->paid_amount,
                'remaining_amount' => (float) $p->remaining_amount,
                'due_date'         => $p->due_date ? $p->due_date->format('Y-m-d') : null,
                'payment_date'     => $p->payment_date ? $p->payment_date->format('Y-m-d') : null,
                'status'           => $p->status,
                'type'             => $p->type,
                'unit_name'        => ($p->lease && $p->lease->unit)
                    ? ($p->lease->unit->property->name . ' - Unit ' . $p->lease->unit->unit_number)
                    : 'N/A',
            ];
        });

        return response()->json([
            'success' => true,
            'data'    => [
                'tenant'           => [
                    'id'   => $tenant->id,
                    'status' => $tenant->status,
                ],
                'managing_company' => $managingCompany,
                'next_payment_due' => $nextPaymentDue,
                'leases'           => $formattedLeases,
                'rental_requests'  => $formattedRequests,
                'payments'         => $formattedPayments,
            ]
        ]);
    }
}
