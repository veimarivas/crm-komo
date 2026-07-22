<?php

namespace App\Http\Controllers;

use App\Models\Lead;
use App\Models\Task;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(Request $request): Response
    {
        $accountId = $request->user()->account_id;

        return Inertia::render('Dashboard', [
            'stats' => [
                'openLeads' => Lead::forAccount($accountId)->where('status', 'open')->count(),
                'openValue' => (float) Lead::forAccount($accountId)->where('status', 'open')->sum('value'),
                'wonThisMonth' => Lead::forAccount($accountId)->where('status', 'won')
                    ->where('closed_at', '>=', now()->startOfMonth())->count(),
                'wonValueThisMonth' => (float) Lead::forAccount($accountId)->where('status', 'won')
                    ->where('closed_at', '>=', now()->startOfMonth())->sum('value'),
                'overdueTasks' => Task::forAccount($accountId)->overdue()->count(),
                'tasksToday' => Task::forAccount($accountId)->pending()
                    ->whereBetween('due_at', [now()->startOfDay(), now()->endOfDay()])->count(),
                // Regla Kommo: leads abiertos SIN tarea pendiente = leads olvidados.
                'leadsWithoutTask' => Lead::forAccount($accountId)->where('status', 'open')
                    ->whereDoesntHave('tasks', fn ($q) => $q->whereNull('completed_at'))->count(),
            ],
            'recentLeads' => Lead::forAccount($accountId)
                ->with(['contact:id,name,phone', 'stage:id,name,color'])
                ->latest()
                ->limit(6)
                ->get(['id', 'title', 'value', 'currency', 'status', 'contact_id', 'stage_id', 'created_at']),
            'myTasks' => Task::forAccount($accountId)
                ->pending()
                ->where('assigned_to', $request->user()->id)
                ->with('lead:id,title')
                ->orderBy('due_at')
                ->limit(6)
                ->get(),
            'currency' => $request->user()->account->default_currency,
        ]);
    }
}
