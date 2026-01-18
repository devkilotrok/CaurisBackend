<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Round extends Model
{
    use HasFactory;

    public $timestamps = true;
    protected $primaryKey = 'round_id';
    public $incrementing = true;
    protected $keyType = 'int';

    protected $fillable = [
        'game_id',
        'room_id',
        'round_number',
        'announcements',
        'obtained_tricks',
        'distributed_cards', // ✅ Cartes distribuées par joueur (JSON)
        'status', // ✅ Statut du round: ANNOUNCEMENT_PHASE, PLAYING, FINISHED
        'announcement_end_at', // ✅ Date/heure de fin de la phase d'annonces
    ];

    protected $casts = [
        'announcements' => 'array',
        'obtained_tricks' => 'array',
        'distributed_cards' => 'array', // ✅ Cast en array pour faciliter l'utilisation
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'announcement_end_at' => 'datetime', // ✅ Cast en datetime
    ];
    
    // ✅ Constantes pour les statuts
    public const STATUS_ANNOUNCEMENT_PHASE = 'ANNOUNCEMENT_PHASE';
    public const STATUS_PLAYING = 'PLAYING';
    public const STATUS_FINISHED = 'FINISHED';
}
