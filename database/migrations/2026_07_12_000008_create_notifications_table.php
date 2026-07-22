<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('app_notifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('account_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete(); // destinatario
            // lead_assigned | lead_created_whatsapp | lead_created_web_form | task_overdue
            $table->string('type', 30);
            $table->foreignUuid('lead_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->string('body')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['user_id', 'read_at']);
        });

        // Marca para no notificar dos veces la misma tarea vencida.
        Schema::table('tasks', function (Blueprint $table) {
            $table->timestamp('overdue_notified_at')->nullable()->after('completed_at');
        });
    }

    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->dropColumn('overdue_notified_at');
        });
        Schema::dropIfExists('app_notifications');
    }
};
