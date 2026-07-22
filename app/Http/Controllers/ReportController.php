<?php

namespace App\Http\Controllers;

use App\Models\Lead;
use App\Models\Pipeline;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ReportController extends Controller
{
    public function index(Request $request): Response
    {
        $accountId = $request->user()->account_id;

        $pipelines = Pipeline::forAccount($accountId)->with('stages')->get();
        $selected = $pipelines->firstWhere('id', $request->query('pipeline'))
            ?? $pipelines->firstWhere('is_default', true)
            ?? $pipelines->first();

        // Embudo: leads abiertos por etapa del pipeline seleccionado.
        $funnel = $selected
            ? $selected->stages->where('stage_type', 'open')->values()->map(fn ($stage) => [
                'name' => $stage->name,
                'color' => $stage->color,
                'count' => Lead::where('stage_id', $stage->id)->where('status', 'open')->count(),
                'value' => (float) Lead::where('stage_id', $stage->id)->where('status', 'open')->sum('value'),
            ])
            : collect();

        // Ganados / perdidos por mes (últimos 6).
        $monthly = collect(range(5, 0))->map(function ($monthsAgo) use ($accountId) {
            $start = now()->subMonths($monthsAgo)->startOfMonth();
            $end = $start->copy()->endOfMonth();

            $base = fn (string $status) => Lead::forAccount($accountId)
                ->where('status', $status)
                ->whereBetween('closed_at', [$start, $end]);

            return [
                'month' => $start->translatedFormat('M Y'),
                'won' => $base('won')->count(),
                'wonValue' => (float) $base('won')->sum('value'),
                'lost' => $base('lost')->count(),
            ];
        });

        // Ranking del equipo este mes.
        $byUser = User::where('account_id', $accountId)
            ->get(['id', 'name'])
            ->map(fn ($user) => [
                'name' => $user->name,
                'won' => Lead::forAccount($accountId)->where('status', 'won')
                    ->where('responsible_user_id', $user->id)
                    ->where('closed_at', '>=', now()->startOfMonth())->count(),
                'wonValue' => (float) Lead::forAccount($accountId)->where('status', 'won')
                    ->where('responsible_user_id', $user->id)
                    ->where('closed_at', '>=', now()->startOfMonth())->sum('value'),
                'open' => Lead::forAccount($accountId)->where('status', 'open')
                    ->where('responsible_user_id', $user->id)->count(),
            ])
            ->sortByDesc('wonValue')
            ->values();

        $totalWon = Lead::forAccount($accountId)->where('status', 'won')->count();
        $totalLost = Lead::forAccount($accountId)->where('status', 'lost')->count();

        return Inertia::render('Reports/Index', [
            'pipelines' => $pipelines->map(fn ($p) => ['id' => $p->id, 'name' => $p->name]),
            'pipelineId' => $selected?->id,
            'funnel' => $funnel,
            'monthly' => $monthly,
            'byUser' => $byUser,
            'conversion' => [
                'won' => $totalWon,
                'lost' => $totalLost,
                'rate' => ($totalWon + $totalLost) > 0 ? round($totalWon / ($totalWon + $totalLost) * 100) : 0,
                'avgTicket' => $totalWon > 0
                    ? (float) Lead::forAccount($accountId)->where('status', 'won')->avg('value')
                    : 0,
            ],
            'currency' => $request->user()->account->default_currency,
        ]);
    }
}
