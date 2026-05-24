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
        if (!Schema::hasTable('rounds')) {
            return;
        }

        if (Schema::hasColumn('rounds', 'status')) {
            if (DB::getDriverName() === 'pgsql') {
                DB::statement('ALTER TABLE rounds ALTER COLUMN status TYPE VARCHAR(50)');
                DB::statement('ALTER TABLE rounds ALTER COLUMN status DROP NOT NULL');
            } elseif (DB::getDriverName() === 'mysql') {
                DB::statement('ALTER TABLE rounds MODIFY COLUMN status VARCHAR(50) NULL');
            }
        } else {
            Schema::table('rounds', function (Blueprint $table) {
                $table->string('status', 50)->nullable();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (!Schema::hasTable('rounds') || !Schema::hasColumn('rounds', 'status')) {
            return;
        }

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE rounds ALTER COLUMN status TYPE VARCHAR(20)');
            DB::statement('ALTER TABLE rounds ALTER COLUMN status DROP NOT NULL');
        } elseif (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE rounds MODIFY COLUMN status VARCHAR(20) NULL');
        }
    }
};
