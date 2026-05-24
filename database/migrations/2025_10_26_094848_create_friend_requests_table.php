<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('friend_requests')) {
            Schema::create('friend_requests', function (Blueprint $table) {
                $table->bigIncrements('request_id');
                $table->unsignedBigInteger('sender_id');
                $table->unsignedBigInteger('receiver_id');
                $table->string('status', 20)->default('pending');
                $table->timestamps();

                $table->foreign('sender_id')->references('user_id')->on('users')->onDelete('cascade');
                $table->foreign('receiver_id')->references('user_id')->on('users')->onDelete('cascade');
                $table->index('sender_id');
                $table->index('receiver_id');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('friend_requests');
    }
};
