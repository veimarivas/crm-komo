<?php

namespace App\Models;

use App\Models\Concerns\BelongsToAccount;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'account_id', 'pipeline_id', 'name', 'token', 'headline',
    'button_label', 'success_message', 'is_active', 'submissions_count',
])]
class WebForm extends Model
{
    use BelongsToAccount, HasUuids;

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function pipeline(): BelongsTo
    {
        return $this->belongsTo(Pipeline::class);
    }
}
