<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Contact;
use App\Models\CustomField;
use App\Models\Lead;
use App\Models\Pipeline;
use App\Models\User;
use App\Services\AccountProvisioner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Phase5Test extends TestCase
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

    public function test_campos_personalizados_en_leads(): void
    {
        // Crear campos desde la UI.
        $this->actingAs($this->user)
            ->post(route('custom-fields.store'), [
                'entity' => 'lead',
                'name' => 'Temperatura',
                'field_type' => 'select',
                'options' => ['Frío', 'Tibio', 'Caliente'],
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $field = CustomField::forAccount($this->account->id)->first();
        $this->assertSame(['Frío', 'Tibio', 'Caliente'], $field->options);

        $pipeline = Pipeline::forAccount($this->account->id)->first();
        $lead = Lead::create([
            'account_id' => $this->account->id,
            'pipeline_id' => $pipeline->id,
            'stage_id' => $pipeline->stages()->first()->id,
            'title' => 'Deal',
        ]);

        // Guardar valor vía update del lead.
        $this->actingAs($this->user)
            ->patch(route('leads.update', $lead->id), [
                'title' => 'Deal',
                'value' => 100,
                'custom_values' => [$field->id => 'Caliente'],
            ])
            ->assertRedirect();

        $this->assertSame(['Caliente'], array_values($lead->customFieldValues()));

        // Vaciar el valor lo borra.
        $this->actingAs($this->user)
            ->patch(route('leads.update', $lead->id), [
                'title' => 'Deal',
                'value' => 100,
                'custom_values' => [$field->id => ''],
            ]);

        $this->assertSame([], $lead->customFieldValues());

        // Un campo de OTRA cuenta se ignora silenciosamente.
        $otro = User::create(['name' => 'X', 'email' => 'x@test.com', 'password' => bcrypt('password')]);
        $otraCuenta = app(AccountProvisioner::class)->createForUser($otro);
        $campoAjeno = CustomField::create([
            'account_id' => $otraCuenta->id, 'entity' => 'lead', 'name' => 'Ajeno', 'field_type' => 'text',
        ]);

        $lead->syncCustomFieldValues([$campoAjeno->id => 'hack'], 'lead');
        $this->assertSame([], $lead->customFieldValues());

        // Eliminar el campo borra sus valores (cascade).
        $lead->syncCustomFieldValues([$field->id => 'Tibio'], 'lead');
        $this->actingAs($this->user)->delete(route('custom-fields.destroy', $field->id));
        $this->assertSame([], $lead->customFieldValues());
    }

    public function test_campos_de_contacto_y_tags_via_modal(): void
    {
        $campo = CustomField::create([
            'account_id' => $this->account->id, 'entity' => 'contact', 'name' => 'Cumpleaños', 'field_type' => 'date',
        ]);

        $this->actingAs($this->user)
            ->post(route('tags.store'), ['name' => 'Mayorista'])
            ->assertRedirect();
        $tag = \App\Models\Tag::forAccount($this->account->id)->first();

        $this->actingAs($this->user)
            ->post(route('contacts.store'), [
                'name' => 'Ana',
                'phone' => '584125550001',
                'tag_ids' => [$tag->id],
                'custom_values' => [$campo->id => '1990-05-10'],
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $contact = Contact::forAccount($this->account->id)->first();
        $this->assertTrue($contact->tags->contains($tag));
        $this->assertSame('1990-05-10', $contact->customFieldValues()[$campo->id]);
    }

    public function test_transferencia_de_ownership(): void
    {
        $admin = User::create([
            'name' => 'Segundo',
            'email' => 's@test.com',
            'password' => bcrypt('password'),
            'account_id' => $this->account->id,
            'account_role' => 'admin',
        ]);

        // Un no-owner no puede.
        $this->actingAs($admin)
            ->post(route('team.members.transfer', $this->user->id))
            ->assertForbidden();

        // El owner sí.
        $this->actingAs($this->user)
            ->post(route('team.members.transfer', $admin->id))
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertSame($admin->id, $this->account->fresh()->owner_user_id);
        $this->assertSame('owner', $admin->fresh()->account_role);
        $this->assertSame('admin', $this->user->fresh()->account_role);
    }
}
