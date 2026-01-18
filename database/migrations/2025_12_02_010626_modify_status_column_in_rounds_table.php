<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('rounds', function (Blueprint $table) {
            // ✅ Modifier la colonne status existante pour accepter des valeurs plus longues
            if (Schema::hasColumn('rounds', 'status')) {
                // Utiliser DB::statement pour modifier directement le type de colonne
                DB::statement("ALTER TABLE `rounds` MODIFY COLUMN `status` VARCHAR(50) NULL COMMENT 'Statut du round: ANNOUNCEMENT_PHASE, PLAYING, FINISHED'");
            } else {
                // Si la colonne n'existe pas, la créer
                $table->string('status', 50)->nullable()->after('round_number')
                    ->comment('Statut du round: ANNOUNCEMENT_PHASE, PLAYING, FINISHED');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('rounds', function (Blueprint $table) {
            // Revenir à un enum plus restrictif si nécessaire
            // Pour l'instant, on garde le string pour la flexibilité
            if (Schema::hasColumn('rounds', 'status')) {
                DB::statement("ALTER TABLE `rounds` MODIFY COLUMN `status` VARCHAR(20) NULL");
            }
        });
    }
};
