<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Fase 4 con Invoice: el lead ganado ahora puede tener revenue REAL
// cobrado (invoice.amount_paid_cents) además del value estimado que
// el vendedor escribió a mano. Invoice actualiza esto via PATCH.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->unsignedBigInteger('invoiced_cents')->default(0)->after('value');
            $table->unsignedBigInteger('collected_cents')->default(0)->after('invoiced_cents');
        });
    }

    public function down(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->dropColumn(['invoiced_cents', 'collected_cents']);
        });
    }
};
