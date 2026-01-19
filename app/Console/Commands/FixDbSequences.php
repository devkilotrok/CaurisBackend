<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class FixDbSequences extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'db:fix-sequences {--dry-run : Only show what would be done without making changes}';

    /**
     * The description of the console command.
     *
     * @var string
     */
    protected $description = 'Reset database AUTO_INCREMENT (MySQL) or Sequences (PostgreSQL) to the maximum current ID';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $driver = DB::getDriverName();
        $this->info("Database driver detected: {$driver}");

        $tables = [
            'users' => 'user_id',
            'rooms' => 'room_id',
            'room_players' => 'player_id', // Found in RoomPlayer model
            'transactions' => 'transaction_id',
            'games' => 'game_id',
            'rounds' => 'round_id',
            'scores' => 'score_id',
            'messages' => 'message_id',
            'room_invitations' => 'invitation_id',
            'friendships' => 'friendship_id',
            'friend_requests' => 'request_id',
            'player_replacements' => 'replacement_id',
            'player_disconnections' => 'id',
            'admin_messages' => 'id',
            'admin_logs' => 'log_id',
            'announcements' => 'announcement_id',
            'chat_conversations' => 'id',
            'chat_messages' => 'id',
            'contact_messages' => 'id',
            'email_verification_codes' => 'code_id',
        ];

        $dryRun = $this->option('dry-run');

        foreach ($tables as $table => $primaryKey) {
            if (!Schema::hasTable($table)) {
                $this->warn("Table '{$table}' does not exist, skipping.");
                continue;
            }

            // Verify column exists
            if (!Schema::hasColumn($table, $primaryKey)) {
                $this->error("Column '{$primaryKey}' does not exist in table '{$table}'.");
                continue;
            }

            $maxId = DB::table($table)->max($primaryKey);
            $nextId = ($maxId ?? 0) + 1;

            if ($driver === 'mysql') {
                $this->fixMysql($table, $nextId, $dryRun);
            } elseif ($driver === 'pgsql') {
                $this->fixPgsql($table, $primaryKey, $nextId, $dryRun);
            } else {
                $this->error("Driver '{$driver}' is not supported for sequence fixing.");
                return 1;
            }
        }

        $this->info('Done!');
        return 0;
    }

    private function fixMysql($table, $nextId, $dryRun)
    {
        $sql = "ALTER TABLE `{$table}` AUTO_INCREMENT = {$nextId}";
        if ($dryRun) {
            $this->line("[DRY RUN] Would run: {$sql}");
        } else {
            try {
                DB::statement($sql);
                $this->info("MySQL: Set AUTO_INCREMENT for '{$table}' to {$nextId}");
            } catch (\Exception $e) {
                $this->error("Failed to set AUTO_INCREMENT for '{$table}': " . $e->getMessage());
            }
        }
    }

    private function fixPgsql($table, $primaryKey, $nextId, $dryRun)
    {
        // Guess the sequence name (standard Laravel/Postgres naming)
        $seqName = "{$table}_{$primaryKey}_seq";
        
        // Try to get actual sequence name if it differs
        try {
            $actualSeq = DB::selectOne("SELECT pg_get_serial_sequence('{$table}', '{$primaryKey}') as seq");
            if ($actualSeq && $actualSeq->seq) {
                $seqName = $actualSeq->seq;
            }
        } catch (\Exception $e) {
            // Fallback to default naming
        }

        $sql = "SELECT setval('{$seqName}', {$nextId}, false)";
        if ($dryRun) {
            $this->line("[DRY RUN] Would run: {$sql}");
        } else {
            try {
                DB::statement($sql);
                $this->info("PostgreSQL: Reset sequence '{$seqName}' to {$nextId}");
            } catch (\Exception $e) {
                $this->error("Failed to reset sequence for '{$table}': " . $e->getMessage());
            }
        }
    }
}
