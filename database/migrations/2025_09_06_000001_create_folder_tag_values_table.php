<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateFolderTagValuesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('folder_tag_values', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('pastas_id');
            $table->unsignedBigInteger('tag_id');
            $table->string('value')->nullable();
            $table->timestamps();

            $table->foreign('pastas_id')->references('id')->on('pastas')->onDelete('cascade');
            $table->foreign('tag_id')->references('id')->on('tags')->onDelete('cascade');
            $table->unique(['pastas_id', 'tag_id']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('folder_tag_values');
    }
}
