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
        Schema::create('planos', function (Blueprint $table) {
            $table->id();
            $table->string('nome');
            $table->string('descricao');
            $table->float('valor');
            $table->integer('tempoGratuidade')->default(Helper::TEMPO_GRATUIDADE);
            $table->integer('quantidadeTags')->default(Helper::LIMITE_TAGS);
            $table->integer('quantidadeFotos')->default(Helper::LIMITE_FOTOS);
            $table->integer('quantidadePastas')->default(Helper::LIMITE_PASTAS);
            $table->boolean('status')->default(true);
            $table->integer('idHost');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('planos');
    }
};
