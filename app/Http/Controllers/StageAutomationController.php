<?php

namespace App\Http\Controllers;

use App\Models\Pipeline;
use App\Models\StageAutomation;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/** Digital Pipeline: acciones automáticas al entrar un lead a una etapa. */
class StageAutomationController extends Controller
{
    public function index(Request $request, Pipeline $pipeline): Response
    {
        abort_if($pipeline->account_id !== $request->user()->account_id, 403);

        return Inertia::render('Pipelines/Automations', [
            'pipeline' => ['id' => $pipeline->id, 'name' => $pipeline->name],
            'stages' => $pipeline->stages()->get()->map(fn ($stage) => [
                'id' => $stage->id,
                'name' => $stage->name,
                'color' => $stage->color,
                'stage_type' => $stage->stage_type,
                'automations' => StageAutomation::forAccount($pipeline->account_id)
                    ->where('stage_id', $stage->id)
                    ->orderBy('created_at')
                    ->get(),
            ]),
            'members' => User::where('account_id', $pipeline->account_id)->get(['id', 'name']),
            'whatsappEnabled' => (bool) $request->user()->account->integration?->is_active,
        ]);
    }

    public function store(Request $request, Pipeline $pipeline): RedirectResponse
    {
        abort_if($pipeline->account_id !== $request->user()->account_id, 403);

        $validated = $request->validate([
            'stage_id' => 'required|uuid',
            'action_type' => ['required', Rule::in(StageAutomation::ACTIONS)],
            'config' => 'required|array',
            'config.text' => 'required|string|max:2000',
            'config.task_type' => 'nullable|in:call,meet,follow_up,email,other',
            'config.due_in_hours' => 'nullable|integer|between:1,720',
            'config.assigned_to' => 'nullable|uuid|exists:users,id',
        ]);

        // La etapa debe ser de este pipeline.
        abort_unless($pipeline->stages()->where('id', $validated['stage_id'])->exists(), 422);

        StageAutomation::create([
            'account_id' => $pipeline->account_id,
            'stage_id' => $validated['stage_id'],
            'action_type' => $validated['action_type'],
            'config' => array_filter($validated['config'], fn ($v) => $v !== null && $v !== ''),
        ]);

        return back()->with('success', 'Automatización creada.');
    }

    public function toggle(Request $request, StageAutomation $automation): RedirectResponse
    {
        abort_if($automation->account_id !== $request->user()->account_id, 403);

        $automation->update(['is_active' => ! $automation->is_active]);

        return back();
    }

    public function destroy(Request $request, StageAutomation $automation): RedirectResponse
    {
        abort_if($automation->account_id !== $request->user()->account_id, 403);
        $automation->delete();

        return back()->with('success', 'Automatización eliminada.');
    }
}
