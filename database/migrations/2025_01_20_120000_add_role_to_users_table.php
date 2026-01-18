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
            DB::statement("
                UPDATE users 
                SET role = CASE 
                    WHEN pseudo = 'superAdmin' OR email = 'superadmin@cauris.com' THEN 'superadmin'
                    WHEN pseudo = 'manageradmin' OR email = 'manageradmin@cauris.com' THEN 'manager'
                    WHEN is_admin = 1 THEN 'admin'
                    ELSE 'user'
                END
                WHERE role IS NULL OR role = ''
            ");
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

