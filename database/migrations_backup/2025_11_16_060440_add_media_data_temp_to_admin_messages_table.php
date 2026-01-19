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
        if (Schema::hasTable('admin_messages')) {
            Schema::table('admin_messages', function (Blueprint $table) {
                // Données média temporaires (base64) - supprimées après récupération
                if (!Schema::hasColumn('admin_messages', 'media_data_temp')) {
                    $table->longText('media_data_temp')->nullable()->after('media_url')->comment('Données base64 temporaires, supprimées après récupération par le destinataire');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('admin_messages')) {
            Schema::table('admin_messages', function (Blueprint $table) {
                if (Schema::hasColumn('admin_messages', 'media_data_temp')) {
                    $table->dropColumn('media_data_temp');
                }
            });
        }
    }
};
