<?php

use App\Http\Util\Helper;
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
        Schema::create('usuarios', function (Blueprint $table) {
            $table->id();
            $table->string('primeiroNome');
            $table->string('sobrenome');
            $table->string('apelido')->nullable();
            $table->string('cpf')->unique();
            $table->string('senha');
            $table->string('email');
            $table->string('telefone');
            $table->date('dataNascimento');
            $table->boolean('status')->default(true);
            $table->timestamp('dataLimiteCompra');
            $table->timestamp('dataUltimoPagamento')->nullable();
            $table->integer('idUltimaCobranca')->nullable();
            $table->integer('idAssinatura')->nullable();
            $table->integer('pastasCriadas')->default(0);
            $table->integer('pontos')->default(0);
            $table->integer('quantidadeConvites')->default(0);
            $table->timestamp('ultimoAcesso')->nullable();
            $table->unsignedBigInteger('idPlano');
            $table->foreign('idPlano')->references('id')->on('planos');
            $table->unsignedBigInteger('idPerfil')->default(Helper::ID_PERFIL_USUARIO);
            $table->foreign('idPerfil')->references('id')->on('perfil');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('usuarios');
    }
};
