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
        // Opção 1: Atualizar todos os usuários para PIX (já é o padrão)
        // Como o campo já tem default 'pix', usuários existentes já terão PIX
        
        // Opção 2: Atualizar baseado em alguma lógica específica
        // Exemplo: usuários que já fizeram pagamentos PIX ficam com PIX
        DB::statement("
            UPDATE usuarios 
            SET meioPagamento = 'pix' 
            WHERE id IN (
                SELECT DISTINCT idUsuario 
                FROM pagamento_pix 
                WHERE status IN ('APROVADA', 'PENDENTE')
            )
        ");
        
        // Opção 3: Usuários sem histórico de PIX ficam com cartão
        DB::statement("
            UPDATE usuarios 
            SET meioPagamento = 'cartao' 
            WHERE id NOT IN (
                SELECT DISTINCT idUsuario 
                FROM pagamento_pix 
                WHERE status IN ('APROVADA', 'PENDENTE')
            )
        ");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Reverter para o padrão PIX
        DB::statement("UPDATE usuarios SET meioPagamento = 'pix'");
    }
};
