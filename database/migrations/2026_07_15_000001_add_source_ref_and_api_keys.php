<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Integración con meta_ads (Fases 4-5 del ecosistema):
//  - leads.source_ref = ad_id del anuncio de Meta que originó el lead
//    (viene del referral Click-to-WhatsApp vía wacrm, o del Lead Ad
//    vía POST /api/v1/leads). Permite a meta_ads calcular ROAS.
//  - leads.meta_leadgen_id = idempotencia de Lead Ads reenviados.
//  - api_keys = API pública propia (patrón copiado del wacrm).
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->string('source_ref', 64)->nullable()->index()->after('source');
            $table->string('source_url', 2048)->nullable()->after('source_ref');
            $table->string('meta_leadgen_id', 64)->nullable()->unique()->after('source_url');
        });

        Schema::create('api_keys', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('account_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('name');
            $table->string('key_prefix', 40); // solo para mostrar, ej. "komo_live_a1b2c3d4"
            $table->string('key_hash', 64)->unique(); // SHA-256 hex de la clave completa
            $table->json('scopes');
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('expires_at')->nullable(); // NULL = no expira
            $table->timestamp('revoked_at')->nullable(); // NULL = activa
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('api_keys');

        Schema::table('leads', function (Blueprint $table) {
            $table->dropColumn(['source_ref', 'source_url', 'meta_leadgen_id']);
        });
    }
};
