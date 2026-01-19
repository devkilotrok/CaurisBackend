<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Table pour stocker les codes de vérification d'email
 * 
 * Utilisé pour :
 * - Codes de vérification d'inscription
 * - Codes de réinitialisation de mot de passe
 * 
 * Les codes expirent automatiquement après la durée spécifiée
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('email_verification_codes', function (Blueprint $table) {
            $table->id('code_id');
            $table->string('email')->index();
            $table->string('code', 6); // Code à 6 chiffres
            $table->enum('type', ['verification', 'reset']); // Type de code
            $table->timestamp('expires_at');
            $table->boolean('used')->default(false);
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            
            $table->index(['email', 'type']);
            $table->index('expires_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('email_verification_codes');
    }
};
