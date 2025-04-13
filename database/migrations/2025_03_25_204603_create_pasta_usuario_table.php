<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::create('pasta_usuario', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pasta_id')->constrained()->onDelete('cascade'); // Chave estrangeira para pastas
            $table->foreignId('usuario_id')->constrained()->onDelete('cascade'); // Chave estrangeira para usuários
            $table->timestamps();

            $table->unique(['pasta_id', 'usuario_id']); // Garantir que cada combinação pasta/usuario seja única
        });
    }

    public function down()
    {
        Schema::dropIfExists('pasta_usuario');
    }

};
