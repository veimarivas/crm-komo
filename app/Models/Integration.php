<?php

namespace App\Models;

use App\Models\Concerns\BelongsToAccount;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/**
 * Conexión de la cuenta con su instancia del wacrm (CRM de WhatsApp).
 * komo → wacrm: API pública con wacrm_api_key.
 * wacrm → komo: webhooks firmados verificados con webhook_secret.
 */
#[Fillable([
    'account_id', 'wacrm_url', 'wacrm_api_key', 'webhook_secret',
    'invoice_url', 'invoice_api_key',
    'is_active', 'last_sync_at',
])]
class Integration extends Model
{
    use BelongsToAccount, HasUuids;

    protected function casts(): array
    {
        return [
            'wacrm_api_key' => 'encrypted',
            'webhook_secret' => 'encrypted',
            'invoice_api_key' => 'encrypted',
            'is_active' => 'boolean',
            'last_sync_at' => 'datetime',
        ];
    }

    public function invoiceBaseUrl(): ?string
    {
        return $this->invoice_url ? rtrim($this->invoice_url, '/') : null;
    }

    /** URL base normalizada (sin slash final). */
    public function baseUrl(): string
    {
        return rtrim($this->wacrm_url, '/');
    }
}
