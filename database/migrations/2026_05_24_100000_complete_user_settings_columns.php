<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('user_settings')) {
            return;
        }

        Schema::table('user_settings', function (Blueprint $table) {
            if (!Schema::hasColumn('user_settings', 'user_id')) {
                $table->unsignedBigInteger('user_id')->unique()->nullable();
            }
            if (!Schema::hasColumn('user_settings', 'language')) {
                $table->string('language', 10)->default('fr');
            }
            if (!Schema::hasColumn('user_settings', 'notifications_enabled')) {
                $table->boolean('notifications_enabled')->default(true);
            }
            if (!Schema::hasColumn('user_settings', 'sound_enabled')) {
                $table->boolean('sound_enabled')->default(true);
            }
            if (!Schema::hasColumn('user_settings', 'vibration_enabled')) {
                $table->boolean('vibration_enabled')->default(true);
            }
            if (!Schema::hasColumn('user_settings', 'theme_mode')) {
                $table->string('theme_mode', 20)->default('light');
            }
        });
    }

    public function down(): void
    {
        // Pas de rollback destructif
    }
};
