<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\AccountInvitation;
use App\Models\Contact;
use App\Models\Integration;
use App\Models\Lead;
use App\Models\Pipeline;
use App\Models\Tag;
use App\Models\User;
use App\Services\AccountProvisioner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class Phase4Test extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Account $account;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::create(['name' => 'Owner', 'email' => 'o@test.com', 'password' => bcrypt('password')]);
        $this->account = app(AccountProvisioner::class)->createForUser($this->user);
        $this->user->refresh();
    }

    public function test_importa_contactos_del_wacrm_con_dedup(): void
    {
        Integration::create([
            'account_id' => $this->account->id,
            'wacrm_url' => 'http://localhost:8000',
            'wacrm_api_key' => 'k',
            'is_active' => true,
        ]);

        // Ya existe uno de los contactos remotos.
        Contact::create(['account_id' => $this->account->id, 'name' => 'Ana', 'phone' => '584125550001']);

        Http::fake([
            'localhost:8000/api/v1/contacts*' => Http::response([
                'data' => [
                    ['id' => 'w1', 'name' => 'Ana', 'phone' => '+58 412 555 0001', 'email' => null],
                    ['id' => 'w2', 'name' => 'Beto', 'phone' => '584125550002', 'email' => 'beto@test.com'],
                    ['id' => 'w3', 'name' => null, 'phone' => '584125550003', 'email' => null],
                    ['id' => 'w4', 'name' => 'Sin Tel', 'phone' => null, 'email' => null],
                ],
                'next_page_url' => null,
            ]),
        ]);

        $this->actingAs($this->user)
            ->post(route('contacts.import-wacrm'))
            ->assertRedirect()
            ->assertSessionHas('success', fn ($msg) => str_contains($msg, '2 contactos nuevos'));

        $this->assertSame(3, Contact::forAccount($this->account->id)->count());
        // La existente ganó el vínculo con el wacrm.
        $this->assertSame('w1', Contact::forAccount($this->account->id)->where('phone_normalized', '584125550001')->value('wacrm_contact_id'));
        // El sin nombre usa el teléfono como nombre.
        $this->assertSame('584125550003', Contact::forAccount($this->account->id)->where('phone_normalized', '584125550003')->value('name'));
    }

    public function test_flujo_de_invitacion_completo(): void
    {
        $this->actingAs($this->user)
            ->post(route('team.invite'), ['role' => 'agent', 'label' => 'Ventas'])
            ->assertRedirect();

        $inviteUrl = session('invite_url');
        $token = basename(parse_url($inviteUrl, PHP_URL_PATH));

        auth()->logout();

        $this->get("/invite/{$token}")->assertOk();

        $this->post("/invite/{$token}", [
            'name' => 'Agente Nuevo',
            'email' => 'agente@test.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])->assertRedirect(route('dashboard'));

        $agent = User::where('email', 'agente@test.com')->first();
        $this->assertSame($this->account->id, $agent->account_id);
        $this->assertSame('agent', $agent->account_role);
        $this->assertNotNull(AccountInvitation::first()->accepted_at);

        // Single-use: el link ya no sirve.
        auth()->logout();
        $this->post("/invite/{$token}", [
            'name' => 'Otro',
            'email' => 'otro@test.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])->assertRedirect(route('login'));
    }

    public function test_expulsar_miembro_le_da_cuenta_propia_con_pipeline(): void
    {
        $agent = User::create([
            'name' => 'Agente',
            'email' => 'agente@test.com',
            'password' => bcrypt('password'),
            'account_id' => $this->account->id,
            'account_role' => 'agent',
        ]);

        $this->actingAs($this->user)
            ->delete(route('team.members.remove', $agent->id))
            ->assertRedirect()
            ->assertSessionHas('success');

        $agent->refresh();
        $this->assertNotSame($this->account->id, $agent->account_id);
        $this->assertSame('owner', $agent->account_role);
        // Su cuenta nueva viene lista con pipeline y etapas terminales.
        $pipeline = Pipeline::forAccount($agent->account_id)->first();
        $this->assertNotNull($pipeline->wonStage());
    }

    public function test_etiquetas_en_leads_con_aislamiento(): void
    {
        $pipeline = Pipeline::forAccount($this->account->id)->first();
        $stage = $pipeline->stages()->first();

        $lead = Lead::create([
            'account_id' => $this->account->id,
            'pipeline_id' => $pipeline->id,
            'stage_id' => $stage->id,
            'title' => 'Deal',
        ]);

        $this->actingAs($this->user)
            ->post(route('tags.store'), ['name' => 'VIP'])
            ->assertRedirect();

        $tag = Tag::forAccount($this->account->id)->first();

        // Etiqueta de OTRA cuenta: no debe poder asignarse.
        $otro = User::create(['name' => 'Otro', 'email' => 'x@test.com', 'password' => bcrypt('password')]);
        $otraCuenta = app(AccountProvisioner::class)->createForUser($otro);
        $tagAjena = Tag::create(['account_id' => $otraCuenta->id, 'name' => 'Ajena']);

        $this->actingAs($this->user)
            ->patch(route('leads.tags', $lead->id), ['tag_ids' => [$tag->id, $tagAjena->id]])
            ->assertRedirect();

        $lead->refresh();
        $this->assertTrue($lead->tags->contains($tag));
        $this->assertFalse($lead->tags->contains($tagAjena)); // filtrada silenciosamente
    }
}
