<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\AppNotification;
use App\Models\Company;
use App\Models\Contact;
use App\Models\CustomField;
use App\Models\Integration;
use App\Models\Lead;
use App\Models\Pipeline;
use App\Models\Task;
use App\Models\User;
use App\Services\AccountProvisioner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Phase6Test extends TestCase
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

    public function test_asignar_lead_notifica_al_responsable_pero_no_a_uno_mismo(): void
    {
        $agent = User::create([
            'name' => 'Agente', 'email' => 'a@test.com', 'password' => bcrypt('password'),
            'account_id' => $this->account->id, 'account_role' => 'agent',
        ]);
        $pipeline = Pipeline::forAccount($this->account->id)->first();

        // Crear lead asignándoselo al agente → notificación.
        $this->actingAs($this->user)->post(route('leads.store'), [
            'pipeline_id' => $pipeline->id,
            'title' => 'Para el agente',
            'responsible_user_id' => $agent->id,
        ]);

        $notification = AppNotification::where('user_id', $agent->id)->first();
        $this->assertSame('lead_assigned', $notification->type);
        $this->assertStringContainsString('Para el agente', $notification->body);

        // Crear lead para uno mismo → NO se notifica.
        $this->actingAs($this->user)->post(route('leads.store'), [
            'pipeline_id' => $pipeline->id,
            'title' => 'Mío',
            'responsible_user_id' => $this->user->id,
        ]);

        $this->assertSame(0, AppNotification::where('user_id', $this->user->id)->count());
    }

    public function test_lead_por_webhook_whatsapp_notifica_al_owner(): void
    {
        Integration::create([
            'account_id' => $this->account->id,
            'wacrm_url' => 'http://localhost:8000',
            'wacrm_api_key' => 'k',
            'webhook_secret' => 'whsec_s',
            'is_active' => true,
        ]);

        $body = json_encode([
            'event' => 'message.received',
            'data' => [
                'conversation_id' => 'c1',
                'contact' => ['phone' => '584125550001', 'name' => 'Ana'],
                'message' => ['text' => 'hola', 'type' => 'text'],
            ],
        ]);

        $this->call('POST', "/webhooks/wacrm/{$this->account->id}", [], [], [], [
            'HTTP_X-Webhook-Signature' => 'sha256='.hash_hmac('sha256', $body, 'whsec_s'),
            'CONTENT_TYPE' => 'application/json',
        ], $body)->assertOk();

        $notification = AppNotification::where('user_id', $this->user->id)->first();
        $this->assertSame('lead_created_whatsapp', $notification->type);
        $this->assertNotNull($notification->lead_id);
    }

    public function test_comando_notifica_tareas_vencidas_una_sola_vez(): void
    {
        $pipeline = Pipeline::forAccount($this->account->id)->first();
        $lead = Lead::create([
            'account_id' => $this->account->id,
            'pipeline_id' => $pipeline->id,
            'stage_id' => $pipeline->stages()->first()->id,
            'title' => 'Con tarea',
        ]);

        Task::create([
            'account_id' => $this->account->id,
            'lead_id' => $lead->id,
            'assigned_to' => $this->user->id,
            'text' => 'Llamar urgente',
            'due_at' => now()->subHours(2),
        ]);

        $this->artisan('tasks:notify-overdue')->assertSuccessful();
        $this->assertSame(1, AppNotification::where('type', 'task_overdue')->count());

        // Segunda corrida: no duplica.
        $this->artisan('tasks:notify-overdue')->assertSuccessful();
        $this->assertSame(1, AppNotification::where('type', 'task_overdue')->count());
    }

    public function test_marcar_todas_leidas(): void
    {
        AppNotification::notify($this->account->id, $this->user->id, 'lead_assigned', 'Uno');
        AppNotification::notify($this->account->id, $this->user->id, 'lead_assigned', 'Dos');

        $this->actingAs($this->user)
            ->get(route('notifications'))
            ->assertOk();

        $this->actingAs($this->user)
            ->post(route('notifications.read-all'))
            ->assertRedirect();

        $this->assertSame(0, AppNotification::where('user_id', $this->user->id)->whereNull('read_at')->count());
    }

    public function test_empresas_con_tags_y_campos_personalizados(): void
    {
        $tag = \App\Models\Tag::create(['account_id' => $this->account->id, 'name' => 'Corporativo']);
        $campo = CustomField::create([
            'account_id' => $this->account->id, 'entity' => 'company', 'name' => 'RIF', 'field_type' => 'text',
        ]);

        $this->actingAs($this->user)
            ->post(route('companies.store'), [
                'name' => 'Acme C.A.',
                'tag_ids' => [$tag->id],
                'custom_values' => [$campo->id => 'J-12345678-9'],
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $company = Company::forAccount($this->account->id)->first();
        $this->assertTrue($company->tags->contains($tag));
        $this->assertSame('J-12345678-9', $company->customFieldValues()[$campo->id]);
    }
}
