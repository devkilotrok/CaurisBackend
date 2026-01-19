<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * This migration fixes a critical mismatch between INT and BIGINT for the user_id column.
     * This is necessary for MySQL foreign key compatibility when admin_messages or other
     * tables try to link to users.user_id.
     */
    public function up(): void
    {
        if (Schema::hasTable('users') && Schema::hasColumn('users', 'user_id')) {
            // Check current driver
            if (DB::getDriverName() === 'mysql') {
                // Use raw SQL to be 100% sure about the change without needing doctrine/dbal if not present
                // We also preserve AUTO_INCREMENT
                DB::statement('ALTER TABLE users MODIFY user_id BIGINT UNSIGNED AUTO_INCREMENT');
            } else if (DB::getDriverName() === 'pgsql') {
                DB::statement('ALTER TABLE users ALTER COLUMN user_id TYPE BIGINT');
            } else {
                Schema::table('users', function (Blueprint $table) {
                    $table->unsignedBigInteger('user_id', true)->change();
                });
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // No down needed as BIGINT is generally safer
    }
};
