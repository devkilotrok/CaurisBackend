<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Table pour suivre les remplacements de joueurs
        if (!Schema::hasTable('player_replacements')) {
            Schema::create('player_replacements', function (Blueprint $table) {
                $table->id('replacement_id');
                // Utiliser integer pour correspondre au type INT(11) de rooms.room_id (signé)
                $table->integer('room_id');
                $table->string('player_name', 50);
                $table->string('bot_name', 50);
                $table->boolean('is_permanent')->default(false);
                $table->timestamp('disconnected_at');
                $table->timestamp('restored_at')->nullable();
                $table->timestamps();
                
                $table->foreign('room_id')->references('room_id')->on('rooms')->onDelete('cascade');
                $table->index(['room_id', 'player_name']);
                $table->index('disconnected_at');
                $table->index('restored_at');
            });
        }

        // Table pour suivre les déconnexions
        if (!Schema::hasTable('player_disconnections')) {
            Schema::create('player_disconnections', function (Blueprint $table) {
                $table->id();
                // Utiliser integer pour correspondre au type INT(11) de rooms.room_id (signé)
                $table->integer('room_id');
                $table->string('player_name', 50);
                $table->timestamp('disconnected_at');
                $table->timestamp('reconnected_at')->nullable();
                $table->timestamps();
                
                $table->foreign('room_id')->references('room_id')->on('rooms')->onDelete('cascade');
                $table->index(['room_id', 'player_name']);
                $table->index('disconnected_at');
                $table->index('reconnected_at');
            });
        }

        // Ajouter les colonnes à room_players
        if (Schema::hasTable('room_players')) {
            Schema::table('room_players', function (Blueprint $table) {
                if (!Schema::hasColumn('room_players', 'is_replacement_bot')) {
                    $table->boolean('is_replacement_bot')->default(false);
                }
                if (!Schema::hasColumn('room_players', 'replaced_player_name')) {
                    $table->string('replaced_player_name', 50)->nullable();
                }
                if (!Schema::hasColumn('room_players', 'is_excluded')) {
                    $table->boolean('is_excluded')->default(false);
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Supprimer les colonnes ajoutées à room_players
        if (Schema::hasTable('room_players')) {
            Schema::table('room_players', function (Blueprint $table) {
                if (Schema::hasColumn('room_players', 'is_excluded')) {
                    $table->dropColumn('is_excluded');
                }
                if (Schema::hasColumn('room_players', 'replaced_player_name')) {
                    $table->dropColumn('replaced_player_name');
                }
                if (Schema::hasColumn('room_players', 'is_replacement_bot')) {
                    $table->dropColumn('is_replacement_bot');
                }
            });
        }

        Schema::dropIfExists('player_disconnections');
        Schema::dropIfExists('player_replacements');
    }
};
