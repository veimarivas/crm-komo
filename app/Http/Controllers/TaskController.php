<?php

namespace App\Http\Controllers;

use App\Models\Lead;
use App\Models\Task;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TaskController extends Controller
{
    public function index(Request $request): Response
    {
        $accountId = $request->user()->account_id;
        $filter = $request->query('filter', 'pending');
        $mine = $request->boolean('mine', true);

        $tasks = Task::forAccount($accountId)
            ->with(['lead:id,title', 'contact:id,name', 'assignee:id,name'])
            ->when($mine, fn ($q) => $q->where('assigned_to', $request->user()->id))
            ->when($filter === 'pending', fn ($q) => $q->pending())
            ->when($filter === 'overdue', fn ($q) => $q->overdue())
            ->when($filter === 'today', fn ($q) => $q->pending()->whereBetween('due_at', [now()->startOfDay(), now()->endOfDay()]))
            ->when($filter === 'done', fn ($q) => $q->whereNotNull('completed_at')->latest('completed_at'))
            ->when($filter !== 'done', fn ($q) => $q->orderBy('due_at'))
            ->paginate(30)
            ->withQueryString();

        return Inertia::render('Tasks/Index', [
            'tasks' => $tasks,
            'filters' => ['filter' => $filter, 'mine' => $mine],
            'members' => User::where('account_id', $accountId)->get(['id', 'name']),
            'counts' => [
                'overdue' => Task::forAccount($accountId)->overdue()
                    ->when($mine, fn ($q) => $q->where('assigned_to', $request->user()->id))->count(),
                'today' => Task::forAccount($accountId)->pending()
                    ->whereBetween('due_at', [now()->startOfDay(), now()->endOfDay()])
                    ->when($mine, fn ($q) => $q->where('assigned_to', $request->user()->id))->count(),
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $accountId = $request->user()->account_id;

        $validated = $request->validate([
            'lead_id' => 'nullable|uuid',
            'assigned_to' => 'nullable|uuid|exists:users,id',
            'task_type' => 'required|in:call,meet,follow_up,email,other',
            'text' => 'required|string|max:2000',
            'due_at' => 'required|date',
        ]);

        $lead = null;
        if ($validated['lead_id'] ?? null) {
            $lead = Lead::forAccount($accountId)->findOrFail($validated['lead_id']);
        }

        $task = Task::create([
            ...$validated,
            'account_id' => $accountId,
            'contact_id' => $lead?->contact_id,
            'assigned_to' => $validated['assigned_to'] ?? $request->user()->id,
            'created_by' => $request->user()->id,
        ]);

        $lead?->recordEvent('task_created', $request->user(), [
            'text' => $task->text,
            'due_at' => $task->due_at->toIso8601String(),
        ]);

        return back()->with('success', 'Tarea creada.');
    }

    public function complete(Request $request, Task $task): RedirectResponse
    {
        abort_if($task->account_id !== $request->user()->account_id, 403);

        $validated = $request->validate(['result_note' => 'nullable|string|max:2000']);

        $task->complete($validated['result_note'] ?? null, $request->user());

        return back()->with('success', 'Tarea completada.');
    }

    public function destroy(Request $request, Task $task): RedirectResponse
    {
        abort_if($task->account_id !== $request->user()->account_id, 403);
        $task->delete();

        return back()->with('success', 'Tarea eliminada.');
    }
}
