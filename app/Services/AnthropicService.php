<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Anthropic Claude API client for the wedding raffle chatbot.
 */
class AnthropicService
{
    private string $apiKey;
    private string $model;
    private string $apiVersion;
    private string $baseUrl;

    public function __construct()
    {
        $this->apiKey = config('services.anthropic.api_key', '');
        $this->model = config('services.anthropic.model', 'claude-sonnet-4-20250514');
        $this->apiVersion = config('services.anthropic.api_version', '2023-06-01');
        $this->baseUrl = config('services.anthropic.base_url', 'https://api.anthropic.com');
    }

    /**
     * Process a conversation turn through the Anthropic API.
     *
     * @param array<string, mixed> $collectedData
     * @param array<int, array{role: string, content: string}> $conversationHistory
     * @return array{response_text: string, next_step: string, extracted_data: array<string, mixed>, intent: string, send_raffle_image: bool, send_bank_details: bool}
     */
    public function processConversation(
        string $userMessage,
        string $currentStep,
        array $collectedData,
        array $conversationHistory,
    ): array {
        $systemPrompt = $this->buildSystemPrompt($currentStep, $collectedData);

        $messages = [];
        foreach ($conversationHistory as $msg) {
            $messages[] = [
                'role' => $msg['role'],
                'content' => $msg['content'],
            ];
        }
        $messages[] = [
            'role' => 'user',
            'content' => $userMessage,
        ];

        try {
            $response = Http::withHeaders([
                'x-api-key' => $this->apiKey,
                'anthropic-version' => $this->apiVersion,
                'Content-Type' => 'application/json',
            ])->timeout(30)->post("{$this->baseUrl}/v1/messages", [
                'model' => $this->model,
                'max_tokens' => 1024,
                'system' => $systemPrompt,
                'messages' => $messages,
            ]);

            if ($response->successful()) {
                $content = $response->json('content.0.text', '');
                return $this->parseAiResponse($content, $currentStep);
            }

            Log::error('Anthropic API error', [
                'status' => $response->status(),
                'body' => substr($response->body(), 0, 500),
                'current_step' => $currentStep,
            ]);
        } catch (\Throwable $e) {
            Log::error('Anthropic API exception', [
                'error' => $e->getMessage(),
                'current_step' => $currentStep,
            ]);
        }

        return $this->getFallbackResponse($currentStep, $userMessage);
    }

    /**
     * Analyze a transfer receipt image using Anthropic Vision API.
     *
     * @return array{is_receipt: bool, amount: float|null, reference: string|null, recipient_matches: bool, confidence: string}
     */
    public function analyzeTransferReceipt(string $imageData, string $mimeType = 'image/jpeg'): array
    {
        $base64 = base64_encode($imageData);

        $defaultResult = [
            'is_receipt' => false,
            'amount' => null,
            'reference' => null,
            'recipient_matches' => false,
            'confidence' => 'low',
        ];

        try {
            $response = Http::withHeaders([
                'x-api-key' => $this->apiKey,
                'anthropic-version' => $this->apiVersion,
                'Content-Type' => 'application/json',
            ])->timeout(30)->post("{$this->baseUrl}/v1/messages", [
                'model' => $this->model,
                'max_tokens' => 512,
                'messages' => [
                    [
                        'role' => 'user',
                        'content' => [
                            [
                                'type' => 'image',
                                'source' => [
                                    'type' => 'base64',
                                    'media_type' => $mimeType,
                                    'data' => $base64,
                                ],
                            ],
                            [
                                'type' => 'text',
                                'text' => 'Analyze this image. Is this a bank transfer receipt (comprobante de transferencia) or proof of payment? '
                                    . 'Expected recipient: Messod / BBVA Bancomer / Account ending in 0551. '
                                    . 'Expected amount should be a multiple of $3,000 MXN (one raffle ticket = $3,000). '
                                    . 'Respond in pure JSON WITHOUT backticks: '
                                    . '{"is_receipt": true/false, "amount": number_or_null, "reference": "string_or_null", "recipient_matches": true/false, "confidence": "high"/"medium"/"low"}',
                            ],
                        ],
                    ],
                ],
            ]);

            if (!$response->successful()) {
                Log::error('Anthropic Vision API error', [
                    'status' => $response->status(),
                    'body' => substr($response->body(), 0, 500),
                ]);
                return $defaultResult;
            }

            $content = $response->json('content.0.text', '');
            $content = trim($content);
            $content = preg_replace('/^```json?\s*/i', '', $content);
            $content = preg_replace('/\s*```$/', '', $content);
            $content = trim($content);

            $parsed = json_decode($content, true);

            if (!is_array($parsed) || !isset($parsed['is_receipt'])) {
                Log::warning('Failed to parse receipt analysis', ['content' => $content]);
                return $defaultResult;
            }

            return [
                'is_receipt' => (bool) $parsed['is_receipt'],
                'amount' => $parsed['amount'] ?? null,
                'reference' => $parsed['reference'] ?? null,
                'recipient_matches' => (bool) ($parsed['recipient_matches'] ?? false),
                'confidence' => $parsed['confidence'] ?? 'low',
            ];
        } catch (\Throwable $e) {
            Log::error('Anthropic Vision exception', ['error' => $e->getMessage()]);
            return $defaultResult;
        }
    }

    /**
     * Build the system prompt for the raffle chatbot.
     *
     * @param array<string, mixed> $collectedData
     */
    private function buildSystemPrompt(string $currentStep, array $collectedData): string
    {
        $dataJson = json_encode($collectedData, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

        return <<<PROMPT
Eres un novio que necesita ayuda para su boda. Hablas por WhatsApp de forma personal y cálida, como si fueras amigo de la persona.

## CONTEXTO:
Estás organizando una rifa solidaria para tu boda:
- Cada boleto cuesta \$3,000 MXN
- Premio: \$100,000 MXN
- Sorteo: 30 de enero de 2027
- 100% deducible de Maaser (diezmo de caridad judío)
- Es una mitzvá hermosa ayudar a unos novios (Hajnasat Kalá)
- También aceptas donaciones de CUALQUIER monto (no solo boletos de \$3,000)
- Todo apoyo es bien recibido
- IMPORTANTE: Todo es en PESOS MEXICANOS (MXN). Si alguien dice dólares, conviértelo a MXN (1 USD ≈ 20 MXN) y pon monto_personalizado en MXN siempre

## DATOS BANCARIOS (para compartir cuando el usuario quiera donar):
Banco: Bancomer (BBVA)
Titular: Messod
Cuenta: 048 133 0551
CLABE: 012 180 00481330551 8
Tarjeta de débito: 4152 3139 8046 2845
SWIFT: BCMRMXMMPYM

## TU PERSONALIDAD:
- Eres el novio hablando directamente, personal y cálido
- Hablas en español mexicano, informal y amigable
- Usas referencias de mitzvot cuando sea natural
- Mensajes CORTOS (es WhatsApp, máx 4 líneas)
- Nunca presiones. Si no quieren, despídete con cariño
- Si alguien quiere dar más, quiere dar menos, quiere cambiar de opción, o tiene dudas - ADÁPTATE a lo que digan. No seas rígido.
- Si alguien ya donó y quiere dar más, acéptalo con alegría

## ESTADO ACTUAL:
- Paso: {$currentStep}
- Datos recopilados: {$dataJson}

## PASOS DEL FLUJO:
- "inicio": El usuario respondió al template de la rifa. Saluda con calidez, explica brevemente la rifa. Hazlo atractivo: \$3,000 por boleto, puedes ganar \$100,000, y todo es Maaser deducible. Pregunta si le interesa participar. Pon send_raffle_image=true la primera vez.
- "presentando_rifa": El usuario está conociendo las opciones. Si dice "otra cantidad", "quiero dar menos", "otro monto", pregúntale cuánto le gustaría aportar. Si dice un número de boletos, avanza. Si pregunta detalles sobre la rifa, responde.
- "interesado": El usuario quiere participar. Puede querer boletos de rifa (\$3,000 c/u) O donar cualquier monto libre. Si dice un número de boletos (1, 2, 3, "un boleto"), pon boletos_solicitados=ese número y monto_personalizado=null. Si dice un monto libre ("quiero dar 500", "aporto 1000", "200 pesos"), pon monto_personalizado=ese monto y boletos_solicitados=0. Confirma el monto y pregunta: "¿Cómo prefieres pagar? 💳 Tarjeta o 🏦 Transferencia bancaria?" → next_step="eligiendo_pago". AVANZA SIEMPRE, nunca repitas la pregunta.
- "eligiendo_pago": El usuario elige forma de pago. Si dice "tarjeta", "card", "con tarjeta" → pon extracted_data.forma_pago="tarjeta" y next_step="esperando_pago_tarjeta". Si dice "transferencia", "transfer", "banco" → pon extracted_data.forma_pago="transferencia", send_bank_details=true, y next_step="esperando_comprobante".
- "esperando_pago_tarjeta": Le enviamos link de pago. Si pregunta algo, responde. Si quiere dar más o cambiar, acéptalo (vuelve a eligiendo_pago con el nuevo monto).
- "esperando_comprobante": Esperamos comprobante de transferencia. Si pregunta algo, responde. Si quiere dar más o cambiar a tarjeta, acéptalo. Si solo dice "listo" o algo irrelevante, pídele la foto del comprobante.
- "confirmado": Ya donó. Agradece. Si quiere dar más, acéptalo con alegría y vuelve a preguntar monto y forma de pago. Nunca rechaces una donación adicional.
- "no_interesado": Despedida amable. Si cambia de opinión, recíbelo con gusto.

## RESPUESTA:
JSON puro SIN backticks ni markdown:
{
    "response_text": "mensaje para el usuario",
    "next_step": "paso_siguiente",
    "extracted_data": {
        "nombre": null,
        "boletos_solicitados": null,
        "monto_personalizado": null,
        "telefono": null,
        "forma_pago": null
    },
    "intent": "interested|not_interested|asking_details|ready_to_pay|choosing_card|choosing_transfer|sending_receipt|general_chat|unclear",
    "send_raffle_image": false,
    "send_bank_details": false
}

REGLAS:
- NO inventes datos. Solo extrae lo explícito.
- Mensajes CORTOS (máx 4 líneas). Es WhatsApp.
- Si dice su nombre en cualquier momento, ponlo en extracted_data.nombre.
- send_raffle_image=true SOLO la primera vez, al inicio o cuando pidan ver la imagen.
- send_bank_details=true SOLO cuando confirme que quiere boletos y esté listo para pagar. NO lo pongas en true más de una vez por conversación.
- CRÍTICO BOLETOS: Si el usuario escribe un número ("1", "2", "3", "uno", "dos", "1 boleto", "un boleto", "quiero 1", etc.), ESO ES la cantidad de boletos. Pon extracted_data.boletos_solicitados=ese número, confirma el total, y AVANZA a next_step="eligiendo_pago". NUNCA vuelvas a preguntar cuántos boletos si ya dio un número.
- Si pregunta por múltiples boletos, calcula: boletos × \$3,000 y menciona el total.
- Si el usuario dice que NO quiere o no puede, ponlo como next_step="no_interesado" y respeta su decisión.
- IMPORTANTE: next_step SIEMPRE debe avanzar cuando el usuario responde correctamente. NUNCA repitas la misma pregunta. Si el paso actual es "interesado" y el usuario da un número, el next_step DEBE ser "eligiendo_pago".
- En "esperando_comprobante", SIEMPRE mantén next_step="esperando_comprobante" si el mensaje es texto.
- En "esperando_pago_tarjeta", SIEMPRE mantén next_step="esperando_pago_tarjeta" si el mensaje es texto.
PROMPT;
    }

    /**
     * Parse the AI JSON response.
     *
     * @return array{response_text: string, next_step: string, extracted_data: array<string, mixed>, intent: string, send_raffle_image: bool, send_bank_details: bool}
     */
    private function parseAiResponse(string $content, string $currentStep): array
    {
        $content = trim($content);
        $content = preg_replace('/^```json?\s*/i', '', $content);
        $content = preg_replace('/\s*```$/', '', $content);
        $content = trim($content);

        // Try to extract JSON from the response (AI sometimes wraps it in text)
        if (preg_match('/\{[\s\S]*"response_text"[\s\S]*\}/m', $content, $jsonMatch)) {
            $content = $jsonMatch[0];
        }

        $parsed = json_decode($content, true);

        if (!is_array($parsed) || empty($parsed['response_text'])) {
            // AI returned plain text instead of JSON - use it directly
            // This is better than a generic fallback
            if (strlen($content) > 10 && strlen($content) < 2000) {
                Log::info('AI returned plain text, using directly', ['content_length' => strlen($content)]);
                return [
                    'response_text' => $content,
                    'next_step' => $currentStep,
                    'extracted_data' => [],
                    'intent' => 'unclear',
                    'send_raffle_image' => false,
                    'send_bank_details' => false,
                ];
            }
            Log::warning('Failed to parse AI response', ['content' => $content]);
            return $this->getFallbackResponse($currentStep, '');
        }

        return [
            'response_text' => $parsed['response_text'],
            'next_step' => $parsed['next_step'] ?? $currentStep,
            'extracted_data' => $parsed['extracted_data'] ?? [],
            'intent' => $parsed['intent'] ?? 'unclear',
            'send_raffle_image' => (bool) ($parsed['send_raffle_image'] ?? false),
            'send_bank_details' => (bool) ($parsed['send_bank_details'] ?? false),
        ];
    }

    /**
     * Get a static fallback response when the API is unavailable.
     *
     * @return array{response_text: string, next_step: string, extracted_data: array<string, mixed>, intent: string, send_raffle_image: bool, send_bank_details: bool}
     */
    private function getFallbackResponse(string $currentStep, string $userMessage = ''): array
    {
        $fallbacks = [
            'inicio' => [
                'response_text' => "\xC2\xA1Shalom! \xF0\x9F\x92\x8D Te invitamos a participar en nuestra rifa solidaria para ayudar a una novia de la comunidad. Boletos a \$3,000 MXN con premio de \$100,000 MXN. 100% deducible de Maaser. \xC2\xBFTe interesa?",
                'next_step' => 'presentando_rifa',
                'send_raffle_image' => true,
                'send_bank_details' => false,
                'intent' => 'interested',
            ],
            'presentando_rifa' => [
                'response_text' => "\xC2\xA1Claro! Puedes comprar boletos de rifa (\$3,000 c/u) o aportar el monto que gustes. \xC2\xBFCu\xC3\xA1nto te gustar\xC3\xADa aportar? \xF0\x9F\x99\x8F",
                'next_step' => 'interesado',
                'send_raffle_image' => false,
                'send_bank_details' => false,
                'intent' => 'asking_details',
            ],
            'interesado' => [
                'response_text' => "\xC2\xA1Gracias! \xC2\xBFCu\xC3\xA1nto te gustar\xC3\xADa aportar? Puedes comprar boletos (\$3,000 c/u) o donar cualquier monto.",
                'next_step' => 'interesado',
                'send_raffle_image' => false,
                'send_bank_details' => false,
                'intent' => 'interested',
            ],
            'enviando_datos_bancarios' => [
                'response_text' => "Te env\xC3\xADo los datos bancarios para la transferencia. Una vez que la hagas, env\xC3\xADanos la foto del comprobante por aqu\xC3\xAD.",
                'next_step' => 'esperando_comprobante',
                'send_raffle_image' => false,
                'send_bank_details' => true,
                'intent' => 'ready_to_pay',
            ],
            'esperando_comprobante' => [
                'response_text' => "Estamos esperando tu comprobante de transferencia. Por favor env\xC3\xADanos una foto o captura de pantalla del comprobante. \xF0\x9F\x93\xB8",
                'next_step' => 'esperando_comprobante',
                'send_raffle_image' => false,
                'send_bank_details' => false,
                'intent' => 'sending_receipt',
            ],
            'confirmado' => [
                'response_text' => "\xC2\xA1Gracias por tu generosidad! Ya est\xC3\xA1s registrado como donador. Que Hashem te bendiga por esta hermosa mitzv\xC3\xA1. \xF0\x9F\x92\x8D\xF0\x9F\x95\x8E",
                'next_step' => 'confirmado',
                'send_raffle_image' => false,
                'send_bank_details' => false,
                'intent' => 'general_chat',
            ],
        ];

        $fallback = $fallbacks[$currentStep] ?? $fallbacks['inicio'];

        return [
            'response_text' => $fallback['response_text'],
            'next_step' => $fallback['next_step'],
            'extracted_data' => [],
            'intent' => $fallback['intent'],
            'send_raffle_image' => $fallback['send_raffle_image'],
            'send_bank_details' => $fallback['send_bank_details'],
        ];
    }
}
