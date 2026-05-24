<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * PostgreSQL : ne pas annuler toute la migration si une FK échoue déjà.
     */
    public $withinTransaction = false;

    private function addColumnIfMissing(string $table, string $column, callable $callback): void
    {
        if (Schema::hasTable($table) && !Schema::hasColumn($table, $column)) {
            Schema::table($table, $callback);
        }
    }

    private function addForeignIfMissing(string $table, string $column, string $refTable, string $refColumn, string $onDelete = 'cascade'): void
    {
        if (!Schema::hasTable($table) || !Schema::hasColumn($table, $column)) {
            return;
        }
        try {
            Schema::table($table, function (Blueprint $blueprint) use ($column, $refTable, $refColumn, $onDelete) {
                $blueprint->foreign($column)->references($refColumn)->on($refTable)->onDelete($onDelete);
            });
        } catch (\Throwable $e) {
            // FK peut déjà exister ou types incompatibles sur anciennes bases
        }
    }

    public function up(): void
    {
        // --- friendships ---
        $this->addColumnIfMissing('friendships', 'user_id', fn (Blueprint $t) => $t->unsignedBigInteger('user_id')->nullable());
        $this->addColumnIfMissing('friendships', 'friend_id', fn (Blueprint $t) => $t->unsignedBigInteger('friend_id')->nullable());
        $this->addColumnIfMissing('friendships', 'status', fn (Blueprint $t) => $t->string('status', 20)->default('pending'));

        // --- friend_requests ---
        $this->addColumnIfMissing('friend_requests', 'sender_id', fn (Blueprint $t) => $t->unsignedBigInteger('sender_id')->nullable());
        $this->addColumnIfMissing('friend_requests', 'receiver_id', fn (Blueprint $t) => $t->unsignedBigInteger('receiver_id')->nullable());
        $this->addColumnIfMissing('friend_requests', 'status', fn (Blueprint $t) => $t->string('status', 20)->default('pending'));

        // --- games ---
        $this->addColumnIfMissing('games', 'room_id', fn (Blueprint $t) => $t->unsignedBigInteger('room_id')->nullable());
        $this->addColumnIfMissing('games', 'deck_id', fn (Blueprint $t) => $t->string('deck_id', 100)->nullable());
        $this->addColumnIfMissing('games', 'started_at', fn (Blueprint $t) => $t->timestamp('started_at')->nullable());
        $this->addColumnIfMissing('games', 'finished_at', fn (Blueprint $t) => $t->timestamp('finished_at')->nullable());
        $this->addColumnIfMissing('games', 'winner_id', fn (Blueprint $t) => $t->unsignedBigInteger('winner_id')->nullable());
        $this->addColumnIfMissing('games', 'final_scores', fn (Blueprint $t) => $t->json('final_scores')->nullable());

        // --- announcements ---
        $this->addColumnIfMissing('announcements', 'game_id', fn (Blueprint $t) => $t->unsignedBigInteger('game_id')->nullable());
        $this->addColumnIfMissing('announcements', 'round_number', fn (Blueprint $t) => $t->integer('round_number')->nullable());
        $this->addColumnIfMissing('announcements', 'player_id', fn (Blueprint $t) => $t->unsignedBigInteger('player_id')->nullable());
        $this->addColumnIfMissing('announcements', 'user_id', fn (Blueprint $t) => $t->unsignedBigInteger('user_id')->nullable());
        $this->addColumnIfMissing('announcements', 'announcement_value', fn (Blueprint $t) => $t->integer('announcement_value')->nullable());

        // --- rounds ---
        $this->addColumnIfMissing('rounds', 'game_id', fn (Blueprint $t) => $t->unsignedBigInteger('game_id')->nullable());
        $this->addColumnIfMissing('rounds', 'room_id', fn (Blueprint $t) => $t->unsignedBigInteger('room_id')->nullable());
        $this->addColumnIfMissing('rounds', 'round_number', fn (Blueprint $t) => $t->unsignedInteger('round_number')->nullable());
        $this->addColumnIfMissing('rounds', 'announcements', fn (Blueprint $t) => $t->json('announcements')->nullable());
        $this->addColumnIfMissing('rounds', 'obtained_tricks', fn (Blueprint $t) => $t->json('obtained_tricks')->nullable());
        $this->addColumnIfMissing('rounds', 'distributed_cards', fn (Blueprint $t) => $t->json('distributed_cards')->nullable());
        $this->addColumnIfMissing('rounds', 'status', fn (Blueprint $t) => $t->string('status', 50)->nullable());
        $this->addColumnIfMissing('rounds', 'announcement_end_at', fn (Blueprint $t) => $t->timestamp('announcement_end_at')->nullable());
        $this->addColumnIfMissing('rounds', 'deck_hash', fn (Blueprint $t) => $t->string('deck_hash', 128)->nullable());
        $this->addColumnIfMissing('rounds', 'results', fn (Blueprint $t) => $t->json('results')->nullable());
        $this->addColumnIfMissing('rounds', 'trick_winner_id', fn (Blueprint $t) => $t->unsignedBigInteger('trick_winner_id')->nullable());
        $this->addColumnIfMissing('rounds', 'started_at', fn (Blueprint $t) => $t->timestamp('started_at')->nullable());
        $this->addColumnIfMissing('rounds', 'finished_at', fn (Blueprint $t) => $t->timestamp('finished_at')->nullable());

        // --- tricks ---
        $this->addColumnIfMissing('tricks', 'round_id', fn (Blueprint $t) => $t->unsignedBigInteger('round_id')->nullable());
        $this->addColumnIfMissing('tricks', 'trick_number', fn (Blueprint $t) => $t->integer('trick_number')->nullable());
        $this->addColumnIfMissing('tricks', 'lead_player_id', fn (Blueprint $t) => $t->unsignedBigInteger('lead_player_id')->nullable());
        $this->addColumnIfMissing('tricks', 'winner_player_id', fn (Blueprint $t) => $t->unsignedBigInteger('winner_player_id')->nullable());
        $this->addColumnIfMissing('tricks', 'cards_played', fn (Blueprint $t) => $t->json('cards_played')->nullable());
        $this->addColumnIfMissing('tricks', 'status', fn (Blueprint $t) => $t->string('status', 20)->default('in_progress'));
        $this->addColumnIfMissing('tricks', 'finished_at', fn (Blueprint $t) => $t->timestamp('finished_at')->nullable());

        // --- played_cards ---
        $this->addColumnIfMissing('played_cards', 'trick_id', fn (Blueprint $t) => $t->unsignedBigInteger('trick_id')->nullable());
        $this->addColumnIfMissing('played_cards', 'player_id', fn (Blueprint $t) => $t->unsignedBigInteger('player_id')->nullable());
        $this->addColumnIfMissing('played_cards', 'card_code', fn (Blueprint $t) => $t->string('card_code', 10)->nullable());
        $this->addColumnIfMissing('played_cards', 'card_value', fn (Blueprint $t) => $t->string('card_value', 10)->nullable());
        $this->addColumnIfMissing('played_cards', 'card_suit', fn (Blueprint $t) => $t->string('card_suit', 10)->nullable());
        $this->addColumnIfMissing('played_cards', 'played_at', fn (Blueprint $t) => $t->timestamp('played_at')->nullable());

        // --- scores ---
        $this->addColumnIfMissing('scores', 'game_id', fn (Blueprint $t) => $t->unsignedBigInteger('game_id')->nullable());
        $this->addColumnIfMissing('scores', 'round_id', fn (Blueprint $t) => $t->unsignedBigInteger('round_id')->nullable());
        $this->addColumnIfMissing('scores', 'player_id', fn (Blueprint $t) => $t->unsignedBigInteger('player_id')->nullable());
        $this->addColumnIfMissing('scores', 'user_id', fn (Blueprint $t) => $t->unsignedBigInteger('user_id')->nullable());
        $this->addColumnIfMissing('scores', 'announcement', fn (Blueprint $t) => $t->integer('announcement')->default(0));
        $this->addColumnIfMissing('scores', 'tricks_won', fn (Blueprint $t) => $t->integer('tricks_won')->default(0));
        $this->addColumnIfMissing('scores', 'round_score', fn (Blueprint $t) => $t->integer('round_score')->default(0));
        $this->addColumnIfMissing('scores', 'cumulative_score', fn (Blueprint $t) => $t->integer('cumulative_score')->default(0));

        // --- room_invitations ---
        $this->addColumnIfMissing('room_invitations', 'room_id', fn (Blueprint $t) => $t->unsignedBigInteger('room_id')->nullable());
        $this->addColumnIfMissing('room_invitations', 'sender_id', fn (Blueprint $t) => $t->unsignedBigInteger('sender_id')->nullable());
        $this->addColumnIfMissing('room_invitations', 'receiver_id', fn (Blueprint $t) => $t->unsignedBigInteger('receiver_id')->nullable());
        $this->addColumnIfMissing('room_invitations', 'status', fn (Blueprint $t) => $t->string('status', 20)->default('pending'));
        $this->addColumnIfMissing('room_invitations', 'message', fn (Blueprint $t) => $t->string('message', 255)->nullable());
        $this->addColumnIfMissing('room_invitations', 'room_name', fn (Blueprint $t) => $t->string('room_name', 100)->nullable());
        $this->addColumnIfMissing('room_invitations', 'room_code', fn (Blueprint $t) => $t->string('room_code', 10)->nullable());
        $this->addColumnIfMissing('room_invitations', 'host_id', fn (Blueprint $t) => $t->unsignedBigInteger('host_id')->nullable());
        $this->addColumnIfMissing('room_invitations', 'host_pseudo', fn (Blueprint $t) => $t->string('host_pseudo', 50)->nullable());
        $this->addColumnIfMissing('room_invitations', 'host_avatar', fn (Blueprint $t) => $t->string('host_avatar', 255)->nullable());
        $this->addColumnIfMissing('room_invitations', 'invited_user_id', fn (Blueprint $t) => $t->unsignedBigInteger('invited_user_id')->nullable());
        $this->addColumnIfMissing('room_invitations', 'minimum_bet', fn (Blueprint $t) => $t->integer('minimum_bet')->default(0));

        // --- admin_logs ---
        $this->addColumnIfMissing('admin_logs', 'admin_user_id', fn (Blueprint $t) => $t->unsignedBigInteger('admin_user_id')->nullable());
        $this->addColumnIfMissing('admin_logs', 'action', fn (Blueprint $t) => $t->string('action', 100)->nullable());
        $this->addColumnIfMissing('admin_logs', 'target_type', fn (Blueprint $t) => $t->string('target_type', 50)->nullable());
        $this->addColumnIfMissing('admin_logs', 'target_id', fn (Blueprint $t) => $t->unsignedBigInteger('target_id')->nullable());
        $this->addColumnIfMissing('admin_logs', 'details', fn (Blueprint $t) => $t->json('details')->nullable());
        $this->addColumnIfMissing('admin_logs', 'ip_address', fn (Blueprint $t) => $t->string('ip_address', 45)->nullable());

        // --- Clés étrangères principales ---
        $this->addForeignIfMissing('rooms', 'creator_id', 'users', 'user_id');
        $this->addForeignIfMissing('room_players', 'room_id', 'rooms', 'room_id');
        $this->addForeignIfMissing('room_players', 'user_id', 'users', 'user_id');
        $this->addForeignIfMissing('friendships', 'user_id', 'users', 'user_id');
        $this->addForeignIfMissing('friendships', 'friend_id', 'users', 'user_id');
        $this->addForeignIfMissing('friend_requests', 'sender_id', 'users', 'user_id');
        $this->addForeignIfMissing('friend_requests', 'receiver_id', 'users', 'user_id');
        $this->addForeignIfMissing('games', 'room_id', 'rooms', 'room_id');
        $this->addForeignIfMissing('games', 'winner_id', 'users', 'user_id', 'set null');
        $this->addForeignIfMissing('announcements', 'game_id', 'games', 'game_id');
        $this->addForeignIfMissing('announcements', 'player_id', 'room_players', 'player_id');
        $this->addForeignIfMissing('announcements', 'user_id', 'users', 'user_id');
        $this->addForeignIfMissing('rounds', 'game_id', 'games', 'game_id');
        $this->addForeignIfMissing('tricks', 'round_id', 'rounds', 'round_id');
        $this->addForeignIfMissing('tricks', 'lead_player_id', 'room_players', 'player_id');
        $this->addForeignIfMissing('tricks', 'winner_player_id', 'room_players', 'player_id', 'set null');
        $this->addForeignIfMissing('played_cards', 'trick_id', 'tricks', 'trick_id');
        $this->addForeignIfMissing('played_cards', 'player_id', 'room_players', 'player_id');
        $this->addForeignIfMissing('scores', 'game_id', 'games', 'game_id');
        $this->addForeignIfMissing('scores', 'round_id', 'rounds', 'round_id', 'set null');
        $this->addForeignIfMissing('scores', 'player_id', 'room_players', 'player_id');
        $this->addForeignIfMissing('scores', 'user_id', 'users', 'user_id');
        $this->addForeignIfMissing('room_invitations', 'room_id', 'rooms', 'room_id');
        $this->addForeignIfMissing('room_invitations', 'sender_id', 'users', 'user_id');
        $this->addForeignIfMissing('room_invitations', 'receiver_id', 'users', 'user_id');
        $this->addForeignIfMissing('admin_logs', 'admin_user_id', 'users', 'user_id');
    }

    public function down(): void
    {
        // Pas de rollback destructif sur schéma de production
    }
};
