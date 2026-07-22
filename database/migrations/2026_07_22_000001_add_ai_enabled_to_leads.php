<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            // true = IA responde automáticamente en el wacrm; false = solo humano.
            // Se sincroniza a `conversations.ai_autoreply_disabled` del wacrm.
            $table->boolean('ai_enabled')->default(true)->after('responsible_user_id');
        });
    }

    public function down(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->dropColumn('ai_enabled');
        });
    }
};
