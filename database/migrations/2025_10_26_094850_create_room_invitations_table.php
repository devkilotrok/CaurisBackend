<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('room_invitations')) {
            Schema::create('room_invitations', function (Blueprint $table) {
                $table->bigIncrements('invitation_id');
                $table->unsignedBigInteger('room_id');
                $table->unsignedBigInteger('sender_id');
                $table->unsignedBigInteger('receiver_id');
                $table->string('status', 20)->default('pending');
                $table->string('message', 255)->nullable();
                $table->timestamps();

                $table->foreign('room_id')->references('room_id')->on('rooms')->onDelete('cascade');
                $table->foreign('sender_id')->references('user_id')->on('users')->onDelete('cascade');
                $table->foreign('receiver_id')->references('user_id')->on('users')->onDelete('cascade');
                $table->index('receiver_id');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('room_invitations');
    }
};
