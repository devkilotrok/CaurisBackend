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
        Schema::table('rounds', function (Blueprint $table) {
            // ✅ Ajouter le champ pour stocker les cartes distribuées par joueur
            // Format JSON: {"Alpha": ["AS", "KS", ...], "Bot1": ["AD", "KD", ...], ...}
            $table->json('distributed_cards')->nullable()->after('obtained_tricks');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('rounds', function (Blueprint $table) {
            $table->dropColumn('distributed_cards');
        });
    }
};
