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
                // Type de média (text, audio, image)
                if (!Schema::hasColumn('admin_messages', 'media_type')) {
                    $table->enum('media_type', ['text', 'audio', 'image'])->default('text')->after('message');
                }
                
                // Nom du fichier média (stocké localement côté client, pas sur le serveur)
                if (!Schema::hasColumn('admin_messages', 'media_url')) {
                    $table->string('media_url')->nullable()->after('media_type')->comment('Nom/ID du fichier stocké localement');
                }
                
                // Référence au message auquel on répond (pour les réponses)
                if (!Schema::hasColumn('admin_messages', 'reply_to_message_id')) {
                    $table->unsignedBigInteger('reply_to_message_id')->nullable()->after('parent_id');
                    $table->foreign('reply_to_message_id')->references('id')->on('admin_messages')->onDelete('set null');
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
                if (Schema::hasColumn('admin_messages', 'reply_to_message_id')) {
                    $table->dropForeign(['reply_to_message_id']);
                    $table->dropColumn('reply_to_message_id');
                }
                if (Schema::hasColumn('admin_messages', 'media_url')) {
                    $table->dropColumn('media_url');
                }
                if (Schema::hasColumn('admin_messages', 'media_type')) {
                    $table->dropColumn('media_type');
                }
            });
        }
    }
};
