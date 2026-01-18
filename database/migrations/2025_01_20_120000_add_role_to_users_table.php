<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

/**
 * Ajouter le champ role à la table users
 * 
 * Rôles possibles :
 * - superadmin : Super administrateur (accès complet)
 * - admin : Administrateur (gestion normale)
 * - manager : Manager/Service Client (gestion des messages)
 * - user : Utilisateur simple (par défaut)
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Vérifier si la colonne role existe déjà
        if (!Schema::hasColumn('users', 'role')) {
            Schema::table('users', function (Blueprint $table) {
                $table->enum('role', ['superadmin', 'admin', 'manager', 'user'])->default('user')->after('is_admin');
            });
        }

        // Mettre à jour les rôles existants basés sur is_admin et pseudo
        // (uniquement si la colonne existe maintenant ou si elle existait déjà)
        if (Schema::hasColumn('users', 'role')) {
            $hasPseudo = Schema::hasColumn('users', 'pseudo');
            $hasIsAdmin = Schema::hasColumn('users', 'is_admin');
            
            $casePseudoSuper = $hasPseudo ? "pseudo = 'superAdmin' OR " : "";
            $casePseudoManager = $hasPseudo ? "pseudo = 'manageradmin' OR " : "";
            $caseIsAdmin = $hasIsAdmin ? "WHEN is_admin = true OR is_admin::text = '1' THEN 'admin'" : "";

            try {
                DB::statement("
                    UPDATE users 
                    SET role = CASE 
                        WHEN {$casePseudoSuper}email = 'superadmin@cauris.com' THEN 'superadmin'
                        WHEN {$casePseudoManager}email = 'manageradmin@cauris.com' THEN 'manager'
                        {$caseIsAdmin}
                        ELSE 'user'
                    END
                    WHERE role IS NULL OR role = ''
                ");
            } catch (\Exception $e) {
                // Silently skip if data-only update fails
                \Log::warning("Migration 2025_01_20_120000: Data update skipped: " . $e->getMessage());
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Vérifier si la colonne existe avant de la supprimer
        if (Schema::hasColumn('users', 'role')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('role');
            });
        }
    }
};

