<?php

use App\Models\AppNotification;
use App\Models\Task;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Avisa al asignado cuando una tarea vence (una sola vez por tarea).
Artisan::command('tasks:notify-overdue', function () {
    $due = Task::overdue()
        ->whereNull('overdue_notified_at')
        ->whereNotNull('assigned_to')
        ->with('lead:id,title')
        ->limit(200)
        ->get();

    foreach ($due as $task) {
        AppNotification::notify(
            $task->account_id,
            $task->assigned_to,
            'task_overdue',
            'Tarea vencida',
            $task->text.($task->lead ? " — {$task->lead->title}" : ''),
            $task->lead_id,
        );

        $task->update(['overdue_notified_at' => now()]);
    }

    $this->info("Notificadas: {$due->count()}");
})->purpose('Notifica tareas vencidas a sus asignados');

Schedule::command('tasks:notify-overdue')->everyTenMinutes();

// Recordatorio diario a cada agente vía WhatsApp con sus tareas del día
// (requiere phone cargado en User + integración wacrm activa).
Schedule::command('komo:remind-daily-tasks')->dailyAt('08:00');
