<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Account;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * API pública v1 del komo (patrón wacrm). Autenticada por API key
 * (middleware api.key) con scopes.
 */
class ApiController extends Controller
{
    public function me(Request $request): JsonResponse
    {
        $key = $request->attributes->get('api_key');
        $account = Account::find($request->attributes->get('account_id'));

        return response()->json([
            'account' => ['id' => $account->id, 'name' => $account->name],
            'key' => ['name' => $key->name, 'scopes' => $key->scopes],
        ]);
    }
}
