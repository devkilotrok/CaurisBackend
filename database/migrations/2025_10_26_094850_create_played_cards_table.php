<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('played_cards')) {
            Schema::create('played_cards', function (Blueprint $table) {
                $table->bigIncrements('played_card_id');
                $table->unsignedBigInteger('trick_id');
                $table->unsignedBigInteger('player_id');
                $table->string('card_code', 10);
                $table->string('card_value', 10);
                $table->string('card_suit', 10);
                $table->timestamp('played_at')->nullable();
                $table->timestamps();

                $table->index('trick_id');
                $table->index('player_id');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('played_cards');
    }
};
