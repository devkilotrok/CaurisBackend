<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('games')) {
            Schema::create('games', function (Blueprint $table) {
                $table->bigIncrements('game_id');
                $table->unsignedBigInteger('room_id');
                $table->string('deck_id', 100);
                $table->timestamp('started_at')->nullable();
                $table->timestamp('finished_at')->nullable();
                $table->unsignedBigInteger('winner_id')->nullable();
                $table->json('final_scores')->nullable();
                $table->timestamps();

                $table->index('room_id');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('games');
    }
};
