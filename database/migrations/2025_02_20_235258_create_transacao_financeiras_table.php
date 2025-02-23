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
        Schema::create('transacao_financeiras', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('idPlano'); //
            $table->foreign('idPlano')->references('id')->on('planos');
            $table->unsignedBigInteger('idUsuario'); //
            $table->foreign('idUsuario')->references('id')->on('usuarios');
            $table->string('formaPagamento')->nullable();
            $table->float('valorPlano');
            $table->float('valorFinalPago')->nullable();
            $table->string('idPagamento');
            $table->bolean('pagamentoEfetuado')->default(false);

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('transacao_financeiras');
    }
};
