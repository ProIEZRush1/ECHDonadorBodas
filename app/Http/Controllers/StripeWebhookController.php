<?php

namespace App\Http\Controllers;

use App\Models\Contact;
use App\Models\ConversationState;
use App\Models\Donation;
use App\Models\Message;
use App\Services\WhatsAppService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Handles Stripe webhook events for payment confirmation.
 */
class StripeWebhookController extends Controller
{
    public function handle(Request $request, WhatsAppService $whatsApp): JsonResponse
    {
        $payload = $request->getContent();
        $sigHeader = $request->header('Stripe-Signature');
        $webhookSecret = config('services.stripe.webhook_secret');

        try {
            $event = \Stripe\Webhook::constructEvent($payload, $sigHeader, $webhookSecret);
        } catch (\Stripe\Exception\SignatureVerificationException $e) {
            Log::warning('Stripe webhook signature verification failed', ['error' => $e->getMessage()]);
            return response()->json(['error' => 'Invalid signature'], 400);
        } catch (\Throwable $e) {
            Log::error('Stripe webhook error', ['error' => $e->getMessage()]);
            return response()->json(['error' => 'Webhook error'], 400);
        }

        Log::info('Stripe webhook received', ['type' => $event->type, 'id' => $event->id]);

        if ($event->type === 'checkout.session.completed') {
            $this->handleCheckoutCompleted($event->data->object, $whatsApp);
        }

        return response()->json(['status' => 'ok']);
    }

    /**
     * Handle successful checkout - mark contact as donor.
     */
    private function handleCheckoutCompleted(object $session, WhatsAppService $whatsApp): void
    {
        $contactId = $session->metadata->contact_id ?? null;
        $boletos = (int) ($session->metadata->boletos ?? 1);
        $telefono = $session->metadata->telefono ?? null;
        $amountTotal = ($session->amount_total ?? 0) / 100; // Convert from centavos

        if (!$contactId) {
            Log::warning('Stripe checkout completed without contact_id', ['session_id' => $session->id]);
            return;
        }

        $contact = Contact::find($contactId);
        if (!$contact) {
            Log::warning('Contact not found for Stripe checkout', ['contact_id' => $contactId]);
            return;
        }

        // Create donation record
        Donation::create([
            'contact_id' => $contact->id,
            'amount' => $amountTotal,
            'boletos' => $boletos,
            'reference' => $session->payment_intent ?? $session->id,
            'receipt_analysis' => ['source' => 'stripe', 'session_id' => $session->id],
            'status' => 'verified',
            'confidence' => 'high',
            'verified_at' => now(),
        ]);

        // Update contact
        $contact->update([
            'status' => 'donador',
            'boletos' => $contact->boletos + $boletos,
        ]);

        // Update conversation state
        $state = ConversationState::where('contact_id', $contact->id)->first();
        if ($state) {
            $state->update(['current_step' => 'confirmado']);
        }

        // Send confirmation via WhatsApp
        $ticketText = $boletos === 1 ? '1 boleto' : "{$boletos} boletos";
        $message = "\xC2\xA1Pago recibido! \xE2\x9C\x85\n\n"
            . "Tienes {$ticketText} para la rifa del 30 de enero de 2027.\n\n"
            . "Que Hashem te bendiga por esta hermosa mitzv\xC3\xA1 de Hajnasat Kal\xC3\xA1. \xF0\x9F\x92\x8D\xF0\x9F\x95\x8E\n\n"
            . "\xC2\xA1Mucha suerte en el sorteo!";

        $result = $whatsApp->sendMessage($contact->telefono, $message);

        Message::create([
            'contact_id' => $contact->id,
            'direction' => 'out',
            'content' => $message,
            'wa_message_id' => $result['messages'][0]['id'] ?? null,
            'status' => $result ? 'sent' : 'failed',
        ]);

        Log::info('Stripe payment confirmed', [
            'contact_id' => $contact->id,
            'boletos' => $boletos,
            'amount' => $amountTotal,
        ]);
    }
}
