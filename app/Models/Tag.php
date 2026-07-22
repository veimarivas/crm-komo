<?php

namespace App\Models;

use App\Models\Concerns\BelongsToAccount;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['account_id', 'name', 'color'])]
class Tag extends Model
{
    use BelongsToAccount, HasUuids;

    public const UPDATED_AT = null;
}
