<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('announcements')) {
            return;
        }

        // Supprimer les doublons éventuels (garder la plus récente par joueur/round)
        if (Schema::hasColumn('announcements', 'id')) {
            DB::statement('
                DELETE FROM announcements a
                USING announcements b
                WHERE a.id < b.id
                  AND a.game_id = b.game_id
                  AND a.round_number = b.round_number
                  AND a.player_id = b.player_id
            ');
        }

        Schema::table('announcements', function (Blueprint $table) {
            if (!$this->indexExists('announcements', 'announcements_game_round_player_unique')) {
                $table->unique(
                    ['game_id', 'round_number', 'player_id'],
                    'announcements_game_round_player_unique'
                );
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('announcements')) {
            return;
        }

        Schema::table('announcements', function (Blueprint $table) {
            if ($this->indexExists('announcements', 'announcements_game_round_player_unique')) {
                $table->dropUnique('announcements_game_round_player_unique');
            }
        });
    }

    private function indexExists(string $table, string $indexName): bool
    {
        $connection = Schema::getConnection();
        $driver = $connection->getDriverName();

        if ($driver === 'pgsql') {
            $result = DB::select(
                'SELECT 1 FROM pg_indexes WHERE tablename = ? AND indexname = ?',
                [$table, $indexName]
            );

            return !empty($result);
        }

        return false;
    }
};
