<?php

namespace App\Http\Controllers;

use App\Models\Campaign;
use App\Models\Contact;
use App\Models\Message;
use App\Models\MessageTemplate;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Stripe\StripeClient;

class PlatformController extends Controller
{
    public function onboarding(): View
    {
        $organization = app('currentOrganization');
        $connection = $organization->whatsappConnections()->latest()->first();
        $billing = $organization->billingProfile;
        $flow = $organization->flows()->where('is_active', true)->first();

        return view('platform.onboarding', compact('organization', 'connection', 'billing', 'flow'));
    }

    public function integrations(): View
    {
        $connections = app('currentOrganization')->whatsappConnections()->latest()->get();

        return view('platform.integrations', compact('connections'));
    }

    public function saveWhatsApp(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => 'required|string|max:100',
            'waba_id' => 'required|string|max:100',
            'phone_number_id' => 'required|string|max:100',
            'display_phone' => 'nullable|string|max:30',
            'access_token' => 'required|string|min:20',
        ]);

        $response = Http::withToken($data['access_token'])->timeout(15)
            ->get('https://graph.facebook.com/'.config('services.whatsapp.api_version', 'v21.0').'/'.$data['phone_number_id'], [
                'fields' => 'id,display_phone_number,verified_name',
            ]);

        if (! $response->successful()) {
            return back()->withInput()->withErrors(['access_token' => 'Meta rechazó estas credenciales. Verifica el token y el Phone Number ID.']);
        }

        app('currentOrganization')->whatsappConnections()->updateOrCreate(
            ['phone_number_id' => $data['phone_number_id']],
            [...$data, 'display_phone' => $response->json('display_phone_number') ?: $data['display_phone'], 'verify_token' => Str::random(48), 'status' => 'connected', 'connected_at' => now()],
        );

        return back()->with('success', 'Número de WhatsApp conectado y validado con Meta.');
    }

    public function templates(): View
    {
        $templates = app('currentOrganization')->templates()->latest()->get();
        $connections = app('currentOrganization')->whatsappConnections()->where('status', 'connected')->get();

        return view('platform.templates', compact('templates', 'connections'));
    }

    public function saveTemplate(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'whatsapp_connection_id' => 'nullable|integer',
            'name' => ['required', 'string', 'max:100', 'regex:/^[a-z0-9_]+$/'],
            'language' => 'required|string|max:10',
            'category' => 'required|in:MARKETING,UTILITY,AUTHENTICATION',
            'body' => 'required|string|max:1024',
        ]);

        if (! empty($data['whatsapp_connection_id'])) {
            app('currentOrganization')->whatsappConnections()->findOrFail($data['whatsapp_connection_id']);
        }

        app('currentOrganization')->templates()->create([...$data, 'status' => 'draft']);

        return back()->with('success', 'Plantilla guardada como borrador. Ya puedes enviarla a revisión de Meta.');
    }

    public function publishTemplate(MessageTemplate $template): RedirectResponse
    {
        abort_unless($template->organization_id === app('currentOrganization')->id, 404);
        $connection = $template->connection;
        if (! $connection || $connection->status !== 'connected') {
            return back()->withErrors(['template' => 'Conecta un número de WhatsApp antes de publicar.']);
        }

        $response = Http::withToken($connection->access_token)->timeout(20)
            ->post('https://graph.facebook.com/'.config('services.whatsapp.api_version', 'v21.0').'/'.$connection->waba_id.'/message_templates', [
                'name' => $template->name,
                'language' => $template->language,
                'category' => $template->category,
                'components' => [['type' => 'BODY', 'text' => $template->body]],
            ]);

        if (! $response->successful()) {
            $template->update(['status' => 'rejected', 'rejection_reason' => $response->json('error.message', 'Meta rechazó la solicitud.')]);

            return back()->withErrors(['template' => $template->rejection_reason]);
        }

        $template->update(['status' => strtolower($response->json('status', 'pending')), 'meta_template_id' => $response->json('id'), 'rejection_reason' => null]);

        return back()->with('success', 'Plantilla enviada a Meta para revisión.');
    }

    public function flows(): View
    {
        $flows = app('currentOrganization')->flows()->latest()->get();

        return view('platform.flows', compact('flows'));
    }

    public function saveFlow(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => 'required|string|max:120',
            'description' => 'nullable|string|max:500',
            'system_prompt' => 'required|string|max:12000',
            'welcome_message' => 'required|string|max:1000',
            'objective' => 'required|string|max:1000',
            'fallback_message' => 'required|string|max:1000',
            'is_active' => 'nullable|boolean',
        ]);

        $active = (bool) ($data['is_active'] ?? false);
        DB::transaction(function () use ($data, $active): void {
            if ($active) {
                app('currentOrganization')->flows()->update(['is_active' => false]);
            }
            app('currentOrganization')->flows()->create([
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
                'system_prompt' => $data['system_prompt'],
                'nodes' => [
                    ['id' => 'start', 'type' => 'message', 'label' => 'Bienvenida', 'content' => $data['welcome_message'], 'next' => 'ai'],
                    ['id' => 'ai', 'type' => 'ai', 'label' => 'Agente IA', 'objective' => $data['objective'], 'fallback' => $data['fallback_message']],
                ],
                'is_active' => $active,
            ]);
        });

        return back()->with('success', 'Flujo creado'.($active ? ' y activado.' : '.'));
    }

    public function billing(): View
    {
        $billing = app('currentOrganization')->billingProfile;

        return view('platform.billing', compact('billing'));
    }

    public function startBillingSetup(): RedirectResponse
    {
        abort_if(blank(config('services.stripe.secret')), 503, 'Stripe todavía no está configurado en el servidor.');
        $organization = app('currentOrganization');
        $profile = $organization->billingProfile()->firstOrCreate([], ['status' => 'incomplete']);
        $stripe = new StripeClient(config('services.stripe.secret'));

        if (! $profile->stripe_customer_id) {
            $customer = $stripe->customers->create(['name' => $organization->name, 'email' => auth()->user()->email, 'metadata' => ['organization_id' => (string) $organization->id]]);
            $profile->update(['stripe_customer_id' => $customer->id]);
        }

        $session = $stripe->checkout->sessions->create([
            'customer' => $profile->stripe_customer_id,
            'mode' => 'setup',
            'payment_method_types' => ['card'],
            'success_url' => route('billing.complete').'?session_id={CHECKOUT_SESSION_ID}',
            'cancel_url' => route('billing'),
            'metadata' => ['organization_id' => (string) $organization->id],
        ]);

        return redirect()->away($session->url);
    }

    public function completeBilling(Request $request): RedirectResponse
    {
        $request->validate(['session_id' => 'required|string']);
        $profile = app('currentOrganization')->billingProfile;
        abort_unless($profile?->stripe_customer_id, 404);
        $stripe = new StripeClient(config('services.stripe.secret'));
        $session = $stripe->checkout->sessions->retrieve($request->string('session_id')->toString(), ['expand' => ['setup_intent.payment_method']]);
        abort_unless($session->customer === $profile->stripe_customer_id && $session->status === 'complete', 403);
        $method = $session->setup_intent->payment_method;
        $profile->update([
            'stripe_payment_method_id' => $method->id,
            'card_brand' => $method->card->brand,
            'card_last_four' => $method->card->last4,
            'card_expiry' => sprintf('%02d/%d', $method->card->exp_month, $method->card->exp_year),
            'status' => 'active',
        ]);

        return redirect()->route('billing')->with('success', 'Método de pago guardado de forma segura en Stripe.');
    }

    public function clients(): View
    {
        abort_unless(auth()->user()->isSuperAdmin(), 403);
        $clients = Organization::with('users')->latest()->get();

        return view('platform.clients', compact('clients'));
    }

    public function superAdminOverview(): View
    {
        abort_unless(auth()->user()->isSuperAdmin(), 403);

        $clients = Organization::with(['users:id,organization_id,email', 'whatsappConnections:id,organization_id,display_phone,status,connected_at'])
            ->orderBy('name')->get();
        $messages = Message::withoutGlobalScopes()->latest()->take(30)->get();
        $contacts = Contact::withoutGlobalScopes()->whereIn('id', $messages->pluck('contact_id')->filter())->get()->keyBy('id');
        $organizations = $clients->keyBy('id');
        $campaigns = Campaign::withoutGlobalScopes()->latest()->take(20)->get();

        $stats = [
            'clients' => $clients->where('id', '!=', app('currentOrganization')->id)->count(),
            'numbers' => $clients->flatMap->whatsappConnections->where('status', 'connected')->count(),
            'outbound_today' => Message::withoutGlobalScopes()->where('direction', 'out')->whereDate('created_at', today())->count(),
            'campaigns_active' => Campaign::withoutGlobalScopes()->where('status', 'sending')->count(),
        ];

        return view('platform.overview', compact('clients', 'messages', 'contacts', 'organizations', 'campaigns', 'stats'));
    }

    public function createClient(Request $request): RedirectResponse
    {
        abort_unless(auth()->user()->isSuperAdmin(), 403);
        $data = $request->validate(['company' => 'required|string|max:150', 'name' => 'required|string|max:120', 'email' => 'required|email|unique:users,email', 'password' => 'required|string|min:12']);
        DB::transaction(function () use ($data): void {
            $org = Organization::create(['name' => $data['company'], 'slug' => Str::slug($data['company']).'-'.Str::lower(Str::random(5)), 'brand_name' => $data['company']]);
            User::create(['organization_id' => $org->id, 'name' => $data['name'], 'email' => $data['email'], 'password' => Hash::make($data['password']), 'role' => 'owner']);
        });

        return back()->with('success', 'Cliente creado. Ya puede iniciar sesión; no existe registro público.');
    }
}
