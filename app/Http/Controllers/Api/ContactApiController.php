<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Contact;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Contactos vía API pública. El consumidor previsto es meta_ads
 * (Fase 7.2): pagina `?tag_id=` para armar Custom Audiences con los
 * teléfonos/emails de un tag del komo.
 */
class ContactApiController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $contacts = Contact::forAccount($request->attributes->get('account_id'))
            ->with('tags:id,name')
            ->when($request->query('q'), fn ($q, $term) => $q
                ->where(fn ($w) => $w->where('name', 'like', "%{$term}%")
                    ->orWhere('phone', 'like', "%{$term}%")))
            // Filtro server-side por tag (mismo contrato que el wacrm).
            ->when($request->query('tag_id'), fn ($q, $tagId) => $q
                ->whereHas('tags', fn ($t) => $t->where('tags.id', $tagId)))
            ->orderByDesc('created_at')
            ->paginate(min((int) $request->query('per_page', 25), 100));

        return response()->json($contacts);
    }
}
