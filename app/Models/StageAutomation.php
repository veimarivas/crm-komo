<?php

namespace App\Models;

use App\Models\Concerns\BelongsToAccount;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['account_id', 'stage_id', 'action_type', 'config', 'is_active', 'execution_count'])]
class StageAutomation extends Model
{
    use BelongsToAccount, HasUuids;

    public const UPDATED_AT = null;

    public const ACTIONS = ['send_whatsapp', 'create_task', 'add_note'];

    protected function casts(): array
    {
        return [
            'config' => 'array',
            'is_active' => 'boolean',
        ];
    }

    public function stage(): BelongsTo
    {
        return $this->belongsTo(PipelineStage::class, 'stage_id');
    }
}
