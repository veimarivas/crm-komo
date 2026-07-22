<?php

namespace App\Http\Controllers;

use App\Models\Integration;
use App\Services\Wacrm\EventProcessor;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

/**
 * Receptor de los webhooks salientes del wacrm. La URL incluye el id
 * de la cuenta (uuid no adivinable) y cada entrega viene firmada con
 * HMAC-SHA256 en X-Webhook-Signature — el mismo contrato que el wacrm
 * usa con cualquier receptor externo.
 */
class WacrmWebhookController extends Controller
{
    public function receive(Request $request, string $accountId, EventProcessor $processor): Response
    {
        $integration = Integration::where('account_id', $accountId)
            ->where('is_active', true)
            ->first();

        if (! $integration || ! $integration->webhook_secret) {
            return response('Unknown integration', 404);
        }

        $signature = $request->header('X-Webhook-Signature', '');
        $expected = 'sha256='.hash_hmac('sha256', $request->getContent(), $integration->webhook_secret);

        if (! hash_equals($expected, $signature)) {
            Log::warning('Webhook wacrm rechazado: firma inválida', ['account_id' => $accountId]);

            return response('Invalid signature', 401);
        }

        $payload = $request->json()->all();

        try {
            $processor->process(
                $integration,
                $payload['event'] ?? '',
                $payload['data'] ?? [],
            );
        } catch (\Throwable $e) {
            // 200 igualmente: el wacrm desactiva receptores tras 10
            // fallos consecutivos; el error queda en logs.
            Log::error('Error procesando webhook wacrm', ['exception' => $e]);
        }

        return response('OK', 200);
    }
}
