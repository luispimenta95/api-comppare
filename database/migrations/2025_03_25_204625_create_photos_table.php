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
        Schema::create('photos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pasta_id')->constrained()->onDelete('cascade');
            $table->string('path');
            $table->timestamp('taken_at')->nullable(); // data da foto
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('photos');
    }

};
