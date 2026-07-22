<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\Contact;
use App\Models\Lead;
use App\Models\Pipeline;
use App\Models\PipelineStage;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class LeadController extends Controller
{
    public function index(Request $request): Response
    {
        $accountId = $request->user()->account_id;

        $pipelines = Pipeline::forAccount($accountId)->with('stages')->orderBy('created_at')->get();
        $selected = $pipelines->firstWhere('id', $request->query('pipeline'))
            ?? $pipelines->firstWhere('is_default', true)
            ?? $pipelines->first();

        $user = $request->user();
        $isAdmin = $user->hasRoleAtLeast(User::ROLE_ADMIN);

        $leads = $selected
            ? $selected->leads()
                ->with(['contact:id,name,phone', 'responsible:id,name'])
                ->withCount(['tasks as pending_tasks_count' => fn ($q) => $q->whereNull('completed_at')])
                // Restricción por rol: agent/viewer solo ve los leads asignados
                // a ellos. admin/owner ven todo el pipeline.
                ->when(! $isAdmin, fn ($q) => $q->where('responsible_user_id', $user->id))
                ->orderByDesc('created_at')
                ->get()
            : collect();

        return Inertia::render('Leads/Index', [
            'pipelines' => $pipelines->map(fn ($p) => ['id' => $p->id, 'name' => $p->name]),
            'pipeline' => $selected ? ['id' => $selected->id, 'name' => $selected->name, 'stages' => $selected->stages] : null,
            'leads' => $leads,
            'members' => User::where('account_id', $accountId)->get(['id', 'name']),
            'contacts' => Contact::forAccount($accountId)->orderBy('name')->limit(500)->get(['id', 'name', 'phone']),
            'currency' => $request->user()->account->default_currency,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $accountId = $request->user()->account_id;

        $validated = $request->validate([
            'pipeline_id' => 'required|uuid',
            'stage_id' => 'nullable|uuid',
            'title' => 'required|string|max:255',
            'value' => 'nullable|numeric|min:0|max:9999999999.99',
            'contact_id' => 'nullable|uuid',
            'responsible_user_id' => 'nullable|uuid',
        ]);

        $pipeline = Pipeline::forAccount($accountId)->findOrFail($validated['pipeline_id']);

        $stage = $validated['stage_id'] ?? null
            ? $pipeline->stages()->findOrFail($validated['stage_id'])
            : $pipeline->stages()->where('stage_type', 'open')->orderBy('position')->firstOrFail();

        if ($validated['contact_id'] ?? null) {
            abort_unless(Contact::forAccount($accountId)->where('id', $validated['contact_id'])->exists(), 422);
        }

        $lead = Lead::create([
            'account_id' => $accountId,
            'pipeline_id' => $pipeline->id,
            'stage_id' => $stage->id,
            'title' => $validated['title'],
            'value' => $validated['value'] ?? 0,
            'currency' => $request->user()->account->default_currency,
            'contact_id' => $validated['contact_id'] ?? null,
            'responsible_user_id' => $validated['responsible_user_id'] ?? $request->user()->id,
        ]);

        $lead->recordEvent('created', $request->user(), ['source' => 'manual']);

        \App\Models\AppNotification::notify(
            $accountId,
            $lead->responsible_user_id,
            'lead_assigned',
            'Lead asignado',
            "Te asignaron el lead «{$lead->title}»",
            $lead->id,
            $request->user()->id,
        );

        return redirect()->route('leads.show', $lead)->with('success', 'Lead creado.');
    }

    public function show(Request $request, Lead $lead): Response
    {
        $this->authorizeLead($request, $lead);

        $lead->load([
            'contact', 'company', 'responsible:id,name',
            'stage:id,name,color,stage_type',
            'pipeline:id,name',
            'tags:id,name,color',
        ]);

        $integration = $request->user()->account->integration;

        return Inertia::render('Leads/Show', [
            'lead' => $lead,
            'stages' => $lead->pipeline->stages()->get(),
            'events' => $lead->events()->with('actor:id,name')->limit(60)->get(),
            'tasks' => $lead->tasks()->with('assignee:id,name')->orderByRaw('completed_at IS NULL DESC')->orderBy('due_at')->get(),
            'notes' => $lead->notes()->with('author:id,name')->limit(30)->get(),
            'members' => User::where('account_id', $lead->account_id)->get(['id', 'name']),
            'contacts' => Contact::forAccount($lead->account_id)->orderBy('name')->limit(500)->get(['id', 'name', 'phone']),
            'companies' => Company::forAccount($lead->account_id)->orderBy('name')->limit(500)->get(['id', 'name']),
            'allTags' => \App\Models\Tag::forAccount($lead->account_id)->orderBy('name')->get(['id', 'name', 'color']),
            'customFields' => \App\Models\CustomField::forAccount($lead->account_id)
                ->where('entity', 'lead')->orderBy('position')->get(),
            'customValues' => $lead->customFieldValues(),
            'whatsappEnabled' => (bool) ($integration?->is_active && $lead->contact?->phone),
        ]);
    }

    public function update(Request $request, Lead $lead): RedirectResponse
    {
        $this->authorizeLead($request, $lead);

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'value' => 'nullable|numeric|min:0|max:9999999999.99',
            'contact_id' => 'nullable|uuid',
            'company_id' => 'nullable|uuid',
            'responsible_user_id' => 'nullable|uuid|exists:users,id',
            'custom_values' => 'nullable|array',
            'custom_values.*' => 'nullable|string|max:1000',
        ]);

        // Solo admin/owner puede cambiar el responsable. Los agents no pueden
        // reasignarse un lead ni pasarlo a otro miembro del equipo.
        if (! $request->user()->hasRoleAtLeast(User::ROLE_ADMIN)) {
            unset($validated['responsible_user_id']);
        }

        $customValues = $validated['custom_values'] ?? null;
        unset($validated['custom_values']);

        foreach (['contact_id' => Contact::class, 'company_id' => Company::class] as $field => $model) {
            if ($validated[$field] ?? null) {
                abort_unless($model::forAccount($lead->account_id)->where('id', $validated[$field])->exists(), 422);
            }
        }

        $oldValue = (string) $lead->value;
        $oldResponsible = $lead->responsible_user_id;
        $lead->update([...$validated, 'value' => $validated['value'] ?? 0]);

        if ($customValues !== null) {
            $lead->syncCustomFieldValues($customValues, 'lead');
        }

        if ($lead->responsible_user_id !== $oldResponsible) {
            if ($lead->responsible_user_id) {
                \App\Models\AppNotification::notify(
                    $lead->account_id,
                    $lead->responsible_user_id,
                    'lead_assigned',
                    'Lead asignado',
                    "Te asignaron el lead «{$lead->title}»",
                    $lead->id,
                    $request->user()->id,
                );
            }

            // Espeja la asignación en el wacrm: la conversación pasa al
            // Inbox del agente responsable. Silencioso si la integración
            // no está configurada o si falla la red.
            $this->syncAssignmentToWacrm($lead);
        }

        if ($oldValue !== (string) $lead->value) {
            $lead->recordEvent('value_changed', $request->user(), ['from' => $oldValue, 'to' => (string) $lead->value]);
        }

        return back()->with('success', 'Lead actualizado.');
    }

    /**
     * Sincroniza el responsable del lead con la conversación en el wacrm.
     * Se hace por email del agente (que debe existir en ambos sistemas).
     */
    private function syncAssignmentToWacrm(Lead $lead): void
    {
        if (! $lead->wacrm_conversation_id) {
            return; // el lead no vino de WhatsApp, no hay nada que sincronizar
        }

        $integration = \App\Models\Integration::forAccount($lead->account_id)->first();
        if (! $integration || ! $integration->wacrm_url || ! $integration->wacrm_api_key) {
            return;
        }

        $email = $lead->responsible_user_id
            ? \App\Models\User::whereKey($lead->responsible_user_id)->value('email')
            : null;

        try {
            \App\Services\Wacrm\Client::for($integration)->assignConversation($lead->wacrm_conversation_id, $email);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('Sync asignación → wacrm falló', [
                'lead_id' => $lead->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /** Mover de etapa (Kanban o ficha). El estado se deriva de la etapa. */
    public function move(Request $request, Lead $lead): RedirectResponse
    {
        $this->authorizeLead($request, $lead);

        $validated = $request->validate(['stage_id' => 'required|uuid']);
        $stage = PipelineStage::findOrFail($validated['stage_id']);

        try {
            $lead->moveToStage($stage, $request->user());
        } catch (\InvalidArgumentException $e) {
            throw ValidationException::withMessages(['stage_id' => $e->getMessage()]);
        }

        return back();
    }

    public function destroy(Request $request, Lead $lead): RedirectResponse
    {
        $this->authorizeLead($request, $lead);
        $lead->delete();

        return redirect()->route('leads.index')->with('success', 'Lead eliminado.');
    }

    public function syncTags(Request $request, Lead $lead): RedirectResponse
    {
        $this->authorizeLead($request, $lead);

        $validated = $request->validate(['tag_ids' => 'nullable|array', 'tag_ids.*' => 'uuid']);

        $valid = \App\Models\Tag::forAccount($lead->account_id)
            ->whereIn('id', $validated['tag_ids'] ?? [])
            ->pluck('id');

        $lead->tags()->sync($valid);

        return back();
    }

    public function addNote(Request $request, Lead $lead): RedirectResponse
    {
        $this->authorizeLead($request, $lead);

        $validated = $request->validate(['text' => 'required|string|max:5000']);

        $lead->notes()->create([
            'account_id' => $lead->account_id,
            'user_id' => $request->user()->id,
            'text' => $validated['text'],
        ]);

        $lead->recordEvent('note_added', $request->user(), ['text' => mb_substr($validated['text'], 0, 200)]);

        return back()->with('success', 'Nota añadida.');
    }

    /** Envía un WhatsApp al contacto del lead a través del wacrm. */
    public function sendWhatsapp(Request $request, Lead $lead): RedirectResponse
    {
        $this->authorizeLead($request, $lead);

        $validated = $request->validate(['text' => 'required|string|max:4096']);

        $integration = $request->user()->account->integration;

        if (! $integration?->is_active) {
            throw ValidationException::withMessages(['text' => 'La integración con el CRM de WhatsApp no está activa.']);
        }

        if (! $lead->contact?->phone) {
            throw ValidationException::withMessages(['text' => 'El lead no tiene un contacto con teléfono.']);
        }

        try {
            \App\Services\Wacrm\Client::for($integration)->sendMessage(
                $lead->contact->phone_normalized ?? $lead->contact->phone,
                $validated['text'],
            );
        } catch (\RuntimeException $e) {
            throw ValidationException::withMessages(['text' => $e->getMessage()]);
        }

        $lead->recordEvent('message_out', $request->user(), [
            'text' => mb_substr($validated['text'], 0, 500),
        ]);

        return back()->with('success', 'WhatsApp enviado.');
    }

    private function authorizeLead(Request $request, Lead $lead): void
    {
        $user = $request->user();

        abort_if($lead->account_id !== $user->account_id, 403);

        // Agent/viewer: solo puede ver/editar/escribir en leads asignados a él.
        // admin/owner: acceso completo (para hacer seguimiento del equipo).
        if (! $user->hasRoleAtLeast(User::ROLE_ADMIN)) {
            abort_if($lead->responsible_user_id !== $user->id, 403,
                'No tienes acceso a este lead. Pídele al admin que te lo asigne.');
        }
    }
}
