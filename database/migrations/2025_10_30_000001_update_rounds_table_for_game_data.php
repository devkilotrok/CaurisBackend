<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('rounds')) {
            Schema::create('rounds', function (Blueprint $table) {
                $table->id();
                $table->string('room_id');
                $table->unsignedInteger('round_number');
                $table->json('announcements');
                $table->json('obtained_tricks');
                $table->timestamps();
                $table->unique(['room_id', 'round_number']);
            });
        } else {
            Schema::table('rounds', function (Blueprint $table) {
                if (!Schema::hasColumn('rounds', 'room_id')) {
                    $table->string('room_id');
                }
                if (!Schema::hasColumn('rounds', 'round_number')) {
                    $table->unsignedInteger('round_number');
                }
                if (!Schema::hasColumn('rounds', 'announcements')) {
                    $table->json('announcements')->nullable();
                }
                if (!Schema::hasColumn('rounds', 'obtained_tricks')) {
                    $table->json('obtained_tricks')->nullable();
                }
                // Créer l'unique si possible
                try {
                    $table->unique(['room_id', 'round_number']);
                } catch (\Throwable $e) {
                    // ignore si déjà présent ou si structure ne le permet pas
                }
            });
        }
    }

    public function down(): void
    {
        // Ne pas supprimer la table entière si elle existait déjà
        if (Schema::hasTable('rounds')) {
            Schema::table('rounds', function (Blueprint $table) {
                if (Schema::hasColumn('rounds', 'obtained_tricks')) {
                    $table->dropColumn('obtained_tricks');
                }
                if (Schema::hasColumn('rounds', 'announcements')) {
                    $table->dropColumn('announcements');
                }
                if (Schema::hasColumn('rounds', 'round_number')) {
                    $table->dropColumn('round_number');
                }
                if (Schema::hasColumn('rounds', 'room_id')) {
                    $table->dropColumn('room_id');
                }
            });
        }
    }
};
