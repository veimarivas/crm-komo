<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Contact;
use App\Models\Integration;
use App\Models\Lead;
use App\Models\Pipeline;
use App\Models\PipelineStage;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class KomoPhase1Test extends TestCase
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

    public function test_el_registro_crea_cuenta_y_pipeline_por_defecto(): void
    {
        $this->post('/register', [
            'name' => 'Nuevo Usuario',
            'email' => 'nuevo@test.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])->assertRedirect('/dashboard');

        $user = User::where('email', 'nuevo@test.com')->first();
        $this->assertSame('owner', $user->account_role);

        $pipeline = Pipeline::forAccount($user->account_id)->where('is_default', true)->first();
        $this->assertNotNull($pipeline);
        $this->assertSame(5, $pipeline->stages()->count());
        $this->assertNotNull($pipeline->wonStage());
        $this->assertNotNull($pipeline->lostStage());
    }

    public function test_ciclo_de_vida_del_lead_estado_derivado_de_la_etapa(): void
    {
        [$user, $account, $pipeline, $stages] = $this->makeAccount();

        $lead = Lead::create([
            'account_id' => $account->id,
            'pipeline_id' => $pipeline->id,
            'stage_id' => $stages[0]->id,
            'title' => 'Licencia anual',
            'value' => 1500,
        ]);
        $lead->recordEvent('created', $user);

        // Avanza a Negociación → sigue abierto.
        $lead->moveToStage($stages[1], $user);
        $this->assertSame(Lead::STATUS_OPEN, $lead->fresh()->status);

        // A Ganado → estado won + closed_at + eventos won y stage_changed.
        $lead->moveToStage($stages[2], $user);
        $lead->refresh();
        $this->assertSame(Lead::STATUS_WON, $lead->status);
        $this->assertNotNull($lead->closed_at);
        $this->assertTrue($lead->events()->where('event_type', 'won')->exists());

        // Reabrir (volver a etapa open) → reopened + closed_at null.
        $lead->moveToStage($stages[0], $user);
        $lead->refresh();
        $this->assertSame(Lead::STATUS_OPEN, $lead->status);
        $this->assertNull($lead->closed_at);
        $this->assertTrue($lead->events()->where('event_type', 'reopened')->exists());

        // Etapa de otro pipeline → rechazada.
        $otroPipeline = Pipeline::create(['account_id' => $account->id, 'name' => 'Otro']);
        $etapaAjena = PipelineStage::create(['pipeline_id' => $otroPipeline->id, 'name' => 'X', 'position' => 0]);
        $this->expectException(\InvalidArgumentException::class);
        $lead->moveToStage($etapaAjena);
    }

    public function test_tareas_completar_registra_evento_en_el_lead(): void
    {
        [$user, $account, $pipeline, $stages] = $this->makeAccount();

        $lead = Lead::create([
            'account_id' => $account->id,
            'pipeline_id' => $pipeline->id,
            'stage_id' => $stages[0]->id,
            'title' => 'Demo',
        ]);

        $task = Task::create([
            'account_id' => $account->id,
            'lead_id' => $lead->id,
            'assigned_to' => $user->id,
            'created_by' => $user->id,
            'task_type' => 'call',
            'text' => 'Llamar para agendar demo',
            'due_at' => now()->subHour(), // vencida
        ]);

        $this->assertTrue($task->isOverdue());
        $this->assertTrue($lead->hasPendingTask());
        $this->assertSame(1, Task::forAccount($account->id)->overdue()->count());

        $task->complete('Cliente confirmó para el martes', $user);

        $this->assertFalse($task->fresh()->isOverdue());
        $this->assertFalse($lead->hasPendingTask());
        $this->assertTrue($lead->events()->where('event_type', 'task_completed')->exists());
    }

    public function test_webhook_del_wacrm_crea_lead_desde_whatsapp(): void
    {
        [, $account, $pipeline, $stages] = $this->makeAccount();

        Integration::create([
            'account_id' => $account->id,
            'wacrm_url' => 'http://localhost:8000',
            'wacrm_api_key' => 'wacrm_live_test',
            'webhook_secret' => 'whsec_secreto',
            'is_active' => true,
        ]);

        $payload = json_encode([
            'event' => 'message.received',
            'data' => [
                'conversation_id' => 'conv-uuid-1',
                'contact' => ['id' => 'wacrm-contact-1', 'phone' => '+58 412 555 0001', 'name' => 'Ana Cliente'],
                'message' => ['text' => 'Hola, quiero cotizar', 'type' => 'text', 'wamid' => 'wamid.X1'],
            ],
            'sent_at' => now()->toIso8601String(),
        ]);

        $post = fn (string $body) => $this->call('POST', "/webhooks/wacrm/{$account->id}", [], [], [], [
            'HTTP_X-Webhook-Signature' => 'sha256='.hash_hmac('sha256', $body, 'whsec_secreto'),
            'CONTENT_TYPE' => 'application/json',
        ], $body);

        $post($payload)->assertOk();

        // Contacto espejo creado con teléfono normalizado.
        $contact = Contact::forAccount($account->id)->first();
        $this->assertSame('584125550001', $contact->phone_normalized);
        $this->assertSame('wacrm-contact-1', $contact->wacrm_contact_id);

        // Lead nuevo en la primera etapa abierta, source whatsapp, con el mensaje en el timeline.
        $lead = Lead::forAccount($account->id)->first();
        $this->assertSame('whatsapp', $lead->source);
        $this->assertSame($stages[0]->id, $lead->stage_id);
        $this->assertSame('conv-uuid-1', $lead->wacrm_conversation_id);
        $this->assertSame('Hola, quiero cotizar', $lead->events()->where('event_type', 'message_in')->first()->payload['text']);

        // Segundo mensaje del mismo contacto → NO crea otro lead; suma al timeline.
        $post(json_encode([
            'event' => 'message.received',
            'data' => [
                'conversation_id' => 'conv-uuid-1',
                'contact' => ['id' => 'wacrm-contact-1', 'phone' => '584125550001', 'name' => 'Ana Cliente'],
                'message' => ['text' => '¿Tienen envíos?', 'type' => 'text', 'wamid' => 'wamid.X2'],
            ],
        ]))->assertOk();

        $this->assertSame(1, Lead::forAccount($account->id)->count());
        $this->assertSame(2, $lead->events()->where('event_type', 'message_in')->count());

        // Firma inválida → 401 y nada cambia.
        $this->call('POST', "/webhooks/wacrm/{$account->id}", [], [], [], [
            'HTTP_X-Webhook-Signature' => 'sha256='.hash_hmac('sha256', $payload, 'otro-secreto'),
            'CONTENT_TYPE' => 'application/json',
        ], $payload)->assertUnauthorized();
    }

    public function test_lead_ganado_no_bloquea_lead_nuevo_del_mismo_contacto(): void
    {
        [$user, $account, $pipeline, $stages] = $this->makeAccount();

        Integration::create([
            'account_id' => $account->id,
            'wacrm_url' => 'http://localhost:8000',
            'wacrm_api_key' => 'k',
            'webhook_secret' => 'whsec_s',
            'is_active' => true,
        ]);

        $contact = Contact::create(['account_id' => $account->id, 'name' => 'Ana', 'phone' => '584125550001']);
        $ganado = Lead::create([
            'account_id' => $account->id,
            'pipeline_id' => $pipeline->id,
            'stage_id' => $stages[0]->id,
            'contact_id' => $contact->id,
            'title' => 'Venta anterior',
        ]);
        $ganado->moveToStage($stages[2], $user); // won

        $body = json_encode([
            'event' => 'message.received',
            'data' => [
                'conversation_id' => 'conv-2',
                'contact' => ['phone' => '584125550001', 'name' => 'Ana'],
                'message' => ['text' => 'Quiero comprar otra vez', 'type' => 'text'],
            ],
        ]);

        $this->call('POST', "/webhooks/wacrm/{$account->id}", [], [], [], [
            'HTTP_X-Webhook-Signature' => 'sha256='.hash_hmac('sha256', $body, 'whsec_s'),
            'CONTENT_TYPE' => 'application/json',
        ], $body)->assertOk();

        // El lead ganado queda intacto y nace uno nuevo abierto.
        $this->assertSame(2, Lead::forAccount($account->id)->count());
        $this->assertSame(1, Lead::forAccount($account->id)->where('status', 'open')->count());
    }

    public function test_aislamiento_el_webhook_de_otra_cuenta_no_cruza_datos(): void
    {
        [, $account] = $this->makeAccount();

        $otroUser = User::create(['name' => 'Otro', 'email' => 'otro@test.com', 'password' => bcrypt('password')]);
        $otraCuenta = Account::create(['name' => 'Otra', 'owner_user_id' => $otroUser->id]);

        // Sin integración configurada → 404.
        $body = json_encode(['event' => 'message.received', 'data' => []]);
        $this->call('POST', "/webhooks/wacrm/{$otraCuenta->id}", [], [], [], [
            'HTTP_X-Webhook-Signature' => 'sha256=x',
            'CONTENT_TYPE' => 'application/json',
        ], $body)->assertNotFound();

        $this->assertSame(0, Contact::forAccount($otraCuenta->id)->count());
    }
}
