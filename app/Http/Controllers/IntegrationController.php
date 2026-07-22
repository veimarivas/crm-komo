<?php

namespace App\Http\Controllers;

use App\Models\Integration;
use App\Services\Wacrm\Client;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** Ajustes de la conexión con el wacrm (CRM de WhatsApp). */
class IntegrationController extends Controller
{
    public function edit(Request $request): Response
    {
        $integration = Integration::forAccount($request->user()->account_id)->first();

        return Inertia::render('Settings/Integration', [
            'integration' => $integration ? [
                'wacrm_url' => $integration->wacrm_url,
                'is_active' => $integration->is_active,
                'last_sync_at' => $integration->last_sync_at?->toIso8601String(),
                'has_api_key' => true,
                'has_webhook_secret' => (bool) $integration->webhook_secret,
            ] : null,
            // Esta URL se pega en el wacrm al crear el webhook saliente.
            'webhookUrl' => route('webhooks.wacrm', $request->user()->account_id),
            'testResult' => session('test_result'),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'wacrm_url' => 'required|url|max:2048',
            'wacrm_api_key' => 'nullable|string',
            'webhook_secret' => 'nullable|string',
            'is_active' => 'boolean',
        ]);

        $accountId = $request->user()->account_id;
        $existing = Integration::forAccount($accountId)->first();

        if (! $existing && empty($validated['wacrm_api_key'])) {
            return back()->withErrors(['wacrm_api_key' => 'La API key del wacrm es obligatoria.']);
        }

        // Vacío = conservar los valores actuales (cifrados).
        foreach (['wacrm_api_key', 'webhook_secret'] as $secret) {
            if (empty($validated[$secret])) {
                unset($validated[$secret]);
            }
        }

        Integration::updateOrCreate(['account_id' => $accountId], $validated);

        return back()->with('success', 'Integración guardada.');
    }

    /** Prueba la conexión llamando a /api/v1/me del wacrm. */
    public function test(Request $request): RedirectResponse
    {
        $integration = Integration::forAccount($request->user()->account_id)->first();

        if (! $integration) {
            return back()->withErrors(['wacrm_url' => 'Configura la integración primero.']);
        }

        try {
            $me = Client::for($integration)->me();
            $integration->update(['last_sync_at' => now()]);

            return back()->with('test_result', [
                'ok' => true,
                'account' => $me['account']['name'] ?? '?',
                'scopes' => $me['key']['scopes'] ?? [],
            ]);
        } catch (\RuntimeException $e) {
            return back()->with('test_result', ['ok' => false, 'error' => $e->getMessage()]);
        }
    }
}
