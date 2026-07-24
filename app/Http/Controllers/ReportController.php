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
        $user = $request->user();
        $accountId = $user->account_id;
        $isAdmin = $user->hasRoleAtLeast(User::ROLE_ADMIN);

        // Scope Lead: admin ve todo, agent solo lo asignado a él.
        $leadScope = fn ($q) => $isAdmin ? $q : $q->where('responsible_user_id', $user->id);

        $pipelines = Pipeline::forAccount($accountId)->with('stages')->get();
        $selected = $pipelines->firstWhere('id', $request->query('pipeline'))
            ?? $pipelines->firstWhere('is_default', true)
            ?? $pipelines->first();

        // Embudo: leads abiertos por etapa del pipeline seleccionado.
        $funnel = $selected
            ? $selected->stages->where('stage_type', 'open')->values()->map(fn ($stage) => [
                'name' => $stage->name,
                'color' => $stage->color,
                'count' => $leadScope(Lead::where('stage_id', $stage->id)->where('status', 'open'))->count(),
                'value' => (float) $leadScope(Lead::where('stage_id', $stage->id)->where('status', 'open'))->sum('value'),
            ])
            : collect();

        // Ganados / perdidos por mes (últimos 6).
        $monthly = collect(range(5, 0))->map(function ($monthsAgo) use ($accountId, $leadScope) {
            $start = now()->subMonths($monthsAgo)->startOfMonth();
            $end = $start->copy()->endOfMonth();

            $base = fn (string $status) => $leadScope(Lead::forAccount($accountId)
                ->where('status', $status)
                ->whereBetween('closed_at', [$start, $end]));

            return [
                'month' => $start->translatedFormat('M Y'),
                'won' => $base('won')->count(),
                'wonValue' => (float) $base('won')->sum('value'),
                'lost' => $base('lost')->count(),
            ];
        });

        // Ranking del equipo: solo admin lo ve; agent no compara con otros.
        $byUser = $isAdmin
            ? User::where('account_id', $accountId)
                ->get(['id', 'name'])
                ->map(fn ($u) => [
                    'name' => $u->name,
                    'won' => Lead::forAccount($accountId)->where('status', 'won')
                        ->where('responsible_user_id', $u->id)
                        ->where('closed_at', '>=', now()->startOfMonth())->count(),
                    'wonValue' => (float) Lead::forAccount($accountId)->where('status', 'won')
                        ->where('responsible_user_id', $u->id)
                        ->where('closed_at', '>=', now()->startOfMonth())->sum('value'),
                    'open' => Lead::forAccount($accountId)->where('status', 'open')
                        ->where('responsible_user_id', $u->id)->count(),
                ])
                ->sortByDesc('wonValue')
                ->values()
            : collect();

        $totalWon = $leadScope(Lead::forAccount($accountId)->where('status', 'won'))->count();
        $totalLost = $leadScope(Lead::forAccount($accountId)->where('status', 'lost'))->count();

        // Conversión por fuente (whatsapp, web_form, lead_ad, manual, api, otros)
        $sourceLabels = [
            'whatsapp' => 'WhatsApp',
            'lead_ad' => 'Meta Lead Ad',
            'web_form' => 'Formulario web',
            'manual' => 'Manual',
            'api' => 'API externa',
        ];

        $bySource = collect(array_keys($sourceLabels))->push(null)->map(function ($source) use ($accountId, $leadScope, $sourceLabels) {
            $base = fn () => $leadScope(Lead::forAccount($accountId)
                ->when($source === null, fn ($q) => $q->whereNull('source')->orWhere('source', ''))
                ->when($source !== null, fn ($q) => $q->where('source', $source)));

            $total = $base()->count();
            $won = (clone $base())->where('status', 'won')->count();
            $lost = (clone $base())->where('status', 'lost')->count();
            $open = (clone $base())->where('status', 'open')->count();
            $wonValue = (float) (clone $base())->where('status', 'won')->sum('value');

            return [
                'source' => $source ?? 'other',
                'label' => $source ? $sourceLabels[$source] : 'Sin fuente',
                'total' => $total,
                'won' => $won,
                'lost' => $lost,
                'open' => $open,
                'won_value' => $wonValue,
                'conversion_rate' => ($won + $lost) > 0 ? round(($won / ($won + $lost)) * 100, 1) : 0,
            ];
        })->filter(fn ($s) => $s['total'] > 0)->sortByDesc('total')->values();

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
                    ? (float) $leadScope(Lead::forAccount($accountId)->where('status', 'won'))->avg('value')
                    : 0,
            ],
            'isAdmin' => $isAdmin,
            'bySource' => $bySource,
            'currency' => $user->account->default_currency,
        ]);
    }
}
