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
        Schema::create('convites', function (Blueprint $table) {
            $table->id();
            $table->string('email');
            $table->unsignedBigInteger('idUsuario');
            $table->foreign('idUsuario')->references('id')->on('usuarios');
            $table->unsignedBigInteger('idPasta');
            $table->foreign('idPasta')->references('id')->on('pastas');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('convites');
    }
};
