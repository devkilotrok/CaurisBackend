<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Table pour stocker les messages de contact
 * 
 * Utilisé pour :
 * - Sauvegarder tous les messages du formulaire de contact
 * - Permettre au service client de gérer les messages
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Vérifier si la table existe déjà
        if (!Schema::hasTable('contact_messages')) {
            Schema::create('contact_messages', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('email');
                $table->text('message');
                $table->string('ip_address', 45)->nullable();
                $table->enum('status', ['unread', 'read', 'processed'])->default('unread');
                $table->unsignedBigInteger('read_by')->nullable();
                $table->timestamp('read_at')->nullable();
                $table->unsignedBigInteger('processed_by')->nullable();
                $table->timestamp('processed_at')->nullable();
                $table->timestamps();
                
                $table->index('status');
                $table->index('email');
                $table->index('created_at');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('contact_messages');
    }
};

