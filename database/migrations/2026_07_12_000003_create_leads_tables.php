<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// El corazón del modelo Kommo: leads con pipeline propio.
// A diferencia del wacrm (deals), aquí las etapas tienen TIPO
// (open/won/lost) y el estado del lead se deriva de su etapa.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pipelines', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('account_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->boolean('is_default')->default(false);
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::create('pipeline_stages', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('pipeline_id')->constrained()->cascadeOnDelete();
            $table->string('name', 60);
            $table->string('color', 20)->default('#3b82f6');
            $table->integer('position')->default(0);
            // open = etapa normal; won/lost = etapas terminales fijas
            // (el modelo Kommo: "Closed – won" / "Closed – lost").
            $table->string('stage_type', 10)->default('open');
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::create('leads', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('account_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('pipeline_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('stage_id')->constrained('pipeline_stages');
            $table->foreignUuid('contact_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignUuid('company_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignUuid('responsible_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('title');
            $table->decimal('value', 12, 2)->default(0);
            $table->string('currency', 3)->default('USD');
            // manual | web_form | whatsapp | api
            $table->string('source', 20)->default('manual');
            // Derivado del stage_type de su etapa; denormalizado para filtrar rápido.
            $table->string('status', 10)->default('open'); // open | won | lost
            $table->timestamp('closed_at')->nullable();
            // Vínculo con el wacrm: conversación de WhatsApp asociada.
            $table->uuid('wacrm_conversation_id')->nullable();
            $table->timestamps();

            $table->index(['account_id', 'status']);
            $table->index(['pipeline_id', 'stage_id']);
        });

        // Historial del lead: timeline de todo lo que le pasa. Aquí
        // también aterrizan los mensajes de WhatsApp que llegan del
        // wacrm vía webhook (event_type message_in / message_out).
        Schema::create('lead_events', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('lead_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('account_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('user_id')->nullable()->constrained('users')->nullOnDelete();
            // created|stage_changed|won|lost|note_added|task_created|
            // task_completed|message_in|message_out|value_changed|reopened
            $table->string('event_type', 30);
            $table->json('payload');
            $table->timestamp('created_at')->useCurrent();

            $table->index(['lead_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lead_events');
        Schema::dropIfExists('leads');
        Schema::dropIfExists('pipeline_stages');
        Schema::dropIfExists('pipelines');
    }
};
