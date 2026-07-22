<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Tareas con vencimiento (pieza central del método Kommo: ningún
// lead sin tarea pendiente) y notas polimórficas.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tasks', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('account_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('lead_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignUuid('contact_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignUuid('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('task_type', 20)->default('follow_up'); // call|meet|follow_up|email|other
            $table->text('text');
            $table->timestamp('due_at');
            $table->timestamp('completed_at')->nullable();
            $table->text('result_note')->nullable();
            $table->timestamps();

            $table->index(['account_id', 'assigned_to', 'completed_at', 'due_at'], 'tasks_agenda_idx');
            $table->index(['lead_id', 'completed_at']);
        });

        // Notas sobre leads, contactos o empresas.
        Schema::create('notes', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('account_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->uuidMorphs('noteable');
            $table->text('text');
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notes');
        Schema::dropIfExists('tasks');
    }
};
