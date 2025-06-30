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
        Schema::create('code_email_verify', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('code', 6);
            $table->string('token');
            $table->timestamp('sent_at');
            $table->foreignId('user_id')->constrained('usuarios')->cascadeOnDelete()->cascadeOnUpdate();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('resend_available_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down()
    {
        Schema::dropIfExists('code_email_verify');
    }
};
