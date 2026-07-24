<?php

namespace App\Console\Commands;

use App\Models\Integration;
use App\Models\Task;
use App\Models\User;
use App\Services\Wacrm\Client;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Cada mañana envía un WhatsApp a cada agente que tenga tareas pendientes
 * para hoy o vencidas, con un resumen. Requiere que el agente tenga phone
 * cargado y que la cuenta tenga la integración con wacrm activa.
 *
 * Se agenda `Schedule::command('komo:remind-daily-tasks')->dailyAt('08:00')`.
 * Uso manual: `php artisan komo:remind-daily-tasks [--dry-run]`
 */
class RemindDailyTasks extends Command
{
    protected $signature = 'komo:remind-daily-tasks {--dry-run}';
    protected $description = 'Envía WhatsApp diario a cada agente con sus tareas del día';

    public function handle(): int
    {
        $usersWithTasks = User::whereNotNull('phone')
            ->whereHas('tasks', fn ($q) => $q->whereNull('completed_at'))
            ->with(['tasks' => fn ($q) => $q->whereNull('completed_at')
                ->where(fn ($x) => $x->whereDate('due_at', today())->orWhere('due_at', '<', now()))
                ->with('lead:id,title')
                ->orderBy('due_at')
                ->limit(20)])
            ->get();

        $this->info("Agentes con tareas para hoy o vencidas: {$usersWithTasks->count()}");

        foreach ($usersWithTasks as $user) {
            $tasks = $user->tasks;
            if ($tasks->isEmpty()) continue;

            $integration = Integration::forAccount($user->account_id)->first();
            if (! $integration || ! $integration->wacrm_url || ! $integration->wacrm_api_key) {
                $this->warn("  ⚠ {$user->name}: sin integración wacrm");
                continue;
            }

            $summary = $this->buildSummary($user, $tasks);
            $this->line("  → {$user->name} ({$user->phone}): {$tasks->count()} tareas");

            if ($this->option('dry-run')) {
                $this->line($summary);
                continue;
            }

            try {
                Client::for($integration)->sendMessage($user->phone, $summary);
            } catch (\Throwable $e) {
                Log::warning('Recordatorio de tareas falló', ['user_id' => $user->id, 'error' => $e->getMessage()]);
                $this->error("     ❌ falló: {$e->getMessage()}");
            }
        }

        $this->info('✅ Recordatorios enviados.');
        return self::SUCCESS;
    }

    private function buildSummary(User $user, $tasks): string
    {
        $firstName = explode(' ', trim($user->name))[0];
        $lines = ["¡Buen día {$firstName}! 👋", '', "Hoy tenés {$tasks->count()} tarea(s) pendientes:", ''];

        $iconMap = ['call' => '📞', 'meet' => '🤝', 'follow_up' => '🔔', 'email' => '✉️', 'other' => '📌'];

        foreach ($tasks as $t) {
            $icon = $iconMap[$t->task_type] ?? '📌';
            $due = \Carbon\Carbon::parse($t->due_at);
            $when = $due->isToday() ? $due->format('H:i') : ($due->isPast() ? '⚠️ vencida (' . $due->diffForHumans() . ')' : $due->format('d/m H:i'));
            $lead = $t->lead ? " · {$t->lead->title}" : '';
            $lines[] = "{$icon} {$t->text} — {$when}{$lead}";
        }

        $lines[] = '';
        $lines[] = 'Buena jornada 🚀';

        return implode("\n", $lines);
    }
}
