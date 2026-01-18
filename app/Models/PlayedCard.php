<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PlayedCard extends Model
{
    use HasFactory;

    protected $primaryKey = 'card_id';
    public $timestamps = false; // La table utilise played_at au lieu de created_at/updated_at

    protected $fillable = [
        'trick_id',
        'player_id', // ✅ Référence à room_players.player_id (comme dans le schéma SQL)
        'card_code',
        'card_value', // ✅ Valeur extraite (A, K, Q, J, 10, 2-9)
        'card_suit', // ✅ Couleur extraite (SPADES, HEARTS, DIAMONDS, CLUBS)
        'played_at', // ✅ Moment où la carte a été jouée (pour l'ordre de jeu)
    ];

    /**
     * Relations
     */
    public function trick()
    {
        return $this->belongsTo(Trick::class, 'trick_id');
    }

    public function player()
    {
        return $this->belongsTo(RoomPlayer::class, 'player_id');
    }
}
