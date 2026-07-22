<?php

namespace App\Models;

use App\Models\Concerns\BelongsToAccount;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['account_id', 'token_hash', 'role', 'created_by_user_id', 'label', 'expires_at', 'accepted_at', 'accepted_by_user_id'])]
class AccountInvitation extends Model
{
    use BelongsToAccount, HasUuids;

    public const UPDATED_AT = null;

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'accepted_at' => 'datetime',
        ];
    }
}
