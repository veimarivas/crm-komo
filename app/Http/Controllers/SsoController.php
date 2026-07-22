<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;

/**
 * SSO ligero del ecosistema (Fase 2 del Komo Hub): consume tokens de
 * login de un solo uso emitidos por el hub.
 *
 * Token: base64url(json{app, email, exp, nonce}) . "." . HMAC-SHA256 hex
 * con el secreto compartido HUB_SSO_SECRET (el mismo en las 4 apps).
 * Anti-replay: el nonce se quema en cache 5 minutos (los tokens viven 60s).
 */
class SsoController extends Controller
{
    /** Identificador de ESTA app dentro del ecosistema. */
    private const APP_ID = 'komo';

    public function consume(Request $request): RedirectResponse
    {
        $secret = config('services.hub.sso_secret');
        $token = (string) $request->query('token', '');

        if (! $secret || substr_count($token, '.') !== 1) {
            return $this->reject();
        }

        [$payload, $signature] = explode('.', $token);

        // Comparación constant-time, como en los webhooks.
        if (! hash_equals(hash_hmac('sha256', $payload, $secret), $signature)) {
            return $this->reject();
        }

        $data = json_decode(base64_decode(strtr($payload, '-_', '+/')), true);

        if (! is_array($data)
            || ($data['app'] ?? null) !== self::APP_ID
            || ($data['exp'] ?? 0) < now()->timestamp) {
            return $this->reject();
        }

        // Un token, un login: si el nonce ya se usó, se rechaza.
        if (! Cache::add('hub_sso_nonce_'.$data['nonce'], 1, 300)) {
            return $this->reject();
        }

        $user = User::where('email', $data['email'] ?? '')->first();

        if (! $user) {
            return redirect()->route('login')->withErrors([
                'email' => 'Tu usuario del hub no existe en esta app. Regístrate o pide una invitación.',
            ]);
        }

        Auth::login($user);
        $request->session()->regenerate();

        // ?next= permite encadenar el salto SSO con una ruta interna
        // (deep-links desde la campana consolidada del hub). Solo se
        // acepta un path relativo (mismo host) para evitar open-redirects.
        $next = (string) $request->query('next', '');
        if ($next !== '' && str_starts_with($next, '/') && ! str_starts_with($next, '//')) {
            return redirect($next);
        }

        return redirect()->route('dashboard');
    }

    private function reject(): RedirectResponse
    {
        return redirect()->route('login')->withErrors([
            'email' => 'El enlace de acceso del hub no es válido o expiró. Vuelve a intentarlo desde el hub.',
        ]);
    }
}
