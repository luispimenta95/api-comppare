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
        Schema::create('tags', function (Blueprint $table) {
            $table->id();
            $table->string('label'); // Descrição da tag, ex: "Peso Atual", "Status da Meta"
            $table->string('valor'); // Valor em texto (ex: "72.5kg", "Aprovado", "Em progresso")
            $table->boolean('status')->default(true);
            $table->unsignedBigInteger('idUsuarioCriador'); //
            $table->foreign('idUsuarioCriador')->references('id')->on('usuarios');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tags');
    }
};
