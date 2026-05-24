<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('tricks')) {
            Schema::create('tricks', function (Blueprint $table) {
                $table->bigIncrements('trick_id');
                $table->unsignedBigInteger('round_id');
                $table->integer('trick_number');
                $table->unsignedBigInteger('lead_player_id');
                $table->unsignedBigInteger('winner_player_id')->nullable();
                $table->json('cards_played')->nullable();
                $table->string('status', 20)->default('in_progress');
                $table->timestamp('finished_at')->nullable();
                $table->timestamps();

                $table->foreign('round_id')->references('round_id')->on('rounds')->onDelete('cascade');
                $table->index(['round_id', 'trick_number']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('tricks');
    }
};
