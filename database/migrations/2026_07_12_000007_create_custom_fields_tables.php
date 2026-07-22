<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Campos personalizados por entidad (lead | contact | company),
// como en Kommo. Los valores son polimórficos.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('custom_fields', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('account_id')->constrained()->cascadeOnDelete();
            $table->string('entity', 10); // lead | contact | company
            $table->string('name', 60);
            $table->string('field_type', 10)->default('text'); // text | number | date | select
            $table->json('options')->nullable(); // opciones para select
            $table->integer('position')->default(0);
            $table->timestamp('created_at')->useCurrent();

            $table->index(['account_id', 'entity']);
        });

        Schema::create('custom_field_values', function (Blueprint $table) {
            $table->foreignUuid('custom_field_id')->constrained()->cascadeOnDelete();
            $table->uuidMorphs('fieldable');
            $table->text('value')->nullable();
            $table->timestamp('updated_at')->useCurrent();

            $table->primary(['custom_field_id', 'fieldable_id', 'fieldable_type'], 'cfv_primary');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('custom_field_values');
        Schema::dropIfExists('custom_fields');
    }
};
