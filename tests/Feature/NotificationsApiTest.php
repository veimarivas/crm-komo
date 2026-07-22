<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\ApiKey;
use App\Models\AppNotification;
use App\Models\Lead;
use App\Models\Pipeline;
use App\Models\PipelineStage;
use App\Models\User;
use App\Services\AccountProvisioner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NotificationsApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_expone_notificaciones_del_user_dueño_de_la_key(): void
    {
        $hubUser = User::create(['name' => 'Hub', 'email' => 'hub@test.com', 'password' => bcrypt('p')]);
        app(AccountProvisioner::class)->createForUser($hubUser);
        $hubUser->refresh();
        $account = Account::find($hubUser->account_id);

        $otro = User::create(['name' => 'Otro', 'account_id' => $account->id, 'email' => 'otro@test.com', 'password' => bcrypt('p')]);

        $pipeline = Pipeline::forAccount($account->id)->first();
        $stage = $pipeline->stages()->where('stage_type', 'open')->first();
        $lead = Lead::create([
            'account_id' => $account->id, 'pipeline_id' => $pipeline->id, 'stage_id' => $stage->id, 'title' => 'X',
        ]);

        AppNotification::create(['account_id' => $account->id, 'user_id' => $hubUser->id, 'type' => 'lead_new', 'title' => 'Para el hub', 'lead_id' => $lead->id]);
        AppNotification::create(['account_id' => $account->id, 'user_id' => $otro->id, 'type' => 'lead_new', 'title' => 'Para otro']);

        [, $plain] = ApiKey::issue($account->id, $hubUser->id, 'Hub', ['notifications:read']);

        $this->withToken($plain)->getJson('/api/v1/notifications')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.title', 'Para el hub')
            ->assertJsonPath('data.0.link_path', "/leads/{$lead->id}");

        [, $sinScope] = ApiKey::issue($account->id, $hubUser->id, 'X', ['leads:read']);
        $this->withToken($sinScope)->getJson('/api/v1/notifications')->assertForbidden();
    }
}
