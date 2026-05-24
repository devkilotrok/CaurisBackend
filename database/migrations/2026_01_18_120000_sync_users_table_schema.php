<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // 1. Rename 'id' to 'user_id' if 'id' exists and 'user_id' doesn't
            if (Schema::hasColumn('users', 'id') && !Schema::hasColumn('users', 'user_id')) {
                $table->renameColumn('id', 'user_id');
            }

            // 2. Rename 'password' to 'password_hash' if it exists
            if (Schema::hasColumn('users', 'password') && !Schema::hasColumn('users', 'password_hash')) {
                $table->renameColumn('password', 'password_hash');
            }

            if (!Schema::hasColumn('users', 'name')) {
                $table->string('name')->nullable();
            }

            // 3. Add 'pseudo' if mission
            if (!Schema::hasColumn('users', 'pseudo')) {
                $table->string('pseudo', 50)->unique()->nullable(); // Temporarily nullable to avoid issues with existing data
            }

            // 4. Add other missing columns
            if (!Schema::hasColumn('users', 'first_name')) {
                $table->string('first_name', 50)->nullable();
            }
            if (!Schema::hasColumn('users', 'last_name')) {
                $table->string('last_name', 50)->nullable();
            }
            if (!Schema::hasColumn('users', 'phone')) {
                $table->string('phone', 20)->nullable();
            }
            if (!Schema::hasColumn('users', 'address')) {
                $table->text('address')->nullable();
            }
            if (!Schema::hasColumn('users', 'avatar')) {
                $table->string('avatar', 255)->default('👤');
            }
            if (!Schema::hasColumn('users', 'theme_preference')) {
                $table->string('theme_preference', 20)->default('light');
            }
            if (!Schema::hasColumn('users', 'is_active')) {
                $table->boolean('is_active')->default(true);
            }
            if (!Schema::hasColumn('users', 'is_bot')) {
                $table->boolean('is_bot')->default(false);
            }
            if (!Schema::hasColumn('users', 'last_login')) {
                $table->timestamp('last_login')->nullable();
            }
            if (!Schema::hasColumn('users', 'cauris_balance')) {
                $table->integer('cauris_balance')->default(0);
            }
            if (!Schema::hasColumn('users', 'role')) {
                $table->enum('role', ['superadmin', 'admin', 'manager', 'user'])->default('user');
            }
        });

        // 5. Cleanup pseudo: make it NOT NULL after potentially seeding or if empty
        // In PostgreSQL, we can use raw SQL to update existing rows if needed.
        // For now, let's just make it NOT NULL if it was added.
        // We'll skip this to be safe, as 'nullable' is safer for an emergency repair.
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // For a repair migration, 'down' is risky, so we'll just skip it or be minimal
        Schema::table('users', function (Blueprint $table) {
            // We won't drop columns to avoid data loss, but we could rename back if needed
        });
    }
};
