<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Conexión de esta cuenta con su instancia del wacrm (el CRM de
// WhatsApp). La integración es a nivel de cuenta:
//   - komo llama a la API pública del wacrm con la api_key.
//   - el wacrm entrega eventos al webhook de komo firmados con
//     webhook_secret (HMAC-SHA256), igual que hace con cualquier
//     otro receptor.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('integrations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('account_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('wacrm_url', 2048); // ej. http://localhost:8000
            $table->text('wacrm_api_key');     // cifrada — scopes: contacts, conversations, messages
            $table->text('webhook_secret')->nullable(); // cifrado — whsec_… del webhook saliente del wacrm
            $table->boolean('is_active')->default(false);
            $table->timestamp('last_sync_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('integrations');
    }
};
