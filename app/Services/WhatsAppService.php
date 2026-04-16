<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * WhatsApp Business API client for the wedding raffle system.
 */
class WhatsAppService
{
    private string $baseUrl;
    private string $token;
    private string $accountId;
    private string $apiVersion;
    private ?string $phoneNumberId = null;
    private ?string $lastError = null;

    public function __construct()
    {
        $this->baseUrl = config('services.whatsapp.base_url', 'https://graph.facebook.com');
        $this->apiVersion = config('services.whatsapp.api_version', 'v21.0');
        $this->token = config('services.whatsapp.token', '');
        $this->accountId = config('services.whatsapp.account_id', '');
        $this->phoneNumberId = config('services.whatsapp.phone_number_id') ?: null;
    }

    /**
     * Auto-detect and cache the Phone Number ID from the WABA.
     */
    public function getPhoneNumberId(): ?string
    {
        if ($this->phoneNumberId) {
            return $this->phoneNumberId;
        }

        if (empty($this->accountId) || empty($this->token)) {
            Log::error('WhatsApp credentials missing');
            return null;
        }

        return Cache::remember('whatsapp_phone_number_id', 3600, function (): ?string {
            $url = "{$this->baseUrl}/{$this->apiVersion}/{$this->accountId}/phone_numbers";
            $response = Http::withToken($this->token)->timeout(15)->get($url);

            if ($response->successful() && !empty($response->json('data.0.id'))) {
                $phoneId = $response->json('data.0.id');
                Log::info('WhatsApp Phone Number ID detected', ['phone_id' => $phoneId]);
                return $phoneId;
            }

            Log::error('Failed to detect WhatsApp Phone Number ID', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            return null;
        });
    }

    /**
     * Send a text message.
     *
     * @return array<string, mixed>|null
     */
    public function sendMessage(string $to, string $text): ?array
    {
        $phoneId = $this->getPhoneNumberId();
        if (!$phoneId) {
            return null;
        }

        return $this->sendWithRetry($phoneId, [
            'messaging_product' => 'whatsapp',
            'to' => $to,
            'type' => 'text',
            'text' => ['body' => $text],
        ]);
    }

    /**
     * Send a template message.
     *
     * @param array<int, array<string, mixed>> $components
     * @return array<string, mixed>|null
     */
    public function sendTemplate(string $to, string $templateName, array $components = [], string $language = 'es'): ?array
    {
        $phoneId = $this->getPhoneNumberId();
        if (!$phoneId) {
            return null;
        }

        $template = [
            'name' => $templateName,
            'language' => ['code' => $language],
        ];

        if (!empty($components)) {
            $template['components'] = $components;
        }

        return $this->sendWithRetry($phoneId, [
            'messaging_product' => 'whatsapp',
            'to' => $to,
            'type' => 'template',
            'template' => $template,
        ]);
    }

    /**
     * Send the raffle announcement template with image and quick reply buttons.
     *
     * @return array<string, mixed>|null
     */
    public function sendRaffleTemplate(string $to, string $contactName = 'Amigo'): ?array
    {
        $phoneId = $this->getPhoneNumberId();
        if (!$phoneId) {
            return null;
        }

        return $this->sendWithRetry($phoneId, [
            'messaging_product' => 'whatsapp',
            'to' => $to,
            'type' => 'template',
            'template' => [
                'name' => 'rifa_boda',
                'language' => ['code' => 'es'],
                'components' => [
                    [
                        'type' => 'body',
                        'parameters' => [
                            [
                                'type' => 'text',
                                'text' => $contactName,
                            ],
                        ],
                    ],
                ],
            ],
        ]);
    }

    /**
     * Send formatted bank transfer details.
     */
    public function sendBankDetails(string $to): ?array
    {
        $details = "\xF0\x9F\x8F\xA6 *Datos para transferencia bancaria*\n\n"
            . "*Banco:* Bancomer (BBVA)\n"
            . "*Titular:* Messod\n"
            . "*No. de cuenta:* 048 133 0551\n"
            . "*CLABE:* 012 180 00481330551 8\n"
            . "*Tarjeta de d\xC3\xA9bito:* 4152 3139 8046 2845\n"
            . "*C\xC3\xB3digo SWIFT:* BCMRMXMMPYM\n\n"
            . "\xF0\x9F\x93\xB8 Una vez que hagas la transferencia, env\xC3\xADanos la foto del comprobante por aqu\xC3\xAD y te confirmamos tus boletos.";

        return $this->sendMessage($to, $details);
    }

    /**
     * Send an image message.
     *
     * @return array<string, mixed>|null
     */
    public function sendImage(string $to, string $mediaId, ?string $caption = null): ?array
    {
        $phoneId = $this->getPhoneNumberId();
        if (!$phoneId) {
            return null;
        }

        $image = ['id' => $mediaId];
        if ($caption) {
            $image['caption'] = $caption;
        }

        return $this->sendWithRetry($phoneId, [
            'messaging_product' => 'whatsapp',
            'to' => $to,
            'type' => 'image',
            'image' => $image,
        ]);
    }

    /**
     * Upload a media file to WhatsApp servers.
     */
    public function uploadMedia(string $filePath, string $mimeType): ?string
    {
        $phoneId = $this->getPhoneNumberId();
        if (!$phoneId) {
            return null;
        }

        $url = "{$this->baseUrl}/{$this->apiVersion}/{$phoneId}/media";
        $response = Http::withToken($this->token)
            ->attach('file', file_get_contents($filePath), basename($filePath))
            ->post($url, [
                'messaging_product' => 'whatsapp',
                'type' => $mimeType,
            ]);

        if ($response->successful() && $response->json('id')) {
            $mediaId = $response->json('id');
            Log::info('Media uploaded', ['media_id' => $mediaId]);
            return $mediaId;
        }

        Log::error('Media upload failed', [
            'file' => basename($filePath),
            'status' => $response->status(),
            'body' => $response->body(),
        ]);
        return null;
    }

    /**
     * Download a media file from WhatsApp by media ID.
     */
    public function downloadMedia(string $mediaId): ?string
    {
        $url = "{$this->baseUrl}/{$this->apiVersion}/{$mediaId}";

        try {
            $response = Http::withToken($this->token)->timeout(15)->get($url);

            if (!$response->successful()) {
                Log::error('Failed to get media URL', ['media_id' => $mediaId, 'status' => $response->status()]);
                return null;
            }

            $downloadUrl = $response->json('url');
            if (!$downloadUrl) {
                Log::error('No download URL in media response', ['media_id' => $mediaId]);
                return null;
            }

            $fileResponse = Http::withToken($this->token)->timeout(30)->get($downloadUrl);

            if (!$fileResponse->successful()) {
                Log::error('Failed to download media', ['media_id' => $mediaId, 'status' => $fileResponse->status()]);
                return null;
            }

            Log::info('Media downloaded', ['media_id' => $mediaId, 'size' => strlen($fileResponse->body())]);
            return $fileResponse->body();
        } catch (\Throwable $e) {
            Log::error('Media download exception', ['media_id' => $mediaId, 'error' => $e->getMessage()]);
            return null;
        }
    }

    /**
     * Send interactive buttons message.
     *
     * @param array<int, array{id: string, title: string}> $buttons
     * @return array<string, mixed>|null
     */
    public function sendButtons(string $to, string $body, array $buttons): ?array
    {
        $phoneId = $this->getPhoneNumberId();
        if (!$phoneId) {
            return null;
        }

        $buttonActions = array_map(fn(array $btn): array => [
            'type' => 'reply',
            'reply' => $btn,
        ], array_slice($buttons, 0, 3));

        return $this->sendWithRetry($phoneId, [
            'messaging_product' => 'whatsapp',
            'to' => $to,
            'type' => 'interactive',
            'interactive' => [
                'type' => 'button',
                'body' => ['text' => $body],
                'action' => ['buttons' => $buttonActions],
            ],
        ]);
    }

    /**
     * Mark a message as read.
     */
    public function markAsRead(string $messageId): void
    {
        $phoneId = $this->getPhoneNumberId();
        if (!$phoneId) {
            return;
        }

        $url = "{$this->baseUrl}/{$this->apiVersion}/{$phoneId}/messages";
        Http::withToken($this->token)->post($url, [
            'messaging_product' => 'whatsapp',
            'status' => 'read',
            'message_id' => $messageId,
        ]);
    }

    /**
     * Send with retry and exponential backoff.
     *
     * @param array<string, mixed> $payload
     * @return array<string, mixed>|null
     */
    private function sendWithRetry(string $phoneId, array $payload, int $maxRetries = 3): ?array
    {
        $url = "{$this->baseUrl}/{$this->apiVersion}/{$phoneId}/messages";
        $this->lastError = null;

        for ($attempt = 1; $attempt <= $maxRetries; $attempt++) {
            try {
                $response = Http::withToken($this->token)->timeout(15)->post($url, $payload);
            } catch (ConnectionException $e) {
                $this->lastError = $e->getMessage();
                Log::error('WhatsApp API connection failed', [
                    'attempt' => $attempt,
                    'error' => $e->getMessage(),
                    'to' => $payload['to'] ?? 'unknown',
                ]);
                if ($attempt < $maxRetries) {
                    usleep($attempt * 500_000);
                }
                continue;
            }

            if ($response->successful()) {
                return $response->json();
            }

            $this->lastError = $response->body();
            Log::warning('WhatsApp API request failed', [
                'attempt' => $attempt,
                'status' => $response->status(),
                'body' => $response->body(),
                'to' => $payload['to'] ?? 'unknown',
            ]);

            if ($attempt < $maxRetries) {
                usleep($attempt * 500_000);
            }
        }

        Log::error('WhatsApp API failed after all retries', [
            'to' => $payload['to'] ?? 'unknown',
            'type' => $payload['type'] ?? 'unknown',
        ]);
        return null;
    }
}
