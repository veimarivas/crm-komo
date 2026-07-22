<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Pipeline;
use App\Models\User;
use App\Services\AccountProvisioner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Fase 7 del Komo Hub: /provision acepta account_id + account_role para invitados. */
class ProvisionMemberTest extends TestCase
{
    use RefreshDatabase;

    private function provision(array $payload)
    {
        $body = json_encode($payload);

        return $this->call('POST', '/api/v1/provision', [], [], [], [
            'HTTP_X-Provision-Signature' => 'sha256='.hash_hmac('sha256', $body, 'prov-secret'),
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
        ], $body);
    }

    public function test_provisiona_miembro_en_cuenta_existente_sin_crear_pipeline(): void
    {
        config(['services.hub.provision_secret' => 'prov-secret']);

        // Ya existe una cuenta con pipeline (el owner).
        $owner = User::factory()->create();
        app(AccountProvisioner::class)->createForUser($owner);
        $ownerAccount = Account::find($owner->refresh()->account_id);
        $pipelinesAntes = Pipeline::count();

        $this->provision([
            'name' => 'Nueva Vendedora',
            'email' => 'venta@test.com',
            'account_id' => $ownerAccount->id,
            'account_role' => 'agent',
        ])->assertCreated()->assertJsonPath('account_id', $ownerAccount->id);

        $invitee = User::where('email', 'venta@test.com')->first();
        $this->assertSame($ownerAccount->id, $invitee->account_id);
        $this->assertSame('agent', $invitee->account_role);
        // No creó pipeline nuevo — se unió al de la cuenta existente.
        $this->assertSame($pipelinesAntes, Pipeline::count());
    }
}
