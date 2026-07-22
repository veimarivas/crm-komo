<?php

namespace App\Http\Middleware;

use App\Models\ApiKey;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Autenticación de la API pública: `Authorization: Bearer komo_live_…`.
 * La clave se guarda hasheada (SHA-256); el middleware resuelve la
 * cuenta y valida el scope declarado por la ruta. Mismo patrón que
 * el wacrm.
 */
class AuthenticateApiKey
{
    public function handle(Request $request, Closure $next, ?string $scope = null): Response
    {
        $plaintext = $request->bearerToken();

        if (! $plaintext) {
            return response()->json(['message' => 'Missing API key.'], 401);
        }

        $key = ApiKey::findByPlaintext($plaintext);

        if (! $key || ! $key->isUsable()) {
            return response()->json(['message' => 'Invalid or revoked API key.'], 401);
        }

        if ($scope && ! $key->hasScope($scope)) {
            return response()->json(['message' => "Missing scope: {$scope}."], 403);
        }

        $key->update(['last_used_at' => now()]);

        $request->attributes->set('api_key', $key);
        $request->attributes->set('account_id', $key->account_id);

        return $next($request);
    }
}
