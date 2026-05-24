<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('announcements')) {
            Schema::create('announcements', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('game_id');
                $table->integer('round_number');
                $table->unsignedBigInteger('player_id');
                $table->unsignedBigInteger('user_id');
                $table->integer('announcement_value');
                $table->timestamps();

                $table->index(['game_id', 'round_number']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('announcements');
    }
};
