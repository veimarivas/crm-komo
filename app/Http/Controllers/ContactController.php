<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\Contact;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class ContactController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();
        $accountId = $user->account_id;
        $isAdmin = $user->hasRoleAtLeast(\App\Models\User::ROLE_ADMIN);

        return Inertia::render('Contacts/Index', [
            'contacts' => Contact::forAccount($accountId)
                ->with(['company:id,name', 'tags:id,name,color'])
                ->withCount(['leads as open_leads_count' => fn ($q) => $q->where('status', 'open')])
                // Agent solo ve contactos con al menos un lead asignado a él.
                ->when(! $isAdmin, fn ($query) => $query->whereHas('leads', fn ($q) => $q->where('responsible_user_id', $user->id)))
                ->when($request->query('q'), fn ($query, $q) => $query->where(fn ($w) => $w
                    ->where('name', 'like', "%{$q}%")
                    ->orWhere('phone', 'like', "%{$q}%")
                    ->orWhere('email', 'like', "%{$q}%")))
                ->orderByDesc('created_at')
                ->paginate(25)
                ->withQueryString(),
            'companies' => Company::forAccount($accountId)->orderBy('name')->get(['id', 'name']),
            'allTags' => \App\Models\Tag::forAccount($accountId)->orderBy('name')->get(['id', 'name', 'color']),
            'customFields' => \App\Models\CustomField::forAccount($accountId)
                ->where('entity', 'contact')->orderBy('position')->get(),
            'filters' => $request->only(['q']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validated($request);
        $this->assertUniquePhone($request->user()->account_id, $validated['fields']['phone'] ?? null);

        $contact = Contact::create([...$validated['fields'], 'account_id' => $request->user()->account_id]);
        $this->syncExtras($contact, $validated);

        return back()->with('success', 'Contacto creado.');
    }

    public function update(Request $request, Contact $contact): RedirectResponse
    {
        abort_if($contact->account_id !== $request->user()->account_id, 403);

        $validated = $this->validated($request);
        $this->assertUniquePhone($contact->account_id, $validated['fields']['phone'] ?? null, $contact->id);

        $contact->update($validated['fields']);
        $this->syncExtras($contact, $validated);

        return back()->with('success', 'Contacto actualizado.');
    }

    private function syncExtras(Contact $contact, array $validated): void
    {
        if ($validated['tag_ids'] !== null) {
            $valid = \App\Models\Tag::forAccount($contact->account_id)
                ->whereIn('id', $validated['tag_ids'])
                ->pluck('id');
            $contact->tags()->sync($valid);
        }

        if ($validated['custom_values'] !== null) {
            $contact->syncCustomFieldValues($validated['custom_values'], 'contact');
        }
    }

    public function destroy(Request $request, Contact $contact): RedirectResponse
    {
        abort_if($contact->account_id !== $request->user()->account_id, 403);
        $contact->delete();

        return back()->with('success', 'Contacto eliminado.');
    }

    /**
     * Importación masiva desde el wacrm: pagina su API de contactos y
     * trae los que no existan aquí (dedup por teléfono normalizado).
     */
    public function importFromWacrm(Request $request): RedirectResponse
    {
        $integration = $request->user()->account->integration;

        if (! $integration?->is_active) {
            return back()->withErrors(['import' => 'Activa la integración con el CRM de WhatsApp primero.']);
        }

        $client = \App\Services\Wacrm\Client::for($integration);
        $accountId = $request->user()->account_id;

        $imported = $skipped = 0;
        $page = 1;

        try {
            do {
                $response = $client->contacts($page);

                foreach ($response['data'] ?? [] as $remote) {
                    $normalized = Contact::normalizePhone($remote['phone'] ?? null);

                    if (! $normalized) {
                        $skipped++;

                        continue;
                    }

                    $existing = Contact::forAccount($accountId)
                        ->where('phone_normalized', $normalized)
                        ->first();

                    if ($existing) {
                        // Solo completa el vínculo si faltaba.
                        $existing->wacrm_contact_id ?: $existing->update(['wacrm_contact_id' => $remote['id'] ?? null]);
                        $skipped++;

                        continue;
                    }

                    Contact::create([
                        'account_id' => $accountId,
                        'name' => $remote['name'] ?: $remote['phone'],
                        'phone' => $remote['phone'],
                        'email' => $remote['email'] ?? null,
                        'wacrm_contact_id' => $remote['id'] ?? null,
                    ]);
                    $imported++;
                }

                $hasMore = ! empty($response['next_page_url']);
                $page++;
            } while ($hasMore && $page <= 40); // tope de seguridad: ~1000 contactos por corrida
        } catch (\RuntimeException $e) {
            return back()->withErrors(['import' => 'El wacrm respondió con error: '.$e->getMessage()]);
        }

        $integration->update(['last_sync_at' => now()]);

        return back()->with('success', "Importación completada: {$imported} contactos nuevos, {$skipped} ya existían u omitidos.");
    }

    private function validated(Request $request): array
    {
        $fields = $request->validate([
            'name' => 'required|string|max:255',
            'position' => 'nullable|string|max:120',
            'phone' => 'nullable|string|max:32',
            'email' => 'nullable|email|max:255',
            'company_id' => 'nullable|uuid',
        ]);

        if ($fields['company_id'] ?? null) {
            abort_unless(Company::forAccount($request->user()->account_id)->where('id', $fields['company_id'])->exists(), 422);
        }

        $extras = $request->validate([
            'tag_ids' => 'nullable|array',
            'tag_ids.*' => 'uuid',
            'custom_values' => 'nullable|array',
            'custom_values.*' => 'nullable|string|max:1000',
        ]);

        return [
            'fields' => $fields,
            'tag_ids' => $extras['tag_ids'] ?? null,
            'custom_values' => $extras['custom_values'] ?? null,
        ];
    }

    private function assertUniquePhone(string $accountId, ?string $phone, ?string $ignoreId = null): void
    {
        $normalized = Contact::normalizePhone($phone);

        if (! $normalized) {
            return;
        }

        $exists = Contact::forAccount($accountId)
            ->where('phone_normalized', $normalized)
            ->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages(['phone' => 'Ya existe un contacto con ese teléfono.']);
        }
    }
}
