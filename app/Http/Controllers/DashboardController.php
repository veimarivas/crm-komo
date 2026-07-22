<?php

namespace App\Http\Controllers;

use App\Models\Lead;
use App\Models\Task;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();
        $accountId = $user->account_id;
        $isAdmin = $user->hasRoleAtLeast(User::ROLE_ADMIN);

        // Scope Lead: admin ve todo, agent solo lo asignado.
        $leadScope = fn ($q) => $isAdmin ? $q : $q->where('responsible_user_id', $user->id);
        $taskScope = fn ($q) => $isAdmin ? $q : $q->where('assigned_to', $user->id);

        return Inertia::render('Dashboard', [
            'stats' => [
                'openLeads' => $leadScope(Lead::forAccount($accountId)->where('status', 'open'))->count(),
                'openValue' => (float) $leadScope(Lead::forAccount($accountId)->where('status', 'open'))->sum('value'),
                'wonThisMonth' => $leadScope(Lead::forAccount($accountId)->where('status', 'won')
                    ->where('closed_at', '>=', now()->startOfMonth()))->count(),
                'wonValueThisMonth' => (float) $leadScope(Lead::forAccount($accountId)->where('status', 'won')
                    ->where('closed_at', '>=', now()->startOfMonth()))->sum('value'),
                'overdueTasks' => $taskScope(Task::forAccount($accountId)->overdue())->count(),
                'tasksToday' => $taskScope(Task::forAccount($accountId)->pending()
                    ->whereBetween('due_at', [now()->startOfDay(), now()->endOfDay()]))->count(),
                // Regla Kommo: leads abiertos SIN tarea pendiente = leads olvidados.
                'leadsWithoutTask' => $leadScope(Lead::forAccount($accountId)->where('status', 'open')
                    ->whereDoesntHave('tasks', fn ($q) => $q->whereNull('completed_at')))->count(),
            ],
            'recentLeads' => $leadScope(Lead::forAccount($accountId)
                ->with(['contact:id,name,phone', 'stage:id,name,color'])
                ->latest())
                ->limit(6)
                ->get(['id', 'title', 'value', 'currency', 'status', 'contact_id', 'stage_id', 'responsible_user_id', 'created_at']),
            'myTasks' => Task::forAccount($accountId)
                ->pending()
                ->where('assigned_to', $user->id)
                ->with('lead:id,title')
                ->orderBy('due_at')
                ->limit(6)
                ->get(),
            'currency' => $user->account->default_currency,
        ]);
    }
}
