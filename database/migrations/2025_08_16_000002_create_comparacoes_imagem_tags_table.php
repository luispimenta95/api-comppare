<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateComparacoesImagemTagsTable extends Migration
{
    public function up()
    {
        Schema::create('comparacoes_imagem_tags', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('id_comparacao');
            $table->unsignedBigInteger('id_tag');
            $table->string('valor');
            $table->timestamps();

            $table->foreign('id_comparacao')->references('id')->on('comparacoes_imagem')->onDelete('cascade');
            $table->foreign('id_tag')->references('id')->on('tags');
        });
    }

    public function down()
    {
        Schema::dropIfExists('comparacoes_imagem_tags');
    }
}
