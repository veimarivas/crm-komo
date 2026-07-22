<?php

namespace App\Models;

use App\Models\Concerns\BelongsToAccount;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Notificaciones in-app. Se llama AppNotification (tabla
 * app_notifications) para no chocar con las database notifications
 * nativas de Laravel.
 */
#[Fillable(['account_id', 'user_id', 'type', 'lead_id', 'title', 'body', 'read_at'])]
class AppNotification extends Model
{
    use BelongsToAccount, HasUuids;

    public const UPDATED_AT = null;

    protected function casts(): array
    {
        return ['read_at' => 'datetime'];
    }

    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    /** Helper de creación con guard: nunca notificarse a uno mismo. */
    public static function notify(
        string $accountId,
        ?string $userId,
        string $type,
        string $title,
        ?string $body = null,
        ?string $leadId = null,
        ?string $actorId = null,
    ): void {
        if (! $userId || $userId === $actorId) {
            return;
        }

        static::create([
            'account_id' => $accountId,
            'user_id' => $userId,
            'type' => $type,
            'lead_id' => $leadId,
            'title' => $title,
            'body' => $body,
        ]);
    }
}
