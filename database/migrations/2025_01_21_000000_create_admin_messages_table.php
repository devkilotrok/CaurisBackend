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
        if (!Schema::hasTable('admin_messages')) {
            Schema::create('admin_messages', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('sender_id')->comment('ID de l\'admin qui envoie');
                $table->unsignedBigInteger('recipient_id')->nullable()->comment('ID du super admin (peut être null pour messages généraux)');
                $table->string('subject')->comment('Sujet du message');
                $table->text('message')->comment('Contenu du message');
                $table->enum('status', ['unread', 'read', 'replied'])->default('unread');
                $table->unsignedBigInteger('parent_id')->nullable()->comment('ID du message parent (pour les réponses)');
                $table->timestamp('read_at')->nullable();
                $table->timestamps();
                
                $table->index('sender_id');
                $table->index('recipient_id');
                $table->index('status');
                $table->index('parent_id');
                $table->index('created_at');
            });
            
            // Ajouter les clés étrangères après la création de la table
            Schema::table('admin_messages', function (Blueprint $table) {
                $table->foreign('sender_id')->references('user_id')->on('users')->onDelete('cascade');
                $table->foreign('recipient_id')->references('user_id')->on('users')->onDelete('cascade');
                $table->foreign('parent_id')->references('id')->on('admin_messages')->onDelete('cascade');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('admin_messages')) {
            Schema::dropIfExists('admin_messages');
        }
    }
};

