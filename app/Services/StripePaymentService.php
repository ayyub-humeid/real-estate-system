<?php

namespace App\Services;

use App\Models\Plan;
use Stripe\StripeClient;
use Stripe\Webhook;
use Stripe\Exception\SignatureVerificationException;

class StripePaymentService
{
    protected StripeClient $stripe;

    public function __construct()
    {
        $this->stripe = new StripeClient(config('services.stripe.secret'));
    }

    public function createCheckoutSession(Plan $plan, string $companyId, string $userId)
    {
        // Retrieve the hardcoded price ID from config if it exists
        $priceId = config("services.stripe.prices.{$plan->slug}");

        if ($priceId) {
            // Using predefined Stripe Product/Price
            $lineItems = [
                [
                    'price' => $priceId,
                    'quantity' => 1,
                ]
            ];
            $mode = 'subscription'; // Using Stripe Billing for recurring payment
        } else {
            // Fallback for dynamic pricing if no ID matched
            $lineItems = [
                [
                    'price_data' => [
                        'currency' => 'usd',
                        'product_data' => [
                            'name' => $plan->name,
                            'description' => $plan->description ?? 'Subscription plan: ' . $plan->name,
                        ],
                        'unit_amount' => (int) ($plan->price * 100),
                    ],
                    'quantity' => 1,
                ]
            ];
            $mode = 'payment'; // One-time checkout
        }

        return $this->stripe->checkout->sessions->create([
            'payment_method_types' => ['card'],
            'line_items' => $lineItems,
            'mode' => $mode,
            'success_url' => rtrim(env('FRONTEND_URL', 'http://localhost:3000'), '/') . '/checkout/success?session_id={CHECKOUT_SESSION_ID}',
            'cancel_url' => rtrim(env('FRONTEND_URL', 'http://localhost:3000'), '/') . '/checkout/cancel?plan=' . $plan->slug,
            'client_reference_id' => $companyId, // optional to get more secure.
            'metadata' => [
                'plan_id' => $plan->id,
                'user_id' => $userId,
            ],
        ]);
    }

    public function verifyWebhook(string $payload, string $signature)
    {
        $webhookSecret = config('services.stripe.webhook_secret');
        return Webhook::constructEvent($payload, $signature, $webhookSecret);
    }

    public function retrieveSession(string $sessionId)
    {
        return $this->stripe->checkout->sessions->retrieve($sessionId);
    }
}
