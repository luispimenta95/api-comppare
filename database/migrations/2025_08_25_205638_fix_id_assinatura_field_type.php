<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Adicionar o campo idAssinatura como string
        Schema::table('usuarios', function (Blueprint $table) {
            $table->string('idAssinatura', 100)->nullable()->after('idUltimaCobranca');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Remover o campo caso necessário
        Schema::table('usuarios', function (Blueprint $table) {
            $table->dropColumn('idAssinatura');
        });
    }
};
