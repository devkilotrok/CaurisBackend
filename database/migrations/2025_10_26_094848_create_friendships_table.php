<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('friendships')) {
            Schema::create('friendships', function (Blueprint $table) {
                $table->bigIncrements('friendship_id');
                $table->unsignedBigInteger('user_id');
                $table->unsignedBigInteger('friend_id');
                $table->string('status', 20)->default('pending');
                $table->timestamps();

                $table->foreign('user_id')->references('user_id')->on('users')->onDelete('cascade');
                $table->foreign('friend_id')->references('user_id')->on('users')->onDelete('cascade');
                $table->unique(['user_id', 'friend_id']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('friendships');
    }
};
