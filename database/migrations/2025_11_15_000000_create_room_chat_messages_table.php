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
        Schema::create('room_chat_messages', function (Blueprint $table) {
            $table->bigIncrements('id');
            // Utiliser integer pour correspondre au type INT(11) de rooms.room_id (signé)
            $table->integer('room_id');
            // Utiliser integer pour correspondre au type INT(11) de users.user_id (signé)
            $table->integer('user_id');
            $table->enum('message_type', ['text', 'preset', 'emoji'])->default('text');
            $table->string('preset_code')->nullable();
            $table->text('message');
            $table->timestamps();

            $table->foreign('room_id')->references('room_id')->on('rooms')->onDelete('cascade');
            $table->foreign('user_id')->references('user_id')->on('users')->onDelete('cascade');
            $table->index(['room_id', 'id']);
            $table->index(['room_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('room_chat_messages');
    }
};


