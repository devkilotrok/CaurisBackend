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
        if (Schema::hasTable('admin_messages')) {
            Schema::table('admin_messages', function (Blueprint $table) {
                // Ajouter colonne pour le statut détaillé (sent, delivered, read)
                if (!Schema::hasColumn('admin_messages', 'message_status')) {
                    $table->enum('message_status', ['sending', 'sent', 'delivered', 'read', 'failed'])->default('sent')->after('status');
                }
                
                // Ajouter colonne pour savoir si le message a été modifié
                if (!Schema::hasColumn('admin_messages', 'is_edited')) {
                    $table->boolean('is_edited')->default(false)->after('message_status');
                }
                
                // Ajouter colonne pour savoir si le message a été supprimé
                if (!Schema::hasColumn('admin_messages', 'is_deleted')) {
                    $table->boolean('is_deleted')->default(false)->after('is_edited');
                }
                
                // Ajouter colonne pour la date de modification
                if (!Schema::hasColumn('admin_messages', 'edited_at')) {
                    $table->timestamp('edited_at')->nullable()->after('is_deleted');
                }
                
                // Ajouter colonne pour la date de livraison
                if (!Schema::hasColumn('admin_messages', 'delivered_at')) {
                    $table->timestamp('delivered_at')->nullable()->after('edited_at');
                }
                
                // Ajouter colonne pour l'erreur d'envoi
                if (!Schema::hasColumn('admin_messages', 'error_message')) {
                    $table->text('error_message')->nullable()->after('delivered_at');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('admin_messages')) {
            Schema::table('admin_messages', function (Blueprint $table) {
                if (Schema::hasColumn('admin_messages', 'error_message')) {
                    $table->dropColumn('error_message');
                }
                if (Schema::hasColumn('admin_messages', 'delivered_at')) {
                    $table->dropColumn('delivered_at');
                }
                if (Schema::hasColumn('admin_messages', 'edited_at')) {
                    $table->dropColumn('edited_at');
                }
                if (Schema::hasColumn('admin_messages', 'is_deleted')) {
                    $table->dropColumn('is_deleted');
                }
                if (Schema::hasColumn('admin_messages', 'is_edited')) {
                    $table->dropColumn('is_edited');
                }
                if (Schema::hasColumn('admin_messages', 'message_status')) {
                    $table->dropColumn('message_status');
                }
            });
        }
    }
};

