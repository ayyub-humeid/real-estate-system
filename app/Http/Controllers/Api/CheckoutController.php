<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\SubscriptionPayment;
use App\Models\User;
use App\Notifications\PaymentNotification;
use App\Services\StripePaymentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

class CheckoutController extends Controller
{
    protected StripePaymentService $stripeService;

    public function __construct(StripePaymentService $stripeService)
    {
        $this->stripeService = $stripeService;
    }

    public function createSession(Request $request)
    {

        $request->validate([
            'plan_id' => 'required|exists:plans,id',
        ]);


        $user = $request->user();

        $plan = Plan::findOrFail($request->plan_id);

        if (!$plan->is_active) {
            return response()->json([
                'success' => false,
                'message' => 'The selected plan is not active.'
            ], 400);
        }

        // Use company_id if the user belongs to a company, otherwise use user id.
        // The webhook will create/update the subscription based on client_reference_id.
        $referenceId = $user->company_id ? (string) $user->company_id : 'user_' . $user->id;

        try {
            $session = $this->stripeService->createSubscriptionCheckoutSession($plan, $referenceId, (string) $user->id);

            return response()->json([
                'success' => true,
                'url' => $session->url,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Stripe Session Creation Failed',
                'error' => $e->getMessage(),
            ], 500);
        }
    }


    public function createLeaseSession(Request $request)
    {
        $request->validate([
            'unit_id' => 'required|exists:units,id',
        ]);

        $user = $request->user();
        $unit = \App\Models\Unit::findOrFail($request->unit_id);

        try {
            $session = $this->stripeService->createLeaseCheckoutSession($unit, (string) $user->id);

            return response()->json([
                'success' => true,
                'url' => $session->url,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Stripe Session Creation Failed',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
    public function createPaymentSession(Request $request)
    {
        $request->validate([
            'payment_id' => 'required|exists:payments,id'
        ]);
        $user = $request->user();
        $payment = Payment::with([
            'lease' => function ($query) {
                $query->select('id', 'unit_id', 'start_date', 'end_date', 'status')
                    ->with(['unit:id,unit_number,property_id', 'unit.property:id,name']);
            }
        ])->findOrFail($request->payment_id);

        if (!$payment) {
            return response()->json([
                'success' => false,
                'message' => 'This Payment request is not exist'
            ]);
        }
        if ($payment->status == 'paid') {
            return response()->json([
                'success' => false,
                'message' => 'This Payment request is already paid'
            ]);
        }
        try {
            $session = $this->stripeService->createPaymentCheckoutSession($payment, (string) $user->id);

            return response()->json([
                'success' => true,
                'url' => $session->url,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Stripe Session Creation Failed',
                'error' => $e->getMessage(),
            ], 500);
        }

    }

    public function verifySession(Request $request)
    {
        $request->validate([
            'session_id' => 'required|string',
        ]);

        try {
            $session = $this->stripeService->retrieveSession($request->session_id);

            if ($session->payment_status === 'paid') {
                // Check if it's a lease session
                $type = $session->metadata->type ?? null;
                if ($type === 'lease') {
                    $unitId = $session->metadata->unit_id ?? null;
                    $userId = $session->metadata->user_id ?? null;

                    if ($unitId && $userId) {
                        $user = \App\Models\User::find($userId);
                        if ($user) {
                            $unit = \App\Models\Unit::with('property')->find($unitId);
                            $companyId = $unit && $unit->property ? $unit->property->company_id : null;

                            // Find or create Tenant record for this user and company
                            $tenant = \App\Models\Tenant::withoutGlobalScopes()->firstOrCreate(
                                [
                                    'user_id' => $user->id,
                                    'company_id' => $companyId
                                ],
                                [
                                    'status' => 'active',
                                ]
                            );

                            // Create the RentalRequest (طلب إيجار)
                            $rentalRequest = \App\Models\RentalRequest::create([
                                'tenant_id' => $tenant->id,
                                'unit_id' => $unitId,
                                'title' => 'Lease Request for Unit ' . ($unit ? $unit->unit_number : $unitId),
                                'description' => 'Auto-created request after successful rent payment via Stripe.',
                                'status' => 'pending',
                                'priority' => 'medium',
                                'max_budget' => $unit ? $unit->rent_price : 0,
                                'duration_months' => 12, // default 1 year
                                'desired_move_in' => now(),
                                'company_id' => $unit && $unit->property ? $unit->property->company_id : null,
                            ]);

                            return response()->json([
                                'success' => true,
                                'message' => 'Payment verified and lease request created successfully.',
                            ]);
                        }
                    }
                }

                $companyId = $session->client_reference_id;
                $planId = $session->metadata->plan_id ?? null;

                if ($companyId && $planId) {
                    $plan = Plan::find($planId);
                    if ($plan) {
                        $startsAt = now();
                        $endsAt = strtolower($plan->billing_cycle) === 'yearly'
                            ? now()->addYear()
                            : now()->addMonth();

                        $subscription = Subscription::updateOrCreate(
                            ['company_id' => $companyId],
                            [
                                'plan_id' => $plan->id,
                                'status' => 'active',
                                'starts_at' => $startsAt,
                                'ends_at' => $endsAt,
                                'trial_ends_at' => null,
                                'canceled_at' => null,
                            ]
                        );

                        SubscriptionPayment::create([
                            'subscription_id' => $subscription->id,
                            'amount' => $session->amount_total / 100,
                            'currency' => strtoupper($session->currency),
                            'payment_method' => 'stripe_card',
                            'status' => 'paid',
                            'transaction_reference' => $session->payment_intent ?? $session->id,
                            'paid_at' => now(),
                        ]);


                        return response()->json([
                            'success' => true,
                            'message' => 'Payment verified and subscription activated.',
                        ]);
                    }
                }
            }

            return response()->json([
                'success' => false,
                'message' => 'Payment not completed.',
            ], 400);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to verify session.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
    public function verifyPaymentSession(Request $request)
    {
        $request->validate([
            'session_id' => 'required|string',
        ]);

        try {
            $session = $this->stripeService->retrieveSession($request->session_id);

            $type = $session->metadata->type;

            if ($type === 'payment') {
                $payment = Payment::with('lease.unit.property')->findOrFail($session->metadata->payment_id);

                $paymentAmount = $session->amount_total / 100;
                $payment->update([
                    'status' => 'paid',
                    'payment_date' => now(),
                    'paid_amount' => $payment->paid_amount + $paymentAmount,
                    'payment_method' => 'credit_card',
                    'reference_number' => $session->payment_intent ?? $session->id,
                ]);

                // Notifications are handled automatically by the PaymentObserver
                // when the payment status is updated to 'paid'.

                return response()->json([
                    'success' => true,
                    'message' => 'Payment generated successfully.',
                ]);
            }

            return response()->json([
                'success' => false,
                'message' => 'Payment not completed.',
            ], 400);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to verify session.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
