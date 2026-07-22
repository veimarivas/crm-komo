<?php

namespace Tests\Feature;

use App\Models\ApiKey;
use App\Models\Integration;
use App\Models\Pipeline;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Provisión del ecosistema (Fase 3 del Komo Hub): POST /api/v1/provision
 * crea usuario+cuenta con pipeline, emite API key y cablea la integración
 * con el wacrm en una llamada.
 */
class ProvisionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.hub.provision_secret' => 'prov-secret']);
    }

    private function provision(array $payload, ?string $secret = 'prov-secret')
    {
        $body = json_encode($payload);

        return $this->call('POST', '/api/v1/provision', [], [], [], [
            'HTTP_X-Provision-Signature' => 'sha256='.hash_hmac('sha256', $body, $secret ?? 'x'),
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
        ], $body);
    }

    public function test_firma_invalida_se_rechaza(): void
    {
        $this->provision(['name' => 'X', 'email' => 'hub@test.com'], 'otro')->assertUnauthorized();

        config(['services.hub.provision_secret' => null]);
        $this->provision(['name' => 'X', 'email' => 'hub@test.com'])->assertStatus(503);

        $this->assertSame(0, User::count());
    }

    public function test_provisiona_cuenta_con_pipeline_key_e_integracion_wacrm(): void
    {
        $response = $this->provision([
            'name' => 'Empresa Hub',
            'email' => 'hub@test.com',
            'api_key' => ['name' => 'Komo Hub', 'scopes' => ['leads:read', 'leads:write', 'contacts:read']],
            'wacrm_integration' => [
                'url' => 'http://localhost:8000/',
                'api_key' => 'wacrm_live_emitida_por_el_hub',
                'webhook_secret' => 'whsec_generado_por_el_hub',
            ],
        ]);

        $response->assertCreated()->assertJsonPath('user_created', true);

        $user = User::where('email', 'hub@test.com')->first();
        $this->assertSame('owner', $user->account_role);

        // AccountProvisioner sembró el pipeline por defecto.
        $pipeline = Pipeline::forAccount($user->account_id)->where('is_default', true)->first();
        $this->assertNotNull($pipeline);
        $this->assertSame(5, $pipeline->stages()->count());

        // La key devuelta funciona contra la API.
        $plaintext = $response->json('api_key');
        $this->assertStringStartsWith('komo_live_', $plaintext);
        $this->withToken($plaintext)->getJson('/api/v1/leads')->assertOk();

        // Integración con el wacrm cableada (URL sin slash final, secretos cifrados).
        $integration = Integration::forAccount($user->account_id)->first();
        $this->assertSame('http://localhost:8000', $integration->wacrm_url);
        $this->assertSame('wacrm_live_emitida_por_el_hub', $integration->wacrm_api_key);
        $this->assertSame('whsec_generado_por_el_hub', $integration->webhook_secret);
        $this->assertTrue($integration->is_active);
    }

    public function test_idempotente_por_email(): void
    {
        $payload = [
            'name' => 'Empresa Hub',
            'email' => 'hub@test.com',
            'api_key' => ['name' => 'Komo Hub', 'scopes' => ['leads:read']],
            'wacrm_integration' => [
                'url' => 'http://localhost:8000',
                'api_key' => 'key_v1',
                'webhook_secret' => 'whsec_v1',
            ],
        ];

        $this->provision($payload)->assertCreated();

        $payload['wacrm_integration']['api_key'] = 'key_v2';
        $this->provision($payload)->assertOk()->assertJsonPath('user_created', false);

        $this->assertSame(1, User::count());
        $this->assertSame(1, Pipeline::count());
        $this->assertSame(2, ApiKey::count());
        $this->assertSame(1, Integration::count()); // una por cuenta, se actualiza
        $this->assertSame('key_v2', Integration::first()->wacrm_api_key);
    }
}
