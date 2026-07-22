<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * SSO ligero del ecosistema: /sso/consume acepta tokens de un solo uso
 * emitidos por el Komo Hub (firma HMAC + expiración + nonce anti-replay).
 */
class SsoConsumeTest extends TestCase
{
    use RefreshDatabase;

    private const APP_ID = 'komo';

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.hub.sso_secret' => 'sso-secret']);

        User::create(['name' => 'Admin', 'email' => 'admin@test.com', 'password' => bcrypt('password')]);
    }

    private function makeToken(array $overrides = [], string $secret = 'sso-secret'): string
    {
        $data = array_merge([
            'app' => self::APP_ID,
            'email' => 'admin@test.com',
            'exp' => now()->addSeconds(60)->timestamp,
            'nonce' => Str::random(48),
        ], $overrides);

        $payload = rtrim(strtr(base64_encode(json_encode($data)), '+/', '-_'), '=');

        return $payload.'.'.hash_hmac('sha256', $payload, $secret);
    }

    public function test_token_valido_abre_sesion_y_un_replay_se_rechaza(): void
    {
        $token = $this->makeToken();

        $this->get('/sso/consume?token='.urlencode($token))
            ->assertRedirect(route('dashboard'));
        $this->assertAuthenticated();

        // Mismo token otra vez (replay) → rechazado.
        auth()->logout();
        $this->get('/sso/consume?token='.urlencode($token))
            ->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_firma_invalida_expirado_o_app_equivocada_se_rechazan(): void
    {
        // Firmado con otro secreto.
        $this->get('/sso/consume?token='.urlencode($this->makeToken(secret: 'otro')))
            ->assertRedirect(route('login'));
        $this->assertGuest();

        // Expirado.
        $this->get('/sso/consume?token='.urlencode($this->makeToken(['exp' => now()->subMinute()->timestamp])))
            ->assertRedirect(route('login'));
        $this->assertGuest();

        // Token emitido para otra app del ecosistema.
        $this->get('/sso/consume?token='.urlencode($this->makeToken(['app' => 'wacrm'])))
            ->assertRedirect(route('login'));
        $this->assertGuest();

        // Basura.
        $this->get('/sso/consume?token=basura')->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_email_desconocido_no_crea_usuario(): void
    {
        $this->get('/sso/consume?token='.urlencode($this->makeToken(['email' => 'nadie@test.com'])))
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors('email');

        $this->assertGuest();
        $this->assertSame(1, User::count());
    }
}
