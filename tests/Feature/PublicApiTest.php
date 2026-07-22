<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\ApiKey;
use App\Models\AppNotification;
use App\Models\Contact;
use App\Models\Integration;
use App\Models\Lead;
use App\Models\Pipeline;
use App\Models\PipelineStage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * API pública /api/v1 (consumida por meta_ads) + atribución de
 * anuncios: referral del wacrm → leads.source_ref.
 */
class PublicApiTest extends TestCase
{
    use RefreshDatabase;

    private function makeAccount(): array
    {
        $user = User::create(['name' => 'Vendedor', 'email' => 'v@test.com', 'password' => bcrypt('password')]);
        $account = Account::create(['name' => 'Empresa', 'owner_user_id' => $user->id]);
        $user->update(['account_id' => $account->id, 'account_role' => 'owner']);

        $pipeline = Pipeline::create(['account_id' => $account->id, 'name' => 'Ventas', 'is_default' => true]);
        $stages = collect([
            ['name' => 'Nuevo', 'stage_type' => 'open'],
            ['name' => 'Negociación', 'stage_type' => 'open'],
            ['name' => 'Ganado', 'stage_type' => 'won'],
            ['name' => 'Perdido', 'stage_type' => 'lost'],
        ])->map(fn ($s, $i) => PipelineStage::create(['pipeline_id' => $pipeline->id, 'position' => $i, ...$s]));

        return [$user->fresh(), $account, $pipeline, $stages];
    }

    public function test_autenticacion_y_scopes_de_la_api(): void
    {
        [$user, $account] = $this->makeAccount();

        // Sin token → 401.
        $this->getJson('/api/v1/me')->assertUnauthorized();

        [, $plaintext] = ApiKey::issue($account->id, $user->id, 'meta_ads', ['leads:read']);

        $this->withToken($plaintext)->getJson('/api/v1/me')
            ->assertOk()
            ->assertJsonPath('account.name', 'Empresa')
            ->assertJsonPath('key.scopes', ['leads:read']);

        // leads:read alcanza para GET pero no para POST.
        $this->withToken($plaintext)->getJson('/api/v1/leads')->assertOk();
        $this->withToken($plaintext)->postJson('/api/v1/leads', ['name' => 'X'])->assertForbidden();

        // Clave revocada deja de funcionar.
        ApiKey::first()->update(['revoked_at' => now()]);
        $this->withToken($plaintext)->getJson('/api/v1/me')->assertUnauthorized();
    }

    public function test_get_leads_filtra_por_ad_id_y_expone_value_cents(): void
    {
        [$user, $account, $pipeline, $stages] = $this->makeAccount();
        [, $plaintext] = ApiKey::issue($account->id, $user->id, 'meta_ads', ['leads:read']);

        $atribuido = Lead::create([
            'account_id' => $account->id,
            'pipeline_id' => $pipeline->id,
            'stage_id' => $stages[0]->id,
            'title' => 'Del anuncio',
            'value' => 150.50,
            'source' => 'whatsapp',
            'source_ref' => 'AD_123',
        ]);
        $atribuido->moveToStage($stages[2]); // won

        Lead::create([
            'account_id' => $account->id,
            'pipeline_id' => $pipeline->id,
            'stage_id' => $stages[0]->id,
            'title' => 'Orgánico',
        ]);

        $response = $this->withToken($plaintext)->getJson('/api/v1/leads?ad_id=AD_123')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.status', 'won')
            ->assertJsonPath('data.0.value_cents', 15050)
            ->assertJsonPath('data.0.source_ref', 'AD_123');

        // Sin filtro devuelve ambos.
        $this->withToken($plaintext)->getJson('/api/v1/leads')->assertJsonCount(2, 'data');
    }

    public function test_post_leads_crea_lead_de_lead_ad_con_idempotencia(): void
    {
        [$user, $account, $pipeline, $stages] = $this->makeAccount();
        [, $plaintext] = ApiKey::issue($account->id, $user->id, 'meta_ads', ['leads:write']);

        $payload = [
            'name' => 'Pedro Interesado',
            'phone' => '+58 412 555 7777',
            'email' => 'pedro@test.com',
            'source' => 'lead_ad',
            'source_ref' => 'AD_999',
            'custom_fields' => ['city' => 'Caracas'],
            'meta_leadgen_id' => 'LG_1',
        ];

        $this->withToken($plaintext)->postJson('/api/v1/leads', $payload)
            ->assertCreated()
            ->assertJsonPath('data.source_ref', 'AD_999');

        $lead = Lead::forAccount($account->id)->first();
        $this->assertSame('lead_ad', $lead->source);
        $this->assertSame($stages[0]->id, $lead->stage_id); // primera etapa open del default
        $this->assertSame('LG_1', $lead->meta_leadgen_id);

        // Contacto dedupeado por teléfono normalizado.
        $this->assertSame('584125557777', $lead->contact->phone_normalized);

        // Los campos extra quedaron como nota.
        $this->assertStringContainsString('city: Caracas', $lead->notes()->first()->text);

        // El owner fue notificado.
        $this->assertTrue(
            AppNotification::forAccount($account->id)
                ->where('user_id', $user->id)
                ->where('type', 'lead_created_api')
                ->exists(),
        );

        // Reenvío del mismo leadgen → no duplica.
        $this->withToken($plaintext)->postJson('/api/v1/leads', $payload)
            ->assertOk()
            ->assertJsonPath('duplicated', true);

        $this->assertSame(1, Lead::forAccount($account->id)->count());
    }

    public function test_referral_del_wacrm_se_guarda_como_source_ref(): void
    {
        [, $account, , $stages] = $this->makeAccount();

        Integration::create([
            'account_id' => $account->id,
            'wacrm_url' => 'http://localhost:8000',
            'wacrm_api_key' => 'k',
            'webhook_secret' => 'whsec_s',
            'is_active' => true,
        ]);

        $post = function (array $message) use ($account) {
            $body = json_encode([
                'event' => 'message.received',
                'data' => [
                    'conversation_id' => 'conv-ad-1',
                    'contact' => ['phone' => '584125550001', 'name' => 'Ana'],
                    'message' => $message,
                ],
            ]);

            return $this->call('POST', "/webhooks/wacrm/{$account->id}", [], [], [], [
                'HTTP_X-Webhook-Signature' => 'sha256='.hash_hmac('sha256', $body, 'whsec_s'),
                'CONTENT_TYPE' => 'application/json',
            ], $body);
        };

        $post([
            'text' => 'Vi su anuncio',
            'type' => 'text',
            'wamid' => 'wamid.A1',
            'referral' => ['source_id' => 'AD_CTWA', 'source_type' => 'ad', 'source_url' => 'https://fb.me/x'],
        ])->assertOk();

        $lead = Lead::forAccount($account->id)->first();
        $this->assertSame('AD_CTWA', $lead->source_ref);
        $this->assertSame('https://fb.me/x', $lead->source_url);
        $this->assertSame('AD_CTWA', $lead->events()->where('event_type', 'created')->first()->payload['ad_id']);

        // Otro referral posterior NO pisa la atribución original.
        $post([
            'text' => 'Toqué otro anuncio',
            'type' => 'text',
            'wamid' => 'wamid.A2',
            'referral' => ['source_id' => 'AD_OTRO', 'source_type' => 'ad'],
        ])->assertOk();

        $this->assertSame('AD_CTWA', $lead->fresh()->source_ref);
    }

    public function test_get_contacts_filtra_por_tag_id(): void
    {
        [$user, $account] = $this->makeAccount();
        [, $plaintext] = ApiKey::issue($account->id, $user->id, 'meta_ads', ['contacts:read']);

        $tag = \App\Models\Tag::create(['account_id' => $account->id, 'name' => 'VIP']);

        $vip = Contact::create(['account_id' => $account->id, 'name' => 'Ana', 'phone' => '584125550001', 'email' => 'ana@test.com']);
        $vip->tags()->attach($tag->id);

        Contact::create(['account_id' => $account->id, 'name' => 'Beto', 'phone' => '584125550002']);

        $this->withToken($plaintext)->getJson("/api/v1/contacts?tag_id={$tag->id}")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Ana')
            ->assertJsonPath('data.0.email', 'ana@test.com');

        // Sin filtro devuelve ambos.
        $this->withToken($plaintext)->getJson('/api/v1/contacts')->assertJsonCount(2, 'data');

        // El scope contacts:read no alcanza para leads.
        $this->withToken($plaintext)->getJson('/api/v1/leads')->assertForbidden();
    }

    public function test_crud_de_api_keys_desde_la_ui(): void
    {
        [$user] = $this->makeAccount();

        $this->actingAs($user)
            ->post(route('team.api-keys.store'), [
                'name' => 'Meta Ads Manager',
                'scopes' => ['leads:read', 'leads:write'],
            ])
            ->assertRedirect()
            ->assertSessionHas('api_key_plaintext', fn ($k) => str_starts_with($k, 'komo_live_'));

        $key = ApiKey::first();
        $this->assertSame(['leads:read', 'leads:write'], $key->scopes);

        // Scope inventado se rechaza.
        $this->actingAs($user)
            ->post(route('team.api-keys.store'), ['name' => 'x', 'scopes' => ['admin:all']])
            ->assertSessionHasErrors('scopes.0');

        $this->actingAs($user)
            ->delete(route('team.api-keys.revoke', $key))
            ->assertRedirect();

        $this->assertNotNull($key->fresh()->revoked_at);
    }
}
