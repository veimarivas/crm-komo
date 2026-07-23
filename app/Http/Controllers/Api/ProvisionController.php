<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ApiKey;
use App\Models\Integration;
use App\Models\User;
use App\Services\AccountProvisioner;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Provisión del ecosistema (Fase 3 del Komo Hub): crea usuario+cuenta
 * (con pipeline por defecto vía AccountProvisioner), emite una API key
 * y cablea la integración con el wacrm — todo en una llamada.
 *
 * Protegido por firma HMAC del body con HUB_PROVISION_SECRET (secreto
 * maestro compartido con el hub). Idempotente por email.
 */
class ProvisionController extends Controller
{
    public function store(Request $request, AccountProvisioner $provisioner): JsonResponse
    {
        $secret = config('services.hub.provision_secret');

        if (! $secret) {
            return response()->json(['message' => 'Provisioning no está habilitado (falta HUB_PROVISION_SECRET).'], 503);
        }

        $signature = $request->header('X-Provision-Signature', '');

        if (! hash_equals('sha256='.hash_hmac('sha256', $request->getContent(), $secret), $signature)) {
            return response()->json(['message' => 'Invalid signature.'], 401);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|lowercase|email|max:255',
            'password' => 'nullable|string|min:8|max:255',
            // Fase 7 (equipo centralizado del hub): unir a cuenta existente
            // con rol distinto de owner. Sin account_id se crea cuenta propia.
            'account_id' => 'nullable|uuid|exists:accounts,id',
            'account_role' => 'nullable|in:owner,admin,agent,viewer',
            'api_key' => 'nullable|array',
            'api_key.name' => 'required_with:api_key|string|max:100',
            'api_key.scopes' => 'required_with:api_key|array|min:1',
            'api_key.scopes.*' => Rule::in(ApiKey::SCOPES),
            // Integración komo → wacrm (la clave y el secreto los genera el hub).
            'wacrm_integration' => 'nullable|array',
            'wacrm_integration.url' => 'required_with:wacrm_integration|url|max:2048',
            'wacrm_integration.api_key' => 'required_with:wacrm_integration|string|max:255',
            'wacrm_integration.webhook_secret' => 'required_with:wacrm_integration|string|max:255',
            // Fase 4 con Invoice: integración komo → invoice (botón "Cotizar" en Leads/Show).
            'invoice_integration' => 'nullable|array',
            'invoice_integration.url' => 'required_with:invoice_integration|url|max:2048',
            'invoice_integration.api_key' => 'required_with:invoice_integration|string|max:255',
        ]);

        $user = User::where('email', $validated['email'])->first();
        $userCreated = false;

        if (! $user) {
            $user = User::create([
                'name' => $validated['name'],
                'email' => $validated['email'],
                // Sin contraseña explícita se genera una aleatoria — el
                // acceso normal será por el SSO del hub.
                'password' => Hash::make($validated['password'] ?? Str::random(40)),
                'email_verified_at' => now(),
            ]);

            if ($joinAccountId = $validated['account_id'] ?? null) {
                // Miembro invitado por el hub: se une a una cuenta existente
                // (con su pipeline ya sembrado), no se crea otra.
                $user->update([
                    'account_id' => $joinAccountId,
                    'account_role' => $validated['account_role'] ?? User::ROLE_AGENT,
                ]);
            } else {
                $provisioner->createForUser($user);
            }
            $user->refresh();
            $userCreated = true;
        }

        $plaintext = null;
        if ($validated['api_key'] ?? null) {
            [, $plaintext] = ApiKey::issue(
                $user->account_id,
                $user->id,
                $validated['api_key']['name'],
                $validated['api_key']['scopes'],
            );
        }

        if ($wacrm = $validated['wacrm_integration'] ?? null) {
            Integration::updateOrCreate(
                ['account_id' => $user->account_id],
                [
                    'wacrm_url' => rtrim($wacrm['url'], '/'),
                    'wacrm_api_key' => $wacrm['api_key'],
                    'webhook_secret' => $wacrm['webhook_secret'],
                    'is_active' => true,
                ],
            );
        }

        // Integración con Invoice (Fase 4 F4-Invoice).
        if ($invoice = $validated['invoice_integration'] ?? null) {
            $integration = Integration::forAccount($user->account_id)->firstOrCreate([]);
            $integration->update([
                'invoice_url' => rtrim($invoice['url'], '/'),
                'invoice_api_key' => $invoice['api_key'],
            ]);
        }

        return response()->json([
            'account_id' => $user->account_id,
            'user_id' => $user->id,
            'user_created' => $userCreated,
            'api_key' => $plaintext,
        ], $userCreated ? 201 : 200);
    }
}
