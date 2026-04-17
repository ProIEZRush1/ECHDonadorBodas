<?php

namespace App\Http\Controllers;

use App\Jobs\SendMassCampaign;
use App\Models\Campaign;
use App\Models\Contact;
use App\Models\ConversationState;
use App\Models\Donation;
use App\Models\Message;
use App\Services\ConversationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Admin panel controller for the wedding raffle system.
 */
class AdminController extends Controller
{
    public function __construct(
        private ConversationService $conversation,
    ) {}

    /**
     * Dashboard with raffle statistics.
     */
    public function dashboard(): View
    {
        $stats = [
            'total' => Contact::count(),
            'nuevo' => Contact::where('status', 'nuevo')->count(),
            'contactado' => Contact::where('status', 'contactado')->count(),
            'leido' => Contact::where('status', 'leido')->count(),
            'interesado' => Contact::where('status', 'interesado')->count(),
            'datos_enviados' => Contact::where('status', 'datos_enviados')->count(),
            'donador' => Contact::where('status', 'donador')->count(),
            'no_interesado' => Contact::where('status', 'no_interesado')->count(),
            'mensajes_hoy' => Message::whereDate('created_at', today())->count(),
        ];

        $totalBoletos = Contact::sum('boletos');
        $totalRecaudado = $totalBoletos * 3000;

        // Countdown to raffle
        $sorteoDate = \Carbon\Carbon::parse('2027-01-30');
        $diasRestantes = (int) now()->diffInDays($sorteoDate, false);

        $recentContacts = Contact::orderByDesc('ultimo_contacto')->take(20)->get();

        $campaigns = Campaign::orderByDesc('created_at')->take(5)->get();

        // Financial analytics
        $aiCalls = Message::where('direction', 'in')->count();
        $anthropicCost = round($aiCalls * 0.0135, 2);

        $campaignIds = Campaign::pluck('id');
        $campaignMessagesDelivered = DB::table('campaign_contact')
            ->whereIn('campaign_id', $campaignIds)
            ->whereIn('status', ['delivered', 'read'])
            ->count();
        $outgoingMessagesDelivered = Message::where('direction', 'out')
            ->whereIn('status', ['delivered', 'read'])
            ->count();
        $whatsappCost = round(($campaignMessagesDelivered * 0.0394) + ($outgoingMessagesDelivered * 0.0088), 2);

        $totalCostsUsd = $anthropicCost + $whatsappCost;
        $totalCostsMxn = $totalCostsUsd * 20;

        // Pending donations needing review
        $pendingDonations = Donation::where('status', 'pending')->count();

        $financial = [
            'anthropic_cost' => $anthropicCost,
            'whatsapp_cost' => $whatsappCost,
            'total_costs_usd' => $totalCostsUsd,
            'total_costs_mxn' => $totalCostsMxn,
            'total_boletos' => $totalBoletos,
            'total_recaudado' => $totalRecaudado,
            'dias_restantes' => $diasRestantes,
            'pending_donations' => $pendingDonations,
        ];

        return view('admin.dashboard', compact('stats', 'recentContacts', 'campaigns', 'financial'));
    }

    /**
     * Donors list.
     */
    public function donadores(Request $request): View
    {
        $query = Contact::where('status', 'donador');

        if ($search = $request->query('search')) {
            $query->where(function ($q) use ($search): void {
                $q->where('nombre_completo', 'like', "%{$search}%")
                    ->orWhere('nombre', 'like', "%{$search}%")
                    ->orWhere('telefono', 'like', "%{$search}%");
            });
        }

        $donadores = $query->orderByDesc('boletos')->paginate(50);
        $totalBoletos = Contact::where('status', 'donador')->sum('boletos');
        $totalRecaudado = $totalBoletos * 3000;

        return view('admin.donadores', compact('donadores', 'totalBoletos', 'totalRecaudado'));
    }

    /**
     * Contacts list with filters.
     */
    public function contacts(Request $request): View
    {
        $query = Contact::query();

        $statuses = array_filter((array) $request->query('status', []));
        if (!empty($statuses)) {
            $query->whereIn('status', $statuses);
        }

        if ($search = $request->query('search')) {
            $query->where(function ($q) use ($search): void {
                $q->where('nombre_completo', 'like', "%{$search}%")
                    ->orWhere('nombre', 'like', "%{$search}%")
                    ->orWhere('telefono', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        $contacts = $query->orderByDesc('ultimo_contacto')->paginate(50);

        return view('admin.contacts', compact('contacts'));
    }

    /**
     * Donations/receipts management.
     */
    public function donations(Request $request): View
    {
        $query = Donation::with('contact');

        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        $donations = $query->orderByDesc('created_at')->paginate(50);
        $pendingCount = Donation::where('status', 'pending')->count();
        $verifiedCount = Donation::where('status', 'verified')->count();

        return view('admin.donations', compact('donations', 'pendingCount', 'verifiedCount'));
    }

    /**
     * Verify a donation manually.
     */
    /**
     * Stream the stored receipt image for a donation. Falls back to re-downloading
     * from Meta if the local copy is missing (e.g. older donations).
     */
    public function donationReceipt(int $id)
    {
        $donation = Donation::findOrFail($id);
        $mediaId = $donation->receipt_media_id;
        if (!$mediaId) {
            abort(404);
        }

        $path = "donation-receipts/{$mediaId}.jpg";
        $disk = \Illuminate\Support\Facades\Storage::disk('local');

        if (!$disk->exists($path)) {
            $whatsApp = app(\App\Services\WhatsAppService::class);
            $bytes = $whatsApp->downloadMedia($mediaId);
            if (!$bytes) {
                abort(404);
            }
            $disk->put($path, $bytes);
        }

        return response()->file($disk->path($path), ['Content-Type' => 'image/jpeg']);
    }

    public function verifyDonation(Request $request, int $id): RedirectResponse
    {
        $request->validate(['boletos' => 'required|integer|min:1']);

        $donation = Donation::findOrFail($id);
        $boletos = (int) $request->input('boletos');

        $donation->update([
            'boletos' => $boletos,
            'status' => 'verified',
            'verified_at' => now(),
            'verified_by' => Auth::id(),
        ]);

        $contact = $donation->contact;
        $contact->update([
            'status' => 'donador',
            'boletos' => $contact->boletos + $boletos,
        ]);

        $state = ConversationState::where('contact_id', $contact->id)->first();
        if ($state) {
            $state->update(['current_step' => 'confirmado']);
        }

        return back()->with('success', "Donacion verificada: {$boletos} boletos para {$contact->nombre_display}");
    }

    /**
     * Reject a donation.
     */
    public function rejectDonation(int $id): RedirectResponse
    {
        $donation = Donation::findOrFail($id);
        $donation->update([
            'status' => 'rejected',
            'verified_by' => Auth::id(),
        ]);

        return back()->with('success', 'Donacion rechazada');
    }

    /**
     * Conversation/chat view.
     */
    public function conversation(int $id): View
    {
        $contact = Contact::findOrFail($id);
        $messages = Message::where('contact_id', $id)->orderBy('created_at', 'asc')->get();
        $state = $contact->conversationState;
        $donations = Donation::where('contact_id', $id)->orderByDesc('created_at')->get();

        return view('admin.conversation', compact('contact', 'messages', 'state', 'donations'));
    }

    /**
     * Send manual message from admin.
     */
    public function sendMessage(Request $request, int $id): RedirectResponse
    {
        $request->validate(['message' => 'required|string|max:4096']);

        $contact = Contact::findOrFail($id);
        $this->conversation->sendManualMessage($contact, $request->input('message'));

        return back()->with('success', 'Mensaje enviado');
    }

    /**
     * Change contact status.
     */
    public function changeStatus(Request $request, int $id): RedirectResponse
    {
        $request->validate([
            'status' => 'required|in:nuevo,contactado,leido,interesado,datos_enviados,donador,no_interesado',
        ]);

        $contact = Contact::findOrFail($id);
        $contact->update(['status' => $request->input('status')]);

        return back()->with('success', 'Estado actualizado');
    }

    /**
     * CSV import form.
     */
    public function importForm(): View
    {
        return view('admin.import');
    }

    /**
     * Process CSV import.
     */
    public function import(Request $request): RedirectResponse
    {
        $request->validate(['file' => 'required|file|mimes:csv,txt|max:10240']);

        $file = $request->file('file');
        $handle = fopen($file->getRealPath(), 'r');
        if (!$handle) {
            return back()->withErrors(['file' => 'No se pudo abrir el archivo']);
        }

        $header = fgetcsv($handle);
        if (!$header) {
            fclose($handle);
            return back()->withErrors(['file' => 'El archivo esta vacio']);
        }

        $header = array_map(fn(string $h): string => strtolower(trim($h)), $header);

        $imported = 0;
        $skipped = 0;

        while (($row = fgetcsv($handle)) !== false) {
            $data = array_combine($header, array_pad($row, count($header), ''));
            if (!$data) {
                continue;
            }

            $telefono = preg_replace('/[^0-9]/', '', $data['telefono'] ?? '');
            if (strlen($telefono) < 10) {
                $skipped++;
                continue;
            }

            // Add Mexico prefix if needed
            if (strlen($telefono) === 10) {
                $telefono = '52' . $telefono;
            }

            if (Contact::where('telefono', $telefono)->exists()) {
                $skipped++;
                continue;
            }

            Contact::create([
                'telefono' => $telefono,
                'pais' => Contact::detectCountry($telefono),
                'nombre' => $data['nombre'] ?? null,
                'email' => $data['email'] ?? null,
                'status' => 'nuevo',
            ]);
            $imported++;
        }

        fclose($handle);

        return back()->with('success', "Importados: {$imported} contactos. Omitidos: {$skipped}");
    }

    /**
     * Campaigns list.
     */
    public function campaigns(): View
    {
        $campaigns = Campaign::orderByDesc('created_at')->paginate(25);
        return view('admin.campaigns', compact('campaigns'));
    }

    /**
     * Campaign create form.
     */
    public function campaignCreate(): View
    {
        $countries = Contact::select('pais', DB::raw('COUNT(*) as total'))
            ->whereNotNull('pais')
            ->groupBy('pais')
            ->orderByDesc('total')
            ->get();

        $contactCounts = [
            'nuevos' => Contact::where('status', 'nuevo')->count(),
            'todos' => Contact::count(),
        ];

        $campaignIds = Campaign::pluck('id');
        $neverSentCount = Contact::whereNotIn('id', DB::table('campaign_contact')
            ->whereIn('campaign_id', $campaignIds)
            ->distinct()
            ->pluck('contact_id'))
            ->count();

        return view('admin.campaign-create', compact('contactCounts', 'neverSentCount', 'countries'));
    }

    /**
     * Launch a campaign.
     */
    public function campaignLaunch(Request $request): RedirectResponse
    {
        $request->validate([
            'nombre' => 'required|string|max:255',
            'audiencia' => 'required|in:nuevos,todos,nunca_enviados',
            'pais' => 'nullable|string|max:10',
            'random_count' => 'nullable|integer|min:1|max:5000',
        ]);

        $audience = $request->input('audiencia');
        $pais = $request->input('pais');
        $campaignIds = Campaign::pluck('id');
        $randomCount = $request->input('random_count');

        $query = match ($audience) {
            'nuevos' => Contact::where('status', 'nuevo'),
            'nunca_enviados' => Contact::whereNotIn('id', DB::table('campaign_contact')
                ->whereIn('campaign_id', $campaignIds)
                ->distinct()
                ->pluck('contact_id')),
            'todos' => Contact::query(),
        };

        if ($pais) {
            $query->where('pais', $pais);
        }

        if ($randomCount) {
            $contactIds = $query->inRandomOrder()->limit($randomCount)->pluck('id');
        } else {
            $contactIds = $query->pluck('id');
        }

        if ($contactIds->isEmpty()) {
            return back()->withErrors(['audiencia' => 'No hay contactos para esta audiencia']);
        }

        $campaign = Campaign::create([
            'name' => $request->input('nombre'),
            'template_name' => 'rifa_boda',
            'status' => 'sending',
            'total_contacts' => $contactIds->count(),
        ]);

        $pivotData = $contactIds->mapWithKeys(fn(int $id): array => [
            $id => ['status' => 'pending'],
        ])->toArray();
        $campaign->contacts()->attach($pivotData);

        SendMassCampaign::dispatch($campaign);

        return redirect("/admin/campaign/{$campaign->id}")->with('success', 'Campana lanzada');
    }

    /**
     * Campaign detail.
     */
    public function campaignDetail(int $id): View
    {
        $campaign = Campaign::findOrFail($id);
        $contacts = $campaign->contacts()->paginate(50);

        $statusCounts = DB::table('campaign_contact')
            ->where('campaign_id', $id)
            ->select('status', DB::raw('COUNT(*) as cnt'))
            ->groupBy('status')
            ->pluck('cnt', 'status');

        $stats = [
            'sent' => ($statusCounts['sent'] ?? 0) + ($statusCounts['delivered'] ?? 0) + ($statusCounts['read'] ?? 0),
            'delivered' => ($statusCounts['delivered'] ?? 0) + ($statusCounts['read'] ?? 0),
            'read' => $statusCounts['read'] ?? 0,
            'failed' => $statusCounts['failed'] ?? 0,
        ];

        return view('admin.campaign-detail', compact('campaign', 'contacts', 'stats'));
    }

    /**
     * Export donors as CSV.
     */
    public function exportDonadores(): StreamedResponse
    {
        $fileName = 'donadores_rifa_boda_' . date('Y-m-d') . '.csv';

        return response()->streamDownload(function (): void {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['Nombre', 'Telefono', 'Boletos', 'Monto Total', 'Ultimo Contacto']);

            Contact::where('status', 'donador')
                ->orderByDesc('boletos')
                ->chunk(100, function ($contacts) use ($handle): void {
                    foreach ($contacts as $contact) {
                        fputcsv($handle, [
                            $contact->nombre_display,
                            $contact->telefono,
                            $contact->boletos,
                            '$' . number_format($contact->boletos * 3000, 0),
                            $contact->ultimo_contacto?->format('Y-m-d H:i'),
                        ]);
                    }
                });

            fclose($handle);
        }, $fileName, ['Content-Type' => 'text/csv']);
    }
}
