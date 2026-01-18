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
            // Ajouter la colonne status pour gérer l'état du round
            if (!Schema::hasColumn('rounds', 'status')) {
                $table->string('status', 50)->nullable()->after('round_number')
                    ->comment('Statut du round: ANNOUNCEMENT_PHASE, PLAYING, FINISHED');
            }
            
            // Ajouter la colonne announcement_end_at pour gérer le timeout
            if (!Schema::hasColumn('rounds', 'announcement_end_at')) {
                $table->timestamp('announcement_end_at')->nullable()->after('status')
                    ->comment('Date/heure de fin de la phase d\'annonces (timeout)');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('rounds', function (Blueprint $table) {
            if (Schema::hasColumn('rounds', 'announcement_end_at')) {
                $table->dropColumn('announcement_end_at');
            }
            if (Schema::hasColumn('rounds', 'status')) {
                $table->dropColumn('status');
            }
        });
    }
};
