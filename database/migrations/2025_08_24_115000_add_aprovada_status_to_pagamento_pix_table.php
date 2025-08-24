<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Alterar o ENUM para incluir 'APROVADA'
        DB::statement("ALTER TABLE pagamento_pix MODIFY COLUMN status ENUM('ATIVA', 'APROVADA', 'CONCLUIDA', 'REMOVIDA_PELO_USUARIO_RECEBEDOR', 'REMOVIDA_PELO_PSP') DEFAULT 'ATIVA'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Reverter para o ENUM original (remover 'APROVADA')
        DB::statement("ALTER TABLE pagamento_pix MODIFY COLUMN status ENUM('ATIVA', 'CONCLUIDA', 'REMOVIDA_PELO_USUARIO_RECEBEDOR', 'REMOVIDA_PELO_PSP') DEFAULT 'ATIVA'");
    }
};
