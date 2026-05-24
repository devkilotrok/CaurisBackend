<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('room_players')) {
            Schema::create('room_players', function (Blueprint $table) {
                $table->bigIncrements('player_id');
                $table->unsignedBigInteger('room_id');
                $table->unsignedBigInteger('user_id');
                $table->integer('position');
                $table->boolean('is_creator')->default(false);
                $table->string('status', 20)->default('waiting');
                $table->timestamp('joined_at')->nullable();
                $table->timestamps();

                $table->unique(['room_id', 'position']);
                $table->index('room_id');
                $table->index('user_id');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('room_players');
    }
};
