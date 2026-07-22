<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AppNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Notificaciones in-app del user dueño de la API key en uso (created_by).
 * Consumido por el Komo Hub (Fase 5) para consolidar notifs de las 3 apps.
 */
class NotificationApiController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $key = $request->attributes->get('api_key');
        $accountId = $request->attributes->get('account_id');

        $items = AppNotification::forAccount($accountId)
            ->when($key->created_by, fn ($q) => $q->where('user_id', $key->created_by))
            ->when($request->query('since'), fn ($q, $since) => $q->where('created_at', '>=', $since))
            ->orderByDesc('created_at')
            ->limit(min((int) $request->query('limit', 50), 200))
            ->get()
            ->map(fn (AppNotification $n) => [
                'id' => $n->id,
                'type' => $n->type,
                'title' => $n->title,
                'body' => $n->body,
                'link_path' => $n->lead_id ? '/leads/'.$n->lead_id : '/notifications',
                'created_at' => $n->created_at->toIso8601String(),
                'read_at' => $n->read_at?->toIso8601String(),
            ]);

        return response()->json(['data' => $items]);
    }
}
