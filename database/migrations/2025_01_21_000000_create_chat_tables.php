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
        // Table des conversations de chat
        Schema::create('chat_conversations', function (Blueprint $table) {
            $table->id();
            $table->integer('user_id');
            $table->enum('status', ['active', 'waiting_manager', 'with_manager', 'closed'])->default('active');
            $table->integer('assigned_manager_id')->nullable();
            $table->enum('assistant_type', ['ai', 'manager'])->default('ai');
            $table->timestamp('last_message_at')->nullable();
            $table->timestamps();
            
            $table->foreign('user_id')->references('user_id')->on('users')->onDelete('cascade');
            $table->foreign('assigned_manager_id')->references('user_id')->on('users')->onDelete('set null');
            $table->index('user_id');
            $table->index('status');
            $table->index('assigned_manager_id');
        });

        // Table des messages de chat
        Schema::create('chat_messages', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('conversation_id');
            $table->enum('sender_type', ['user', 'ai', 'manager']);
            $table->unsignedBigInteger('sender_id')->nullable(); // user_id ou manager_id
            $table->text('message');
            $table->boolean('is_read')->default(false);
            $table->timestamps();
            
            $table->foreign('conversation_id')->references('id')->on('chat_conversations')->onDelete('cascade');
            $table->index('conversation_id');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('chat_messages');
        Schema::dropIfExists('chat_conversations');
    }
};

