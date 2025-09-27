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
        Schema::create('photo_tag_values', function (Blueprint $table) {
            $table->id();
            $table->foreignId('photo_id')->constrained()->onDelete('cascade');
            $table->foreignId('tag_id')->constrained()->onDelete('cascade');
            $table->string('value'); // Pode mudar para float() se quiser restringir
            $table->timestamps();

            $table->unique(['photo_id', 'tag_id']); // Impede tags duplicadas por pasta
        });
    }

    public function down()
    {
        Schema::dropIfExists('photo_tag_values');
    }

};
