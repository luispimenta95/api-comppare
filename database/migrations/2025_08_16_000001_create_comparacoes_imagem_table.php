<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateComparacoesImagemTable extends Migration
{
    public function up()
    {
        Schema::create('comparacoes_imagem', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('id_usuario');
            $table->unsignedBigInteger('id_photo');
            $table->date('data_comparacao');
            $table->timestamps();

            $table->foreign('id_usuario')->references('id')->on('usuarios');
            $table->foreign('id_photo')->references('id')->on('photos');
        });
    }

    public function down()
    {
        Schema::dropIfExists('comparacoes_imagem');
    }
}
