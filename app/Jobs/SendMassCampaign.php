<?php

namespace App\Jobs;

use App\Models\Campaign;
use App\Models\Message;
use App\Models\MessageTemplate;
use App\Services\WhatsAppService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

/**
 * Queue job to send raffle campaign to contacts.
 */
class SendMassCampaign implements ShouldQueue
{
    use Queueable;

    public int $timeout = 3600;

    public int $tries = 1;

    public function __construct(
        private Campaign $campaign,
    ) {}

    public function handle(): void
    {
        app()->instance('currentOrganization', $this->campaign->organization);
        $whatsApp = app(WhatsAppService::class);
        $template = MessageTemplate::where('name', $this->campaign->template_name)->where('status', 'approved')->firstOrFail();
        Log::info('Campaign started', ['campaign_id' => $this->campaign->id, 'total' => $this->campaign->total_contacts]);

        $contacts = $this->campaign->contacts()->wherePivot('status', 'pending')->get();
        $sentCount = 0;

        foreach ($contacts as $contact) {
            $result = $whatsApp->sendTemplate($contact->telefono, $template->name, [], $template->language);

            if ($result) {
                $waMessageId = $result['messages'][0]['id'] ?? null;
                $this->campaign->contacts()->updateExistingPivot($contact->id, [
                    'wa_message_id' => $waMessageId,
                    'status' => 'sent',
                ]);
                $sentCount++;

                Message::create([
                    'contact_id' => $contact->id,
                    'direction' => 'out',
                    'content' => "[Plantilla: {$template->name}]\n{$template->body}",
                    'wa_message_id' => $waMessageId,
                    'status' => 'sent',
                ]);

                if ($contact->status === 'nuevo') {
                    $contact->update(['ultimo_contacto' => now()]);
                }
            } else {
                $this->campaign->contacts()->updateExistingPivot($contact->id, [
                    'status' => 'failed',
                ]);
                $this->campaign->increment('failed_count');
            }

            $this->campaign->update(['sent_count' => $sentCount]);

            // Rate limit: ~80 messages/second max, we do 5/second to be safe
            usleep(200_000);
        }

        $this->campaign->update([
            'status' => 'completed',
            'sent_count' => $sentCount,
        ]);

        Log::info('Campaign completed', ['campaign_id' => $this->campaign->id, 'sent' => $sentCount]);
    }
}
