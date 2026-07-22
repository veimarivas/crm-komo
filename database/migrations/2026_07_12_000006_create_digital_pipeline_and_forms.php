<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Digital Pipeline (automatizaciones por etapa) + formularios web.
return new class extends Migration
{
    public function up(): void
    {
        // "Cuando un lead ENTRA a esta etapa, ejecuta esta acción."
        Schema::create('stage_automations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('account_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('stage_id')->constrained('pipeline_stages')->cascadeOnDelete();
            $table->string('action_type', 20); // send_whatsapp | create_task | add_note
            $table->json('config');
            $table->boolean('is_active')->default(true);
            $table->integer('execution_count')->default(0);
            $table->timestamp('created_at')->useCurrent();

            $table->index(['stage_id', 'is_active']);
        });

        // Formulario público embebible: cada envío crea contacto + lead.
        Schema::create('web_forms', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('account_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('pipeline_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('token', 32)->unique(); // va en la URL pública
            $table->string('headline')->nullable();
            $table->string('button_label', 60)->default('Enviar');
            $table->string('success_message')->default('¡Gracias! Te contactaremos pronto.');
            $table->boolean('is_active')->default(true);
            $table->integer('submissions_count')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('web_forms');
        Schema::dropIfExists('stage_automations');
    }
};
