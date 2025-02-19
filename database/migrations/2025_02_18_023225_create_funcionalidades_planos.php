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
        Schema::create('funcionalidades_planos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('funcionalidade_id')->constrained('funcionalidades');
            $table->foreignId('plano_id')->constrained('planos');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('funcionalidades_planos');
    }
};
