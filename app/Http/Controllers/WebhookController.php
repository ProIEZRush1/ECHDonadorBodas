<?php

namespace App\Http\Controllers;

use App\Models\Campaign;
use App\Models\Contact;
use App\Models\Message;
use App\Services\ConversationService;
use App\Services\WhatsAppService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

/**
 * WhatsApp webhook controller for the wedding raffle system.
 */
class WebhookController extends Controller
{
    public function __construct(
        private ConversationService $conversation,
        private WhatsAppService $whatsApp,
    ) {}

    /**
     * Handle webhook verification from Meta.
     */
    public function verify(Request $request): Response
    {
        Log::info('=== WEBHOOK GET ===', ['query' => $request->query()]);

        $mode = $request->query('hub_mode');
        $token = $request->query('hub_verify_token');
        $challenge = $request->query('hub_challenge');

        if ($mode === 'subscribe' && $token === config('services.whatsapp.verify_token')) {
            Log::info('Webhook verified');
            return response($challenge, 200)->header('Content-Type', 'text/plain');
        }

        Log::warning('Webhook verification failed', ['token' => $token]);
        return response('Forbidden', 403);
    }

    /**
     * Handle incoming webhook events from Meta.
     */
    public function handle(Request $request): JsonResponse
    {
        Log::info('=== WEBHOOK POST ===', [
            'body_length' => strlen($request->getContent()),
            'body_preview' => substr($request->getContent(), 0, 2000),
        ]);

        $data = $request->all();
        $entries = $data['entry'] ?? [];

        foreach ($entries as $entry) {
            $changes = $entry['changes'] ?? [];
            foreach ($changes as $change) {
                $field = $change['field'] ?? '';
                $value = $change['value'] ?? [];

                if ($field === 'messages') {
                    $this->processStatuses($value['statuses'] ?? []);
                    $this->processMessages($value['messages'] ?? [], $value['contacts'] ?? []);
                }
            }
        }

        return response()->json(['status' => 'ok']);
    }

    /**
     * Process message status updates (sent, delivered, read).
     *
     * @param array<int, array<string, mixed>> $statuses
     */
    private function processStatuses(array $statuses): void
    {
        foreach ($statuses as $status) {
            $waMessageId = $status['id'] ?? null;
            $newStatus = $status['status'] ?? null;

            if (!$waMessageId || !$newStatus) {
                continue;
            }

            $statusMap = [
                'sent' => 'sent',
                'delivered' => 'delivered',
                'read' => 'read',
                'failed' => 'failed',
            ];

            $mappedStatus = $statusMap[$newStatus] ?? null;
            if (!$mappedStatus) {
                continue;
            }

            // Update message record
            $message = Message::where('wa_message_id', $waMessageId)->first();
            if ($message) {
                $message->update(['status' => $mappedStatus]);

                // Update contact status for outbound messages
                if ($message->direction === 'out') {
                    $contact = $message->contact;
                    if ($contact) {
                        $contact->update(['ultimo_mensaje_status' => $mappedStatus]);

                        // Advance contact status based on delivery
                        if ($mappedStatus === 'delivered' && $contact->status === 'nuevo') {
                            $contact->update(['status' => 'contactado']);
                        } elseif ($mappedStatus === 'read' && in_array($contact->status, ['nuevo', 'contactado'])) {
                            $contact->update(['status' => 'leido']);
                        }
                    }
                }
            }

            // Update campaign_contact pivot (source of truth; counts are computed from here)
            \DB::table('campaign_contact')
                ->where('wa_message_id', $waMessageId)
                ->update(['status' => $mappedStatus]);
        }
    }

    /**
     * Process incoming messages.
     *
     * @param array<int, array<string, mixed>> $messages
     * @param array<int, array<string, mixed>> $contacts
     */
    private function processMessages(array $messages, array $contacts): void
    {
        $contactMap = [];
        foreach ($contacts as $c) {
            $contactMap[$c['wa_id'] ?? ''] = $c['profile']['name'] ?? null;
        }

        foreach ($messages as $message) {
            $from = $message['from'] ?? '';
            $type = $message['type'] ?? '';
            $waMessageId = $message['id'] ?? '';
            $senderName = $contactMap[$from] ?? null;

            // Only process messages from contacts in our database (webhook mirror filtering)
            $existingContact = Contact::where('telefono', $from)->orWhere('wa_id', $from)->first();

            Log::info('Processing message', [
                'from' => $from,
                'type' => $type,
                'wa_id' => $waMessageId,
                'known_contact' => (bool) $existingContact,
            ]);

            match ($type) {
                'text' => $this->conversation->handleIncomingMessage(
                    $from,
                    $message['text']['body'] ?? '',
                    $waMessageId,
                    $senderName,
                ),
                'button' => $this->conversation->handleTemplateButtonReply(
                    $from,
                    $message['button']['text'] ?? $message['button']['payload'] ?? '',
                    $waMessageId,
                    $senderName,
                ),
                'interactive' => $this->handleInteractiveReply($from, $message, $waMessageId, $senderName),
                'image' => $this->conversation->handleImageMessage(
                    $from,
                    $message['image']['id'] ?? '',
                    $waMessageId,
                    $senderName,
                ),
                default => Log::info('Unhandled message type', ['type' => $type, 'from' => $from]),
            };
        }
    }

    /**
     * Handle interactive message replies (button_reply, list_reply).
     */
    private function handleInteractiveReply(string $from, array $message, string $waMessageId, ?string $senderName): void
    {
        $interactive = $message['interactive'] ?? [];
        $type = $interactive['type'] ?? '';

        if ($type === 'button_reply') {
            $buttonId = $interactive['button_reply']['id'] ?? '';
            $buttonTitle = $interactive['button_reply']['title'] ?? '';
            $this->conversation->handleTemplateButtonReply($from, $buttonTitle ?: $buttonId, $waMessageId, $senderName);
        } elseif ($type === 'list_reply') {
            $listId = $interactive['list_reply']['id'] ?? '';
            $listTitle = $interactive['list_reply']['title'] ?? '';
            $this->conversation->handleIncomingMessage($from, $listTitle ?: $listId, $waMessageId, $senderName);
        }
    }
}
