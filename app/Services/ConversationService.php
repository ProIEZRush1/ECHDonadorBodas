<?php

namespace App\Services;

use App\Models\Contact;
use App\Models\ConversationState;
use App\Models\Donation;
use App\Models\Message;
use App\Models\Setting;
use Illuminate\Support\Facades\Log;

/**
 * Conversation orchestrator for the wedding raffle chatbot.
 *
 * Coordinates WhatsApp messaging, AI processing, and donation tracking.
 */
class ConversationService
{
    public function __construct(
        private WhatsAppService $whatsApp,
        private AnthropicService $anthropic,
    ) {}

    /**
     * Handle an incoming text message.
     */
    public function handleIncomingMessage(
        string $from,
        string $text,
        string $waMessageId,
        ?string $senderName = null,
    ): void {
        // 1. Mark as read
        $this->whatsApp->markAsRead($waMessageId);

        // 2. Find or create contact
        $contact = Contact::firstOrCreate(
            ['telefono' => $from],
            [
                'wa_id' => $from,
                'nombre' => $senderName,
                'pais' => Contact::detectCountry($from),
                'status' => 'nuevo',
            ],
        );

        $contactUpdates = ['ultimo_contacto' => now(), 'wa_id' => $from];
        if (!$contact->pais) {
            $contactUpdates['pais'] = Contact::detectCountry($from);
        }
        if ($senderName && !$contact->nombre) {
            $contactUpdates['nombre'] = $senderName;
        }
        $contact->update($contactUpdates);

        // 3. Log incoming message
        Message::create([
            'contact_id' => $contact->id,
            'direction' => 'in',
            'content' => $text,
            'wa_message_id' => $waMessageId,
            'status' => 'delivered',
        ]);

        // 4. Get or create conversation state
        $state = $this->getOrCreateState($contact);

        // If already confirmed donor, keep in confirmado
        if ($contact->status === 'donador' && $state->current_step !== 'confirmado') {
            $state->update(['current_step' => 'confirmado']);
        }

        // 4b. Handle payment method choice directly (bypass AI for reliability)
        //     Also intercept "tarjeta"/"transferencia" from ANY step (user might say it anytime)
        if ($state->current_step === 'eligiendo_pago' || $this->isPaymentChoice($text)) {
            $lowerText = mb_strtolower(trim($text));
            $boletos = (int) ($state->collected_data['boletos_solicitados'] ?? 0);
            $montoCustom = (int) ($state->collected_data['monto_personalizado'] ?? 0);
            $totalAmount = $boletos > 0 ? $boletos * 3000 : ($montoCustom > 0 ? $montoCustom : 3000);

            if (str_contains($lowerText, 'tarjeta') || str_contains($lowerText, 'card') || str_contains($lowerText, 'credito') || str_contains($lowerText, 'debito')) {
                // Card payment - generate Stripe link
                $stripeService = app(StripeService::class);
                $checkoutUrl = $stripeService->createCheckoutSessionCustom($contact, $totalAmount, $boletos > 0 ? "{$boletos} boleto(s) de rifa" : "Donativo");

                if ($checkoutUrl) {
                    $reply = "\xF0\x9F\x92\xB3 Aqu\xC3\xAD est\xC3\xA1 tu link de pago por \$" . number_format($totalAmount, 0) . " MXN:\n\n{$checkoutUrl}\n\n\xC2\xA1Una vez que pagues, te confirmaremos autom\xC3\xA1ticamente!";
                } else {
                    $reply = "Hubo un problema generando tu link de pago. Puedes pagar por transferencia bancaria. Te env\xC3\xADo los datos:";
                    $this->whatsApp->sendBankDetails($from);
                }

                $state->update(['current_step' => 'esperando_pago_tarjeta', 'collected_data' => array_merge($state->collected_data ?? [], ['forma_pago' => 'tarjeta'])]);
                $contact->update(['status' => 'datos_enviados']);
                $result = $this->whatsApp->sendMessage($from, $reply);
                Message::create(['contact_id' => $contact->id, 'direction' => 'out', 'content' => $reply, 'wa_message_id' => $result['messages'][0]['id'] ?? null, 'status' => $result ? 'sent' : 'failed']);
                return;

            } elseif (str_contains($lowerText, 'transfer') || str_contains($lowerText, 'banco') || str_contains($lowerText, 'bancaria') || str_contains($lowerText, 'clabe')) {
                // Bank transfer
                $this->whatsApp->sendBankDetails($from);
                Message::create(['contact_id' => $contact->id, 'direction' => 'out', 'content' => '[Datos bancarios enviados]', 'status' => 'sent']);

                $reply = "Una vez que hagas la transferencia, env\xC3\xADanos la foto del comprobante por aqu\xC3\xAD. \xF0\x9F\x93\xB8";
                $state->update(['current_step' => 'esperando_comprobante', 'collected_data' => array_merge($state->collected_data ?? [], ['forma_pago' => 'transferencia'])]);
                $contact->update(['status' => 'datos_enviados']);
                $result = $this->whatsApp->sendMessage($from, $reply);
                Message::create(['contact_id' => $contact->id, 'direction' => 'out', 'content' => $reply, 'wa_message_id' => $result['messages'][0]['id'] ?? null, 'status' => $result ? 'sent' : 'failed']);
                return;
            }
            // If unclear, fall through to AI
        }

        // 4c. All other steps - let AI handle naturally (no more rigid interceptors)
        // The AI knows the context and can handle "quiero dar mas", questions, etc.

        // 5. Build conversation history
        $history = $this->buildConversationHistory($contact);

        // 6. Call AI
        $aiResponse = $this->anthropic->processConversation(
            $text,
            $state->current_step,
            $state->collected_data ?? [],
            $history,
        );

        // 6b. AI handles everything - no code-level overrides
        // The AI decides the next step and response naturally

        // 7. Send raffle image if AI requested
        if ($aiResponse['send_raffle_image']) {
            $this->sendRaffleImage($from);
        }

        // 8. Send bank details if AI requested
        if ($aiResponse['send_bank_details']) {
            $this->whatsApp->sendBankDetails($from);
            Message::create([
                'contact_id' => $contact->id,
                'direction' => 'out',
                'content' => '[Datos bancarios enviados]',
                'status' => 'sent',
            ]);
        }

        // 9. Update collected data and contact status
        $this->updateCollectedData($contact, $state, $aiResponse);
        $this->updateContactStatus($contact, $aiResponse);

        // 9b. Auto-generate Stripe link if AI moved to esperando_pago_tarjeta or mentioned tarjeta
        $nextStep = $aiResponse['next_step'] ?? $state->current_step;
        $responseText = mb_strtolower($aiResponse['response_text']);
        if ($nextStep === 'esperando_pago_tarjeta'
            || (str_contains($responseText, 'link de pago') && str_contains($responseText, 'tarjeta'))
            || str_contains($responseText, 'te voy a mandar el link')
            || str_contains($responseText, 'te envío el link')
            || str_contains($responseText, 'te envio el link')
        ) {
            $collected = $state->fresh()->collected_data ?? [];
            $boletos = (int) ($collected['boletos_solicitados'] ?? 0);
            $montoCustom = (int) ($collected['monto_personalizado'] ?? 0);
            $totalAmount = $boletos > 0 ? $boletos * 3000 : ($montoCustom > 0 ? $montoCustom : 3000);

            try {
                $stripeService = app(StripeService::class);
                $label = $boletos > 0 ? "{$boletos} boleto(s) de rifa" : "Donativo";
                $checkoutUrl = $stripeService->createCheckoutSessionCustom($contact, $totalAmount, $label);

                if ($checkoutUrl) {
                    $linkMsg = "\xF0\x9F\x92\xB3 Link de pago por \$" . number_format($totalAmount, 0) . " MXN:\n\n{$checkoutUrl}";
                    $linkResult = $this->whatsApp->sendMessage($from, $linkMsg);
                    Message::create(['contact_id' => $contact->id, 'direction' => 'out', 'content' => $linkMsg, 'wa_message_id' => $linkResult['messages'][0]['id'] ?? null, 'status' => $linkResult ? 'sent' : 'failed']);
                    $state->update(['current_step' => 'esperando_pago_tarjeta']);
                    Log::info('Auto-generated Stripe link', ['amount' => $totalAmount, 'contact_id' => $contact->id]);
                }
            } catch (\Throwable $e) {
                Log::error('Auto Stripe link failed', ['error' => $e->getMessage()]);
            }
        }

        // 10. Send AI reply
        $result = $this->whatsApp->sendMessage($from, $aiResponse['response_text']);

        // 11. Log outgoing message
        Message::create([
            'contact_id' => $contact->id,
            'direction' => 'out',
            'content' => $aiResponse['response_text'],
            'wa_message_id' => $result['messages'][0]['id'] ?? null,
            'status' => $result ? 'sent' : 'failed',
        ]);
    }

    /**
     * Handle an incoming image message (comprobante/receipt).
     */
    public function handleImageMessage(
        string $from,
        string $mediaId,
        string $waMessageId,
        ?string $senderName = null,
    ): void {
        $this->whatsApp->markAsRead($waMessageId);

        $contact = Contact::where('telefono', $from)->first();
        if (!$contact) {
            Log::warning('Image from unknown contact', ['from' => $from]);
            return;
        }

        // Log incoming image
        Message::create([
            'contact_id' => $contact->id,
            'direction' => 'in',
            'content' => '[Imagen recibida]',
            'wa_message_id' => $waMessageId,
            'status' => 'delivered',
        ]);

        $contact->update(['ultimo_contacto' => now()]);

        $state = ConversationState::where('contact_id', $contact->id)->first();

        // Download and analyze the image
        $imageData = $this->whatsApp->downloadMedia($mediaId);
        if (!$imageData) {
            $reply = "No pudimos descargar la imagen. \xC2\xBFPodr\xC3\xADas enviarla de nuevo?";
            $result = $this->whatsApp->sendMessage($from, $reply);
            Message::create([
                'contact_id' => $contact->id,
                'direction' => 'out',
                'content' => $reply,
                'wa_message_id' => $result['messages'][0]['id'] ?? null,
                'status' => $result ? 'sent' : 'failed',
            ]);
            return;
        }

        // Analyze receipt with Claude Vision
        $analysis = $this->anthropic->analyzeTransferReceipt($imageData);

        // Create donation record
        $donation = Donation::create([
            'contact_id' => $contact->id,
            'amount' => $analysis['amount'],
            'reference' => $analysis['reference'],
            'receipt_media_id' => $mediaId,
            'receipt_analysis' => $analysis,
            'confidence' => $analysis['confidence'],
            'status' => 'pending',
        ]);

        if ($analysis['is_receipt'] && in_array($analysis['confidence'], ['high', 'medium'])) {
            // Valid receipt - calculate tickets and confirm
            $amount = $analysis['amount'] ?? 3000;
            $boletos = max(1, (int) floor($amount / 3000));
            $donation->update([
                'boletos' => $boletos,
                'status' => 'verified',
                'verified_at' => now(),
            ]);

            $contact->update([
                'status' => 'donador',
                'boletos' => $contact->boletos + $boletos,
            ]);

            if ($state) {
                $state->update(['current_step' => 'confirmado']);
            }

            $ticketText = $boletos === 1 ? '1 boleto' : "{$boletos} boletos";
            $reply = "\xC2\xA1Comprobante recibido y verificado! \xE2\x9C\x85\n\n"
                . "Tienes {$ticketText} para la rifa del 30 de enero de 2027.\n\n"
                . "Que Hashem te bendiga por esta hermosa mitzv\xC3\xA1 de Hajnasat Kal\xC3\xA1. \xF0\x9F\x92\x8D\xF0\x9F\x95\x8E\n\n"
                . "\xC2\xA1Mucha suerte en el sorteo!";
        } else {
            // Could not verify - mark for manual review
            $reply = "Recibimos tu imagen. \xF0\x9F\x93\xB8 Nuestro equipo la revisar\xC3\xA1 y te confirmaremos tus boletos en breve. \xC2\xA1Gracias por tu paciencia!";
        }

        $result = $this->whatsApp->sendMessage($from, $reply);
        Message::create([
            'contact_id' => $contact->id,
            'direction' => 'out',
            'content' => $reply,
            'wa_message_id' => $result['messages'][0]['id'] ?? null,
            'status' => $result ? 'sent' : 'failed',
        ]);
    }

    /**
     * Handle template button quick-reply (me_interesa / no_gracias).
     */
    public function handleTemplateButtonReply(
        string $from,
        string $buttonPayload,
        string $waMessageId,
        ?string $senderName = null,
    ): void {
        $this->whatsApp->markAsRead($waMessageId);

        $contact = Contact::firstOrCreate(
            ['telefono' => $from],
            ['wa_id' => $from, 'nombre' => $senderName, 'pais' => Contact::detectCountry($from), 'status' => 'nuevo'],
        );
        $contact->update(['ultimo_contacto' => now(), 'wa_id' => $from]);

        Message::create([
            'contact_id' => $contact->id,
            'direction' => 'in',
            'content' => $buttonPayload,
            'wa_message_id' => $waMessageId,
            'status' => 'delivered',
        ]);

        $payload = strtolower(trim($buttonPayload));

        if (str_contains($payload, 'interesa') || str_contains($payload, 'si') || $payload === 'me interesa') {
            $contact->update(['status' => 'interesado']);

            $this->sendRaffleImage($from);

            $state = $this->getOrCreateState($contact);
            $state->update(['current_step' => 'inicio']);

            // Let the AI craft the greeting, sharing the opening in its own words.
            $history = $this->buildConversationHistory($contact);
            $aiResponse = $this->anthropic->processConversation(
                'Me interesa',
                $state->current_step,
                $state->collected_data ?? [],
                $history,
            );

            $this->updateCollectedData($contact, $state, $aiResponse);
            $this->updateContactStatus($contact, $aiResponse);

            $result = $this->whatsApp->sendMessage($from, $aiResponse['response_text']);
            Message::create([
                'contact_id' => $contact->id,
                'direction' => 'out',
                'content' => $aiResponse['response_text'],
                'wa_message_id' => $result['messages'][0]['id'] ?? null,
                'status' => $result ? 'sent' : 'failed',
            ]);
        } else {
            // Only send goodbye the first time they decline; ignore repeat "No gracias" taps.
            if ($contact->status === 'no_interesado') {
                return;
            }

            $contact->update(['status' => 'no_interesado']);

            $reply = "\xC2\xA1Gracias por tu tiempo! Si en el futuro te interesa participar, no dudes en escribirnos. Que Hashem te bendiga. \xF0\x9F\x99\x8F";
            $result = $this->whatsApp->sendMessage($from, $reply);

            Message::create([
                'contact_id' => $contact->id,
                'direction' => 'out',
                'content' => $reply,
                'wa_message_id' => $result['messages'][0]['id'] ?? null,
                'status' => $result ? 'sent' : 'failed',
            ]);
        }
    }

    /**
     * Send manual message from admin panel.
     */
    public function sendManualMessage(Contact $contact, string $messageText): ?array
    {
        $result = $this->whatsApp->sendMessage($contact->telefono, $messageText);

        Message::create([
            'contact_id' => $contact->id,
            'direction' => 'out',
            'content' => $messageText,
            'wa_message_id' => $result['messages'][0]['id'] ?? null,
            'status' => $result ? 'sent' : 'failed',
            'metadata' => ['source' => 'admin_manual'],
        ]);

        $contact->update(['ultimo_contacto' => now()]);
        return $result;
    }

    /**
     * Send the raffle flyer image.
     */
    private function sendRaffleImage(string $to): void
    {
        $mediaId = Setting::getValue('media_id_rifa_image');
        if ($mediaId) {
            $this->whatsApp->sendImage($to, $mediaId, "Rifa solidaria - Ayuda a una novia \xF0\x9F\x92\x8D");
        }
    }

    /**
     * Get or create conversation state, reset if expired.
     */
    private function getOrCreateState(Contact $contact): ConversationState
    {
        $seedData = $this->buildSeedData($contact);

        $state = ConversationState::firstOrCreate(
            ['contact_id' => $contact->id],
            [
                'current_step' => 'inicio',
                'collected_data' => $seedData,
                'last_interaction' => now(),
                'expires_at' => now()->addHours(24),
            ],
        );

        if ($state->expires_at && $state->expires_at->isPast()) {
            $state->update([
                'current_step' => 'inicio',
                'collected_data' => $seedData,
                'last_interaction' => now(),
                'expires_at' => now()->addHours(24),
            ]);
        }

        return $state;
    }

    /**
     * Build seed data from contact fields.
     *
     * @return array<string, mixed>
     */
    private function buildSeedData(Contact $contact): array
    {
        $data = [];
        if ($contact->nombre) {
            $data['nombre'] = $contact->nombre;
        }
        if ($contact->telefono) {
            $data['telefono_whatsapp'] = $contact->telefono;
        }
        if ($contact->boletos > 0) {
            $data['boletos_previos'] = $contact->boletos;
        }
        return $data;
    }

    /**
     * Build conversation history from last 10 messages.
     *
     * @return array<int, array{role: string, content: string}>
     */
    private function buildConversationHistory(Contact $contact): array
    {
        $messages = Message::where('contact_id', $contact->id)
            ->whereNotNull('content')
            ->orderBy('created_at', 'asc')
            ->take(10)
            ->get();

        return $messages->map(fn(Message $msg): array => [
            'role' => $msg->direction === 'in' ? 'user' : 'assistant',
            'content' => $msg->content,
        ])->toArray();
    }

    /**
     * Update collected data and conversation state from AI response.
     *
     * @param array<string, mixed> $aiResponse
     */
    private function updateCollectedData(Contact $contact, ConversationState $state, array $aiResponse): void
    {
        $extracted = $aiResponse['extracted_data'] ?? [];
        $collected = $state->collected_data ?? [];

        foreach ($extracted as $key => $value) {
            if ($value !== null) {
                $collected[$key] = $value;
            }
        }

        $contactUpdates = [];
        if (!empty($extracted['nombre'])) {
            $contactUpdates['nombre'] = $extracted['nombre'];
            $contactUpdates['nombre_completo'] = $extracted['nombre'];
        }
        if (!empty($extracted['boletos_solicitados'])) {
            $collected['boletos_solicitados'] = (int) $extracted['boletos_solicitados'];
        }

        if (!empty($contactUpdates)) {
            $contact->update($contactUpdates);
        }

        $state->update([
            'current_step' => $aiResponse['next_step'] ?? $state->current_step,
            'collected_data' => $collected,
            'last_interaction' => now(),
            'expires_at' => now()->addHours(24),
        ]);
    }

    /**
     * Update contact status based on AI response.
     *
     * @param array<string, mixed> $aiResponse
     */
    private function updateContactStatus(Contact $contact, array $aiResponse): void
    {
        $nextStep = $aiResponse['next_step'] ?? '';
        $intent = $aiResponse['intent'] ?? '';

        if ($nextStep === 'no_interesado') {
            $contact->update(['status' => 'no_interesado']);
            return;
        }

        if ($nextStep === 'confirmado') {
            $contact->update(['status' => 'donador']);
            return;
        }

        if ($nextStep === 'enviando_datos_bancarios' || $nextStep === 'esperando_comprobante') {
            $contact->update(['status' => 'datos_enviados']);
            return;
        }

        if (in_array($intent, ['interested', 'asking_details', 'ready_to_pay'])
            && in_array($contact->status, ['nuevo', 'contactado', 'leido'])) {
            $contact->update(['status' => 'interesado']);
        }
    }

    /**
     * Check if the user text is clearly a payment method choice.
     */
    private function isPaymentChoice(string $text): bool
    {
        $lower = mb_strtolower(trim($text));
        return str_contains($lower, 'tarjeta') || str_contains($lower, 'transferencia')
            || str_contains($lower, 'bancaria') || $lower === 'card'
            || str_contains($lower, 'con tarjeta') || str_contains($lower, 'por transferencia');
    }

    /**
     * Extract a number from user text (e.g., "1", "1 boleto", "dos", "un boleto").
     */
    private function extractNumberFromText(string $text): int
    {
        $text = mb_strtolower(trim($text));

        // Direct number
        if (preg_match('/^(\d+)/', $text, $m)) {
            return (int) $m[1];
        }

        // Spanish words
        $words = [
            'un' => 1, 'uno' => 1, 'una' => 1,
            'dos' => 2, 'tres' => 3, 'cuatro' => 4, 'cinco' => 5,
            'seis' => 6, 'siete' => 7, 'ocho' => 8, 'nueve' => 9, 'diez' => 10,
        ];

        foreach ($words as $word => $num) {
            if (str_starts_with($text, $word . ' ') || $text === $word) {
                return $num;
            }
        }

        return 0;
    }
}
