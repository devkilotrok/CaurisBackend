<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // rooms: add updated_at if missing
        if (Schema::hasTable('rooms')) {
            Schema::table('rooms', function (Blueprint $table) {
                if (!Schema::hasColumn('rooms', 'updated_at')) {
                    $table->timestamp('updated_at')->nullable();
                }
            });
        }

        // games: ensure timestamps
        if (Schema::hasTable('games')) {
            Schema::table('games', function (Blueprint $table) {
                if (!Schema::hasColumn('games', 'created_at')) {
                    $table->timestamp('created_at')->nullable();
                }
                if (!Schema::hasColumn('games', 'updated_at')) {
                    $table->timestamp('updated_at')->nullable();
                }
            });
        }

        // rounds: add timestamps and optional game_id with FK
        if (Schema::hasTable('rounds')) {
            Schema::table('rounds', function (Blueprint $table) {
                if (!Schema::hasColumn('rounds', 'created_at')) {
                    $table->timestamp('created_at')->nullable();
                }
                if (!Schema::hasColumn('rounds', 'updated_at')) {
                    $table->timestamp('updated_at')->nullable();
                }
                if (!Schema::hasColumn('rounds', 'game_id')) {
                    $afterColumn = Schema::hasColumn('rounds', 'round_id') ? 'round_id' : (Schema::hasColumn('rounds', 'id') ? 'id' : null);
                    if ($afterColumn) {
                        $table->unsignedBigInteger('game_id')->nullable()->after($afterColumn);
                    } else {
                        $table->unsignedBigInteger('game_id')->nullable();
                    }
                    try {
                        $table->foreign('game_id')->references('game_id')->on('games')->onDelete('cascade');
                    } catch (\Throwable $e) {
                        // Ignore FK if structure incompatible
                    }
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('rooms')) {
            Schema::table('rooms', function (Blueprint $table) {
                if (Schema::hasColumn('rooms', 'updated_at')) {
                    $table->dropColumn('updated_at');
                }
            });
        }
        if (Schema::hasTable('games')) {
            Schema::table('games', function (Blueprint $table) {
                if (Schema::hasColumn('games', 'created_at')) {
                    $table->dropColumn('created_at');
                }
                if (Schema::hasColumn('games', 'updated_at')) {
                    $table->dropColumn('updated_at');
                }
            });
        }
        if (Schema::hasTable('rounds')) {
            Schema::table('rounds', function (Blueprint $table) {
                if (Schema::hasColumn('rounds', 'updated_at')) {
                    $table->dropColumn('updated_at');
                }
                if (Schema::hasColumn('rounds', 'created_at')) {
                    $table->dropColumn('created_at');
                }
                if (Schema::hasColumn('rounds', 'game_id')) {
                    try { $table->dropForeign(['game_id']); } catch (\Throwable $e) {}
                    $table->dropColumn('game_id');
                }
            });
        }
    }
};
