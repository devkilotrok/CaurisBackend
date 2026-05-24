<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('scores')) {
            Schema::create('scores', function (Blueprint $table) {
                $table->bigIncrements('score_id');
                $table->unsignedBigInteger('game_id');
                $table->unsignedBigInteger('round_id')->nullable();
                $table->unsignedBigInteger('player_id');
                $table->unsignedBigInteger('user_id');
                $table->integer('announcement')->default(0);
                $table->integer('tricks_won')->default(0);
                $table->integer('round_score')->default(0);
                $table->integer('cumulative_score')->default(0);
                $table->timestamps();

                $table->index('game_id');
                $table->index('player_id');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('scores');
    }
};
