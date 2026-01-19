<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * This migration renames generic 'id' columns to specific primary keys
     * for tables created on 2025-10-26, to satisfy legacy FK dependencies.
     */
    public function up(): void
    {
        $renames = [
            'friend_requests' => 'request_id',
            'friendships' => 'friendship_id',
            'games' => 'game_id',
            'rooms' => 'room_id',
            'rounds' => 'round_id',
            'room_invitations' => 'invitation_id',
            'scores' => 'score_id',
            'tricks' => 'trick_id',
            'played_cards' => 'played_card_id',
        ];

        foreach ($renames as $table => $newId) {
            if (Schema::hasTable($table)) {
                Schema::table($table, function (Blueprint $tableObj) use ($table, $newId) {
                    if (Schema::hasColumn($table, 'id') && !Schema::hasColumn($table, $newId)) {
                        $tableObj->renameColumn('id', $newId);
                    }
                });
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // No down needed for emergency bridge
    }
};
