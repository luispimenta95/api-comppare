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
        Schema::create('pagamento_pix', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('idUsuario');
            $table->foreign('idUsuario')->references('id')->on('usuarios');
            $table->string('txid', 32)->unique();
            $table->string('numeroContrato', 20)->nullable();
            $table->string('pixCopiaECola', 500)->nullable();
            $table->decimal('valor', 10, 2);
            $table->string('chavePixRecebedor')->nullable();
            $table->string('nomeDevedor')->nullable();
            $table->string('cpfDevedor', 11)->nullable();
            $table->integer('locationId')->nullable();
            $table->string('recId')->nullable();
            $table->enum('status', ['ATIVA', 'CONCLUIDA', 'REMOVIDA_PELO_USUARIO_RECEBEDOR', 'REMOVIDA_PELO_PSP'])->default('ATIVA');
            $table->enum('statusPagamento', ['PENDENTE', 'PAGO', 'CANCELADO'])->default('PENDENTE');
            $table->date('dataInicial')->nullable();
            $table->date('dataFinal')->nullable();
            $table->enum('periodicidade', ['DIARIO', 'SEMANAL', 'MENSAL', 'BIMESTRAL', 'TRIMESTRAL', 'SEMESTRAL', 'ANUAL'])->nullable();
            $table->string('objeto')->nullable();
            $table->timestamp('dataVencimento')->nullable();
            $table->timestamp('dataPagamento')->nullable();
            $table->json('responseApiCompleta')->nullable();
            $table->timestamps();
            
            $table->index(['idUsuario', 'status']);
            $table->index(['txid']);
            $table->index(['statusPagamento']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pagamento_pix');
    }
};
