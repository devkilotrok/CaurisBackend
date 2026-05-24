<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('rounds')) {
            Schema::create('rounds', function (Blueprint $table) {
                $table->bigIncrements('round_id');
                $table->unsignedBigInteger('game_id')->nullable();
                $table->unsignedBigInteger('room_id')->nullable();
                $table->unsignedInteger('round_number')->nullable();
                $table->json('announcements')->nullable();
                $table->json('obtained_tricks')->nullable();
                $table->json('distributed_cards')->nullable();
                $table->string('status', 50)->nullable();
                $table->timestamp('announcement_end_at')->nullable();
                $table->string('deck_hash', 128)->nullable();
                $table->json('results')->nullable();
                $table->unsignedBigInteger('trick_winner_id')->nullable();
                $table->timestamp('started_at')->nullable();
                $table->timestamp('finished_at')->nullable();
                $table->timestamps();

                $table->index(['room_id', 'round_number']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('rounds');
    }
};
