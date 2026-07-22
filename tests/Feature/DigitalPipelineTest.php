<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Contact;
use App\Models\Integration;
use App\Models\Lead;
use App\Models\Pipeline;
use App\Models\PipelineStage;
use App\Models\StageAutomation;
use App\Models\Task;
use App\Models\User;
use App\Models\WebForm;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class DigitalPipelineTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Account $account;

    private Pipeline $pipeline;

    private $stages;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::create(['name' => 'Vendedor', 'email' => 'v@test.com', 'password' => bcrypt('password')]);
        $this->account = Account::create(['name' => 'Empresa', 'owner_user_id' => $this->user->id]);
        $this->user->update(['account_id' => $this->account->id, 'account_role' => 'owner']);
        $this->user->refresh();

        $this->pipeline = Pipeline::create(['account_id' => $this->account->id, 'name' => 'Ventas', 'is_default' => true]);
        $this->stages = collect([
            ['name' => 'Nuevo', 'stage_type' => 'open'],
            ['name' => 'Negociación', 'stage_type' => 'open'],
            ['name' => 'Ganado', 'stage_type' => 'won'],
        ])->map(fn ($s, $i) => PipelineStage::create(['pipeline_id' => $this->pipeline->id, 'position' => $i, ...$s]));
    }

    public function test_crear_lead_dispara_automatizacion_de_la_primera_etapa(): void
    {
        // Al entrar a "Nuevo" → crear tarea de seguimiento en 24h.
        StageAutomation::create([
            'account_id' => $this->account->id,
            'stage_id' => $this->stages[0]->id,
            'action_type' => 'create_task',
            'config' => ['text' => 'Contactar a {name} por {title}', 'task_type' => 'call', 'due_in_hours' => 24],
        ]);

        $contact = Contact::create(['account_id' => $this->account->id, 'name' => 'Ana', 'phone' => '584125550001']);

        $lead = Lead::create([
            'account_id' => $this->account->id,
            'pipeline_id' => $this->pipeline->id,
            'stage_id' => $this->stages[0]->id,
            'contact_id' => $contact->id,
            'title' => 'Cotización web',
            'responsible_user_id' => $this->user->id,
        ]);

        // QUEUE_CONNECTION=sync en tests: el job corre inline.
        $task = Task::where('lead_id', $lead->id)->first();
        $this->assertNotNull($task);
        $this->assertSame('Contactar a Ana por Cotización web', $task->text);
        $this->assertSame('call', $task->task_type);
        $this->assertSame($this->user->id, $task->assigned_to); // hereda el responsable
        $this->assertTrue($lead->events()->where('event_type', 'task_created')->exists());
        $this->assertSame(1, StageAutomation::first()->execution_count);
    }

    public function test_mover_de_etapa_ejecuta_whatsapp_automatico(): void
    {
        Integration::create([
            'account_id' => $this->account->id,
            'wacrm_url' => 'http://localhost:8000',
            'wacrm_api_key' => 'k',
            'is_active' => true,
        ]);

        StageAutomation::create([
            'account_id' => $this->account->id,
            'stage_id' => $this->stages[1]->id, // al entrar a Negociación
            'action_type' => 'send_whatsapp',
            'config' => ['text' => 'Hola {name}, seguimos con tu {title} 🚀'],
        ]);

        Http::fake(['localhost:8000/*' => Http::response(['id' => 'msg1', 'status' => 'sent'], 201)]);

        $contact = Contact::create(['account_id' => $this->account->id, 'name' => 'Ana', 'phone' => '584125550001']);
        $lead = Lead::create([
            'account_id' => $this->account->id,
            'pipeline_id' => $this->pipeline->id,
            'stage_id' => $this->stages[0]->id,
            'contact_id' => $contact->id,
            'title' => 'plan anual',
        ]);

        $lead->moveToStage($this->stages[1], $this->user);

        Http::assertSent(fn ($request) => str_contains($request->url(), '/api/v1/messages')
            && $request['text'] === 'Hola Ana, seguimos con tu plan anual 🚀'
            && $request['to'] === '584125550001');

        $this->assertTrue($lead->events()->where('event_type', 'message_out')->exists());
    }

    public function test_automatizacion_pausada_no_se_ejecuta(): void
    {
        StageAutomation::create([
            'account_id' => $this->account->id,
            'stage_id' => $this->stages[0]->id,
            'action_type' => 'add_note',
            'config' => ['text' => 'Nota automática'],
            'is_active' => false,
        ]);

        Lead::create([
            'account_id' => $this->account->id,
            'pipeline_id' => $this->pipeline->id,
            'stage_id' => $this->stages[0]->id,
            'title' => 'Test',
        ]);

        $this->assertSame(0, \App\Models\Note::count());
    }

    public function test_formulario_web_publico_crea_lead(): void
    {
        $form = WebForm::create([
            'account_id' => $this->account->id,
            'pipeline_id' => $this->pipeline->id,
            'name' => 'Landing',
            'token' => 'tok123abc',
            'headline' => 'Cotiza ya',
        ]);

        // GET muestra el formulario.
        $this->get('/f/tok123abc')->assertOk()->assertSee('Cotiza ya');

        // POST crea contacto + lead + nota.
        $this->post('/f/tok123abc', [
            'name' => 'Pedro Web',
            'phone' => '+58 414 555 2222',
            'email' => 'pedro@test.com',
            'message' => 'Quiero información del plan pro',
        ])->assertRedirect()->assertSessionHas('webform_sent', true);

        $contact = Contact::forAccount($this->account->id)->where('phone_normalized', '584145552222')->first();
        $this->assertNotNull($contact);

        $lead = Lead::forAccount($this->account->id)->where('source', 'web_form')->first();
        $this->assertSame('Web: Pedro Web', $lead->title);
        $this->assertSame($this->stages[0]->id, $lead->stage_id);
        $this->assertSame(1, $lead->notes()->count());
        $this->assertSame(1, $form->fresh()->submissions_count);

        // Honeypot relleno → finge éxito pero no crea nada.
        $this->post('/f/tok123abc', [
            'name' => 'Bot',
            'phone' => '111222333',
            'website' => 'spam.com',
        ])->assertSessionHas('webform_sent', true);
        $this->assertSame(1, Lead::forAccount($this->account->id)->count());

        // Formulario inactivo → 404.
        $form->update(['is_active' => false]);
        $this->get('/f/tok123abc')->assertNotFound();
    }

    public function test_reportes_carga_con_datos(): void
    {
        $lead = Lead::create([
            'account_id' => $this->account->id,
            'pipeline_id' => $this->pipeline->id,
            'stage_id' => $this->stages[0]->id,
            'title' => 'Venta',
            'value' => 900,
            'responsible_user_id' => $this->user->id,
        ]);
        $lead->moveToStage($this->stages[2], $this->user); // won

        $this->actingAs($this->user)->get('/reports')->assertOk();
    }

    public function test_crud_de_automatizaciones_desde_la_ui(): void
    {
        $this->actingAs($this->user)
            ->post(route('pipelines.automations.store', $this->pipeline->id), [
                'stage_id' => $this->stages[1]->id,
                'action_type' => 'create_task',
                'config' => ['text' => 'Llamar', 'task_type' => 'call', 'due_in_hours' => 48],
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $automation = StageAutomation::first();

        // Etapa de otro pipeline → rechazada.
        $otro = Pipeline::create(['account_id' => $this->account->id, 'name' => 'Otro']);
        $etapaAjena = PipelineStage::create(['pipeline_id' => $otro->id, 'name' => 'X', 'position' => 0]);
        $this->actingAs($this->user)
            ->post(route('pipelines.automations.store', $this->pipeline->id), [
                'stage_id' => $etapaAjena->id,
                'action_type' => 'add_note',
                'config' => ['text' => 'x'],
            ])
            ->assertStatus(422);

        $this->actingAs($this->user)
            ->post(route('automations.toggle', $automation))
            ->assertRedirect();
        $this->assertFalse($automation->fresh()->is_active);
    }
}
