<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public $withinTransaction = false;

    public function up(): void
    {
        if (!Schema::hasTable('room_players')) {
            return;
        }

        if (Schema::hasColumn('room_players', 'id') && !Schema::hasColumn('room_players', 'player_id')) {
            if (DB::getDriverName() === 'pgsql') {
                DB::statement('ALTER TABLE room_players RENAME COLUMN id TO player_id');
            } elseif (DB::getDriverName() === 'mysql') {
                DB::statement('ALTER TABLE room_players CHANGE COLUMN id player_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT');
            }
        }
    }

    public function down(): void
    {
        // Pas de rollback
    }
};
