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
                // Utiliser une syntaxe compatible (Doctrine DBAL est souvent nécessaire pour change() mais ici on utilise raw)
                if (DB::getDriverName() === 'pgsql') {
                    DB::statement("ALTER TABLE rounds ALTER COLUMN status TYPE VARCHAR(50)");
                } else {
                    DB::statement("ALTER TABLE rounds MODIFY COLUMN status VARCHAR(50) NULL");
                }
            } else {
                // Si la colonne n'existe pas, la créer
                $table->string('status', 50)->nullable()->after('round_number');
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
                if (DB::getDriverName() === 'pgsql') {
                    DB::statement("ALTER TABLE rounds ALTER COLUMN status TYPE VARCHAR(20)");
                } else {
                    DB::statement("ALTER TABLE rounds MODIFY COLUMN status VARCHAR(20) NULL");
                }
            }
        });
    }
};
