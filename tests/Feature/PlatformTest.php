<?php

namespace Tests\Feature;

use App\Models\Contact;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlatformTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_registration_does_not_exist(): void
    {
        $this->get('/register')->assertNotFound();
        $this->get('/login')->assertOk()->assertSee('Buy Overcloud');
    }

    public function test_an_authorized_client_can_use_the_private_platform(): void
    {
        $org = Organization::create(['name' => 'Cliente Uno', 'slug' => 'cliente-uno']);
        $user = User::factory()->create(['organization_id' => $org->id, 'role' => 'owner']);

        $this->actingAs($user)->get('/admin/onboarding')->assertOk()->assertSee('Todo listo para despegar');
        $this->actingAs($user)->post('/admin/flows', [
            'name' => 'Ventas',
            'system_prompt' => 'Responde con claridad.',
            'welcome_message' => 'Hola, ¿cómo podemos ayudarte?',
            'objective' => 'Calificar al prospecto.',
            'fallback_message' => 'Te conecto con el equipo.',
            'is_active' => '1',
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('conversation_flows', ['organization_id' => $org->id, 'name' => 'Ventas', 'is_active' => true]);
    }

    public function test_all_platform_pages_render_for_an_authorized_client(): void
    {
        $org = Organization::create(['name' => 'Render Client', 'slug' => 'render-client']);
        $user = User::factory()->create(['organization_id' => $org->id]);

        foreach (['/admin', '/admin/onboarding', '/admin/integrations', '/admin/templates', '/admin/flows', '/admin/billing', '/admin/campaign/create'] as $url) {
            $this->actingAs($user)->get($url)->assertOk();
        }
    }

    public function test_tenant_scope_hides_other_clients_contacts(): void
    {
        $one = Organization::create(['name' => 'Uno', 'slug' => 'uno']);
        $two = Organization::create(['name' => 'Dos', 'slug' => 'dos']);
        $user = User::factory()->create(['organization_id' => $one->id]);
        Contact::withoutGlobalScopes()->create(['organization_id' => $one->id, 'telefono' => '521111111111', 'nombre' => 'Visible']);
        Contact::withoutGlobalScopes()->create(['organization_id' => $two->id, 'telefono' => '522222222222', 'nombre' => 'Secreto']);

        $this->actingAs($user)->get('/admin/contacts')->assertOk()->assertSee('Visible')->assertDontSee('Secreto');
    }

    public function test_only_super_admin_can_create_clients(): void
    {
        $org = Organization::where('slug', 'buy-overcloud')->firstOrFail();
        $owner = User::factory()->create(['organization_id' => $org->id, 'role' => 'owner']);
        $admin = User::factory()->create(['organization_id' => $org->id, 'role' => 'super_admin']);
        $payload = ['company' => 'Nuevo Cliente', 'name' => 'Ana', 'email' => 'ana@example.com', 'password' => 'Temporal-1234'];

        $this->actingAs($owner)->post('/admin/clients', $payload)->assertForbidden();
        $this->actingAs($admin)->post('/admin/clients', $payload)->assertSessionHasNoErrors();
        $this->assertDatabaseHas('users', ['email' => 'ana@example.com', 'role' => 'owner']);
    }
}
