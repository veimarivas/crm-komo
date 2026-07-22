<?php

namespace App\Models;

use App\Models\Concerns\BelongsToAccount;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['account_id', 'name', 'is_default'])]
class Pipeline extends Model
{
    use BelongsToAccount, HasUuids;

    public const UPDATED_AT = null;

    protected function casts(): array
    {
        return ['is_default' => 'boolean'];
    }

    public function stages(): HasMany
    {
        return $this->hasMany(PipelineStage::class)->orderBy('position');
    }

    public function leads(): HasMany
    {
        return $this->hasMany(Lead::class);
    }

    /** Etapas terminales del pipeline (won / lost). */
    public function wonStage(): ?PipelineStage
    {
        return $this->stages()->where('stage_type', 'won')->first();
    }

    public function lostStage(): ?PipelineStage
    {
        return $this->stages()->where('stage_type', 'lost')->first();
    }
}
