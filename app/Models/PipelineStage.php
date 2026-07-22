<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['pipeline_id', 'name', 'color', 'position', 'stage_type'])]
class PipelineStage extends Model
{
    use HasUuids;

    public const UPDATED_AT = null;

    public const TYPE_OPEN = 'open';
    public const TYPE_WON = 'won';
    public const TYPE_LOST = 'lost';

    public function pipeline(): BelongsTo
    {
        return $this->belongsTo(Pipeline::class);
    }

    public function leads(): HasMany
    {
        return $this->hasMany(Lead::class, 'stage_id');
    }

    public function isTerminal(): bool
    {
        return $this->stage_type !== self::TYPE_OPEN;
    }
}
