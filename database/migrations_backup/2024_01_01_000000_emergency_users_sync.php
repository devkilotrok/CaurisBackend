<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * This migration is dated 2024 to run BEFORE the 2025 legacy migrations
     * that are currently failing due to missing columns.
     */
    public function up(): void
    {
        if (Schema::hasTable('users')) {
            Schema::table('users', function (Blueprint $table) {
                // Pre-add pseudo if missing (Satisfies roles migration)
                if (!Schema::hasColumn('users', 'pseudo')) {
                    $table->string('pseudo', 50)->nullable();
                }

                // Pre-add is_admin if missing (Satisfies roles and remove_is_admin migrations)
                if (!Schema::hasColumn('users', 'is_admin')) {
                    $table->boolean('is_admin')->default(false);
                }

                // Pre-rename for consistency
                if (Schema::hasColumn('users', 'id') && !Schema::hasColumn('users', 'user_id')) {
                    $table->renameColumn('id', 'user_id');
                }
                
                if (Schema::hasColumn('users', 'password') && !Schema::hasColumn('users', 'password_hash')) {
                    $table->renameColumn('password', 'password_hash');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Emergency rescue migrations should not revert columns to avoid breaking state
    }
};
