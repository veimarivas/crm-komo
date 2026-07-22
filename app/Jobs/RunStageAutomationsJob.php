<?php

namespace App\Jobs;

use App\Models\Lead;
use App\Models\PipelineStage;
use App\Services\DigitalPipeline\Runner;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Ejecuta las automatizaciones de etapa en la cola: mover un lead en
 * el Kanban responde al instante aunque haya que llamar al wacrm.
 */
class RunStageAutomationsJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public function __construct(
        public readonly string $leadId,
        public readonly string $stageId,
    ) {
    }

    public function handle(Runner $runner): void
    {
        $lead = Lead::find($this->leadId);
        $stage = PipelineStage::find($this->stageId);

        // Solo si el lead sigue en esa etapa (pudo moverse de nuevo).
        if ($lead && $stage && $lead->stage_id === $stage->id) {
            $runner->leadEnteredStage($lead, $stage);
        }
    }
}
