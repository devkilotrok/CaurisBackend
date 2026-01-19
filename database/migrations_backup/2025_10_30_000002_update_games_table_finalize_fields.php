<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('games')) {
            Schema::table('games', function (Blueprint $table) {
                if (!Schema::hasColumn('games', 'final_scores')) {
                    $table->json('final_scores')->nullable();
                }
                if (!Schema::hasColumn('games', 'finished_at')) {
                    $table->timestamp('finished_at')->nullable();
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('games')) {
            Schema::table('games', function (Blueprint $table) {
                if (Schema::hasColumn('games', 'finished_at')) {
                    $table->dropColumn('finished_at');
                }
                if (Schema::hasColumn('games', 'final_scores')) {
                    $table->dropColumn('final_scores');
                }
            });
        }
    }
};
