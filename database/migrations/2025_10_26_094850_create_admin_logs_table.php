<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('admin_logs')) {
            Schema::create('admin_logs', function (Blueprint $table) {
                $table->bigIncrements('log_id');
                $table->unsignedBigInteger('admin_user_id');
                $table->string('action', 100);
                $table->string('target_type', 50)->nullable();
                $table->unsignedBigInteger('target_id')->nullable();
                $table->json('details')->nullable();
                $table->string('ip_address', 45)->nullable();
                $table->timestamp('created_at')->nullable();

                $table->foreign('admin_user_id')->references('user_id')->on('users')->onDelete('cascade');
                $table->index('admin_user_id');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_logs');
    }
};
