<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('rounds')) {
            Schema::table('rounds', function (Blueprint $table) {
                if (!Schema::hasColumn('rounds', 'deck_hash')) {
                    $table->string('deck_hash', 128)->nullable()->after('round_number');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('rounds')) {
            Schema::table('rounds', function (Blueprint $table) {
                if (Schema::hasColumn('rounds', 'deck_hash')) {
                    $table->dropColumn('deck_hash');
                }
            });
        }
    }
};
