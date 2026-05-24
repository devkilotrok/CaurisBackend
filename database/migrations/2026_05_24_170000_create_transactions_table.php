<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public $withinTransaction = false;

    public function up(): void
    {
        if (Schema::hasTable('transactions')) {
            return;
        }

        Schema::create('transactions', function (Blueprint $table) {
            $table->id('transaction_id');
            $table->unsignedBigInteger('user_id');
            $table->string('type', 20); // depot | retrait
            $table->integer('cauris_amount');
            $table->integer('fcfa_amount');
            $table->string('beneficiaire_name')->nullable();
            $table->string('phone_number', 20)->nullable();
            $table->string('image_path', 500)->nullable();
            $table->string('status', 20)->default('en_attente'); // en_attente | valide | rejete
            $table->timestamp('validated_at')->nullable();
            $table->unsignedBigInteger('validated_by')->nullable();
            $table->text('notes')->nullable();
            $table->string('fedapay_transaction_id')->nullable();
            $table->string('fedapay_status', 50)->nullable();
            $table->string('payment_method', 50)->default('manual');
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
            $table->index('status');
        });

        if (Schema::hasTable('users')) {
            try {
                Schema::table('transactions', function (Blueprint $table) {
                    $table->foreign('user_id')
                        ->references('user_id')
                        ->on('users')
                        ->onDelete('cascade');
                });
            } catch (\Throwable $e) {
                // FK optionnelle si types incompatibles
            }

            try {
                Schema::table('transactions', function (Blueprint $table) {
                    $table->foreign('validated_by')
                        ->references('user_id')
                        ->on('users')
                        ->onDelete('set null');
                });
            } catch (\Throwable $e) {
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};
