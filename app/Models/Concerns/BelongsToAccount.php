<?php

namespace App\Models\Concerns;

use App\Models\Account;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Scoping multi-tenant por cuenta (mismo patrón que el wacrm).
 * Toda query de datos de tenant DEBE pasar por forAccount().
 */
trait BelongsToAccount
{
    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function scopeForAccount(Builder $query, Account|string $account): Builder
    {
        $accountId = $account instanceof Account ? $account->id : $account;

        return $query->where($this->qualifyColumn('account_id'), $accountId);
    }
}
