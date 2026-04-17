<?php

namespace App\Services;

use App\Models\Contact;
use Illuminate\Support\Facades\Log;

/**
 * Stripe payment service for raffle ticket purchases.
 */
class StripeService
{
    private \Stripe\StripeClient $stripe;

    public function __construct()
    {
        $this->stripe = new \Stripe\StripeClient(config('services.stripe.secret'));
    }

    /**
     * Create a Stripe Checkout session for raffle ticket purchase.
     *
     * @return string|null The checkout URL
     */
    public function createCheckoutSession(Contact $contact, int $boletos = 1): ?string
    {
        return $this->createCheckoutSessionCustom($contact, $boletos * 3000, $boletos === 1 ? '1 Boleto de Rifa' : "{$boletos} Boletos de Rifa");
    }

    /**
     * Create a Stripe Checkout session for any amount.
     *
     * @return string|null The checkout URL
     */
    public function createCheckoutSessionCustom(Contact $contact, int $amountMxn, string $label = 'Donativo'): ?string
    {
        try {
            $session = $this->stripe->checkout->sessions->create([
                'payment_method_types' => ['card'],
                'line_items' => [
                    [
                        'price_data' => [
                            'currency' => 'mxn',
                            'product_data' => [
                                'name' => $label,
                                'description' => 'Rifa solidaria - Hajnasat Kala. Apoyo para boda.',
                            ],
                            'unit_amount' => $amountMxn * 100,
                        ],
                        'quantity' => 1,
                    ],
                ],
                'mode' => 'payment',
                'success_url' => config('app.url') . '/gracias?session_id={CHECKOUT_SESSION_ID}',
                'cancel_url' => config('app.url') . '/cancelado',
                'metadata' => [
                    'contact_id' => (string) $contact->id,
                    'telefono' => $contact->telefono,
                    'boletos' => (string) ($amountMxn >= 3000 ? (int) floor($amountMxn / 3000) : 0),
                    'amount_mxn' => (string) $amountMxn,
                ],
            ]);

            Log::info('Stripe checkout created', [
                'session_id' => $session->id,
                'contact_id' => $contact->id,
                'amount_mxn' => $amountMxn,
                'label' => $label,
            ]);

            return $session->url;
        } catch (\Throwable $e) {
            Log::error('Stripe checkout creation failed', [
                'error' => $e->getMessage(),
                'contact_id' => $contact->id,
            ]);
            return null;
        }
    }
}
