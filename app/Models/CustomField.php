<?php

namespace App\Models;

use App\Models\Concerns\BelongsToAccount;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['account_id', 'entity', 'name', 'field_type', 'options', 'position'])]
class CustomField extends Model
{
    use BelongsToAccount, HasUuids;

    public const UPDATED_AT = null;

    public const ENTITIES = ['lead', 'contact', 'company'];

    public const TYPES = ['text', 'number', 'date', 'select'];

    protected function casts(): array
    {
        return ['options' => 'array'];
    }
}
