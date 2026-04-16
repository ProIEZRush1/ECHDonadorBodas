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
        $amount = $boletos * 3000 * 100; // $3,000 MXN per ticket, in centavos
        $ticketLabel = $boletos === 1 ? '1 Boleto de Rifa' : "{$boletos} Boletos de Rifa";

        try {
            $session = $this->stripe->checkout->sessions->create([
                'payment_method_types' => ['card'],
                'line_items' => [
                    [
                        'price_data' => [
                            'currency' => 'mxn',
                            'product_data' => [
                                'name' => $ticketLabel,
                                'description' => 'Rifa solidaria - Hajnasat Kala. Sorteo: 30 de enero 2027. Premio: $100,000 MXN.',
                            ],
                            'unit_amount' => 3000 * 100, // $3,000 MXN in centavos
                        ],
                        'quantity' => $boletos,
                    ],
                ],
                'mode' => 'payment',
                'success_url' => config('app.url') . '/gracias?session_id={CHECKOUT_SESSION_ID}',
                'cancel_url' => config('app.url') . '/cancelado',
                'metadata' => [
                    'contact_id' => (string) $contact->id,
                    'telefono' => $contact->telefono,
                    'boletos' => (string) $boletos,
                ],
                'customer_email' => $contact->email,
            ]);

            Log::info('Stripe checkout created', [
                'session_id' => $session->id,
                'contact_id' => $contact->id,
                'boletos' => $boletos,
                'amount' => $amount,
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
