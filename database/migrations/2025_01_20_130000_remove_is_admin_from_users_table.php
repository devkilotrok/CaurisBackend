<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

/**
 * Supprimer la colonne is_admin de la table users
 * 
 * Cette migration supprime is_admin car nous utilisons maintenant uniquement
 * la colonne role pour gérer les permissions.
 * 
 * Note: Cette migration doit être exécutée APRÈS que tous les rôles
 * aient été correctement définis dans la colonne role.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Vérifier si la colonne existe avant de la supprimer
        if (Schema::hasColumn('users', 'is_admin')) {
            // Supprimer l'index idx_is_admin s'il existe (via SQL direct)
            try {
                DB::statement('ALTER TABLE users DROP INDEX idx_is_admin');
            } catch (\Exception $e) {
                // L'index n'existe peut-être pas, continuer
            }
            
            // Supprimer la colonne is_admin
            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('is_admin');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Recréer la colonne is_admin si nécessaire
        if (!Schema::hasColumn('users', 'is_admin')) {
            Schema::table('users', function (Blueprint $table) {
                $table->boolean('is_admin')->default(false)->after('role');
                // Recréer l'index
                $table->index('is_admin', 'idx_is_admin');
            });
            
            // Mettre à jour is_admin basé sur role
            DB::statement("
                UPDATE users 
                SET is_admin = CASE 
                    WHEN role IN ('superadmin', 'admin', 'manager') THEN 1
                    ELSE 0
                END
            ");
        }
    }
};

