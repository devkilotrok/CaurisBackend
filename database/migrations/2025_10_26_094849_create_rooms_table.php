<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('rooms')) {
            Schema::create('rooms', function (Blueprint $table) {
                $table->bigIncrements('room_id');
                $table->string('room_name', 100);
                $table->string('room_code', 6)->unique();
                $table->unsignedBigInteger('creator_id');
                $table->integer('minimum_bet')->default(50);
                $table->string('status', 20)->default('waiting');
                $table->integer('max_players')->default(4);
                $table->timestamps();
                $table->timestamp('started_at')->nullable();
                $table->timestamp('finished_at')->nullable();

                $table->foreign('creator_id')->references('user_id')->on('users')->onDelete('cascade');
                $table->index('room_code');
                $table->index('creator_id');
                $table->index('status');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('rooms');
    }
};
