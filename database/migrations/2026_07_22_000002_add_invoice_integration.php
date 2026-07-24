<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Fase 4 con Invoice: el komo ahora también se integra con Komo Invoice
// para el botón "Cotizar" en Leads/Show.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('integrations', function (Blueprint $table) {
            $table->string('invoice_url', 2048)->nullable()->after('webhook_secret');
            $table->text('invoice_api_key')->nullable()->after('invoice_url');
        });
    }

    public function down(): void
    {
        Schema::table('integrations', function (Blueprint $table) {
            $table->dropColumn(['invoice_url', 'invoice_api_key']);
        });
    }
};
