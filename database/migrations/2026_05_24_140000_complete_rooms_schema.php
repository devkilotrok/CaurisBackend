<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('rooms')) {
            Schema::table('rooms', function (Blueprint $table) {
                if (!Schema::hasColumn('rooms', 'room_name')) {
                    $table->string('room_name', 100)->nullable();
                }
                if (!Schema::hasColumn('rooms', 'room_code')) {
                    $table->string('room_code', 6)->nullable()->unique();
                }
                if (!Schema::hasColumn('rooms', 'creator_id')) {
                    $table->unsignedBigInteger('creator_id')->nullable();
                }
                if (!Schema::hasColumn('rooms', 'minimum_bet')) {
                    $table->integer('minimum_bet')->default(50);
                }
                if (!Schema::hasColumn('rooms', 'status')) {
                    $table->string('status', 20)->default('waiting');
                }
                if (!Schema::hasColumn('rooms', 'max_players')) {
                    $table->integer('max_players')->default(4);
                }
                if (!Schema::hasColumn('rooms', 'started_at')) {
                    $table->timestamp('started_at')->nullable();
                }
                if (!Schema::hasColumn('rooms', 'finished_at')) {
                    $table->timestamp('finished_at')->nullable();
                }
            });
        }

        if (Schema::hasTable('room_players')) {
            Schema::table('room_players', function (Blueprint $table) {
                if (!Schema::hasColumn('room_players', 'room_id')) {
                    $table->unsignedBigInteger('room_id')->nullable();
                }
                if (!Schema::hasColumn('room_players', 'user_id')) {
                    $table->unsignedBigInteger('user_id')->nullable();
                }
                if (!Schema::hasColumn('room_players', 'position')) {
                    $table->integer('position')->nullable();
                }
                if (!Schema::hasColumn('room_players', 'is_creator')) {
                    $table->boolean('is_creator')->default(false);
                }
                if (!Schema::hasColumn('room_players', 'status')) {
                    $table->string('status', 20)->default('waiting');
                }
                if (!Schema::hasColumn('room_players', 'joined_at')) {
                    $table->timestamp('joined_at')->nullable();
                }
            });
        }
    }

    public function down(): void
    {
        // Pas de rollback destructif
    }
};
