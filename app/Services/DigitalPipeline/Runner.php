<?php

namespace App\Services\DigitalPipeline;

use App\Models\Lead;
use App\Models\PipelineStage;
use App\Models\StageAutomation;
use App\Models\Task;
use App\Services\Wacrm\Client;
use Illuminate\Support\Facades\Log;

/**
 * Digital Pipeline (el sello de Kommo): cuando un lead ENTRA a una
 * etapa, se ejecutan las acciones configuradas para esa etapa —
 * enviar WhatsApp, crear tarea de seguimiento, dejar nota.
 */
class Runner
{
    public function leadEnteredStage(Lead $lead, PipelineStage $stage): void
    {
        $automations = StageAutomation::forAccount($lead->account_id)
            ->where('stage_id', $stage->id)
            ->where('is_active', true)
            ->get();

        foreach ($automations as $automation) {
            try {
                $this->execute($automation, $lead);
                $automation->increment('execution_count');
            } catch (\Throwable $e) {
                Log::warning('Automatización de etapa falló', [
                    'automation_id' => $automation->id,
                    'lead_id' => $lead->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    private function execute(StageAutomation $automation, Lead $lead): void
    {
        $config = $automation->config;

        match ($automation->action_type) {
            'send_whatsapp' => $this->sendWhatsapp($lead, $config),
            'create_task' => $this->createTask($lead, $config),
            'add_note' => $this->addNote($lead, $config),
            default => throw new \RuntimeException("Acción desconocida: {$automation->action_type}"),
        };
    }

    private function sendWhatsapp(Lead $lead, array $config): void
    {
        $integration = $lead->account->integration;

        if (! $integration?->is_active) {
            throw new \RuntimeException('Integración con el wacrm inactiva.');
        }

        if (! $lead->contact?->phone) {
            throw new \RuntimeException('El lead no tiene contacto con teléfono.');
        }

        $text = $this->interpolate($config['text'] ?? '', $lead);

        Client::for($integration)->sendMessage(
            $lead->contact->phone_normalized ?? $lead->contact->phone,
            $text,
        );

        $lead->recordEvent('message_out', null, [
            'text' => mb_substr($text, 0, 500),
            'automation' => true,
        ]);
    }

    private function createTask(Lead $lead, array $config): void
    {
        $task = Task::create([
            'account_id' => $lead->account_id,
            'lead_id' => $lead->id,
            'contact_id' => $lead->contact_id,
            'assigned_to' => $config['assigned_to'] ?? $lead->responsible_user_id,
            'task_type' => $config['task_type'] ?? 'follow_up',
            'text' => $this->interpolate($config['text'] ?? 'Dar seguimiento', $lead),
            'due_at' => now()->addHours(max(1, (int) ($config['due_in_hours'] ?? 24))),
        ]);

        $lead->recordEvent('task_created', null, [
            'text' => $task->text,
            'due_at' => $task->due_at->toIso8601String(),
            'automation' => true,
        ]);
    }

    private function addNote(Lead $lead, array $config): void
    {
        $text = $this->interpolate($config['text'] ?? '', $lead);

        $lead->notes()->create([
            'account_id' => $lead->account_id,
            'text' => $text,
        ]);

        $lead->recordEvent('note_added', null, [
            'text' => mb_substr($text, 0, 200),
            'automation' => true,
        ]);
    }

    /** Tokens del lead disponibles en los textos configurables. */
    private function interpolate(string $text, Lead $lead): string
    {
        return strtr($text, [
            '{name}' => $lead->contact?->name ?? '',
            '{title}' => $lead->title,
            '{value}' => (string) $lead->value,
            '{stage}' => $lead->stage?->name ?? '',
        ]);
    }
}
