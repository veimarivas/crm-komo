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

// El envío de recordatorios por WhatsApp `komo:remind-daily-tasks` se dejó
// en el código pero NO se agenda: Meta cobra por conversaciones iniciadas
// desde el negocio fuera de la ventana de 24h (~$0.01-0.03 USD por agente
// por día en Bolivia) y además requiere un template aprobado. Preferimos
// las notificaciones in-app (AppNotification + tasks:notify-overdue arriba).
// Si más adelante se aprueba un template en Meta, reactivar acá.
