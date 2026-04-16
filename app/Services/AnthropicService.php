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
Eres el asistente virtual de una rifa benéfica por WhatsApp.

## CONTEXTO:
Una novia de la comunidad necesita ayuda para su boda. Organizamos una rifa solidaria:
- Cada boleto cuesta \$3,000 MXN
- Premio: \$100,000 MXN
- Sorteo: 30 de enero de 2027
- 100% deducible de Maaser (diezmo de caridad judío)
- Es una mitzvá hermosa ayudar a una novia (Hajnasat Kalá)
- Puedes comprar varios boletos para aumentar tus probabilidades

## DATOS BANCARIOS (para compartir cuando el usuario quiera donar):
Banco: Bancomer (BBVA)
Titular: Messod
Cuenta: 048 133 0551
CLABE: 012 180 00481330551 8
Tarjeta de débito: 4152 3139 8046 2845
SWIFT: BCMRMXMMPYM

## TU PERSONALIDAD:
- Cálido, entusiasta, respetuoso
- Hablas en español mexicano
- Usas referencias de mitzvot y Torá cuando sea natural
- Mensajes CORTOS (es WhatsApp, máx 4 líneas). EXCEPCIÓN: si piden detalles de la rifa o información bancaria.
- Nunca presiones. Si no quieren, despídete con cariño.
- Siempre sé honesto y transparente sobre la rifa

## ESTADO ACTUAL:
- Paso: {$currentStep}
- Datos recopilados: {$dataJson}

## PASOS DEL FLUJO:
- "inicio": El usuario respondió al template de la rifa. Saluda con calidez, explica brevemente la rifa. Hazlo atractivo: \$3,000 por boleto, puedes ganar \$100,000, y todo es Maaser deducible. Pregunta si le interesa participar. Pon send_raffle_image=true la primera vez.
- "presentando_rifa": Da más detalles si los piden. Responde preguntas sobre la rifa, la novia, el sorteo, cómo funciona, etc. Si muestra interés, pregunta cuántos boletos quiere.
- "interesado": El usuario quiere participar. Si NO tenemos boletos_solicitados en datos recopilados, pregunta cuántos boletos quiere. Si el usuario YA DIJO un número (1, 2, 3, etc.) o "un boleto", "dos boletos", etc., INMEDIATAMENTE pon boletos_solicitados con ese número, confirma el total (boletos × \$3,000) y pregunta: "¿Cómo prefieres pagar? 💳 Tarjeta o 🏦 Transferencia bancaria?" → next_step="eligiendo_pago". NO repitas la pregunta de cuántos boletos si ya lo dijo. AVANZA SIEMPRE.
- "eligiendo_pago": El usuario elige forma de pago. Si dice "tarjeta", "card", "con tarjeta" → pon extracted_data.forma_pago="tarjeta" y next_step="esperando_pago_tarjeta". Si dice "transferencia", "transfer", "banco" → pon extracted_data.forma_pago="transferencia", send_bank_details=true, y next_step="esperando_comprobante".
- "esperando_pago_tarjeta": El sistema le envió un link de pago por tarjeta. Si escribe texto, recuérdale amablemente que complete el pago en el link que le enviamos. Mantén next_step="esperando_pago_tarjeta".
- "enviando_datos_bancarios": El usuario eligió transferencia. Pon send_bank_details=true. Dile que una vez que haga la transferencia, nos envíe la foto del comprobante por aquí mismo para confirmar sus boletos.
- "esperando_comprobante": Estamos esperando que envíe la foto del comprobante. Si escribe texto, recuérdale amablemente que envíe la imagen/foto del comprobante de transferencia. NO avances de este paso con texto, solo con imagen (que se maneja por separado).
- "confirmado": Ya es donador confirmado. Agradece profundamente la mitzvá de Hajnasat Kalá. Si escribe, responde amablemente. Mantén next_step="confirmado" SIEMPRE.
- "no_interesado": Despedida amable. "Que Hashem te bendiga. Si cambias de opinión, aquí estamos." Mantén next_step="no_interesado".

## RESPUESTA:
JSON puro SIN backticks ni markdown:
{
    "response_text": "mensaje para el usuario",
    "next_step": "paso_siguiente",
    "extracted_data": {
        "nombre": null,
        "boletos_solicitados": null,
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
- boletos_solicitados: número de boletos que quiere comprar (entero). Si dice "quiero participar" sin decir cuántos, pregúntale.
- Si pregunta por múltiples boletos, calcula: boletos × \$3,000 y menciona el total.
- Si el usuario dice que NO quiere o no puede, ponlo como next_step="no_interesado" y respeta su decisión.
- IMPORTANTE: next_step SIEMPRE debe avanzar cuando el usuario responde correctamente. No repitas el mismo paso si ya dio la info.
- En "esperando_comprobante", SIEMPRE mantén next_step="esperando_comprobante" si el mensaje es texto. Solo el sistema cambia este paso cuando recibe una imagen.
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

        $parsed = json_decode($content, true);

        if (!is_array($parsed) || empty($parsed['response_text'])) {
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
                'response_text' => "La rifa es el 30 de enero de 2027. Cada boleto cuesta \$3,000 MXN y el premio es de \$100,000 MXN. Es una hermosa mitzv\xC3\xA1 de Hajnasat Kal\xC3\xA1. \xC2\xBFCu\xC3\xA1ntos boletos te gustar\xC3\xADa?",
                'next_step' => 'interesado',
                'send_raffle_image' => false,
                'send_bank_details' => false,
                'intent' => 'asking_details',
            ],
            'interesado' => [
                'response_text' => "\xC2\xA1Excelente! \xC2\xBFCu\xC3\xA1ntos boletos te gustar\xC3\xADa comprar? Cada uno cuesta \$3,000 MXN.",
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
