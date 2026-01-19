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
        // 1. Sync room_invitations table
        if (Schema::hasTable('room_invitations')) {
            Schema::table('room_invitations', function (Blueprint $table) {
                if (!Schema::hasColumn('room_invitations', 'room_id')) {
                    $table->unsignedBigInteger('room_id')->nullable();
                }
                if (!Schema::hasColumn('room_invitations', 'room_name')) {
                    $table->string('room_name')->nullable();
                }
                if (!Schema::hasColumn('room_invitations', 'room_code')) {
                    $table->string('room_code', 10)->nullable();
                }
                if (!Schema::hasColumn('room_invitations', 'host_id')) {
                    $table->unsignedBigInteger('host_id')->nullable();
                }
                if (!Schema::hasColumn('room_invitations', 'host_pseudo')) {
                    $table->string('host_pseudo')->nullable();
                }
                if (!Schema::hasColumn('room_invitations', 'host_avatar')) {
                    $table->string('host_avatar')->nullable();
                }
                if (!Schema::hasColumn('room_invitations', 'invited_user_id')) {
                    $table->unsignedBigInteger('invited_user_id')->nullable();
                }
                if (!Schema::hasColumn('room_invitations', 'minimum_bet')) {
                    $table->integer('minimum_bet')->default(0);
                }
                if (!Schema::hasColumn('room_invitations', 'message')) {
                    $table->string('message')->nullable();
                }
                if (!Schema::hasColumn('room_invitations', 'status')) {
                    $table->string('status')->default('pending'); // pending, accepted, rejected
                }
            });
        }

        // 2. Ensure transactions table has correct defaults and primary key if needed
        if (Schema::hasTable('transactions')) {
            Schema::table('transactions', function (Blueprint $table) {
                // Ensure cauris_balance is synced in users if not already done by previous fixes
                // (Already done in sync_users_table_schema)
            });
        }
        
        // 3. Sync player_replacements if needed (checked previously, seems ok but good to be sure)
        if (Schema::hasTable('player_replacements')) {
            Schema::table('player_replacements', function (Blueprint $table) {
                if (!Schema::hasColumn('player_replacements', 'replacement_id')) {
                    // Check if it has 'id' and rename to replacement_id for consistency
                    if (Schema::hasColumn('player_replacements', 'id')) {
                         $table->renameColumn('id', 'replacement_id');
                    }
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('room_invitations')) {
            Schema::table('room_invitations', function (Blueprint $table) {
                $table->dropColumn([
                    'room_id', 'room_name', 'room_code', 'host_id', 
                    'host_pseudo', 'host_avatar', 'invited_user_id', 
                    'minimum_bet', 'message', 'status'
                ]);
            });
        }
    }
};
