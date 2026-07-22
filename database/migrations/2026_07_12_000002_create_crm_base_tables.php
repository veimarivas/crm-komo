<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Base del CRM estilo Kommo: empresas, contactos (varios por empresa)
// y etiquetas polimórficas (aplican a leads, contactos y empresas).
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('companies', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('account_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('responsible_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('name');
            $table->string('phone', 32)->nullable();
            $table->string('email')->nullable();
            $table->string('website')->nullable();
            $table->text('address')->nullable();
            $table->timestamps();

            $table->index(['account_id', 'name']);
        });

        Schema::create('contacts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('account_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('company_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignUuid('responsible_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('name');
            $table->string('position')->nullable();
            $table->string('phone', 32)->nullable();
            // Clave de correlación con el wacrm (solo dígitos).
            $table->string('phone_normalized', 32)->nullable();
            $table->string('email')->nullable();
            // Ids espejo en el wacrm (se rellenan al sincronizar).
            $table->uuid('wacrm_contact_id')->nullable();
            $table->timestamps();

            $table->unique(['account_id', 'phone_normalized']);
            $table->index(['account_id', 'email']);
        });

        Schema::create('tags', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('account_id')->constrained()->cascadeOnDelete();
            $table->string('name', 60);
            $table->string('color', 20)->default('#10b981');
            $table->timestamp('created_at')->useCurrent();
        });

        // Polimórfica: una etiqueta puede aplicarse a leads, contactos y empresas.
        Schema::create('taggables', function (Blueprint $table) {
            $table->foreignUuid('tag_id')->constrained()->cascadeOnDelete();
            $table->uuidMorphs('taggable');
            $table->timestamp('created_at')->useCurrent();

            $table->primary(['tag_id', 'taggable_id', 'taggable_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('taggables');
        Schema::dropIfExists('tags');
        Schema::dropIfExists('contacts');
        Schema::dropIfExists('companies');
    }
};
