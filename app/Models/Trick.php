<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Trick extends Model
{
    use HasFactory;

    protected $primaryKey = 'trick_id';
    public $timestamps = false; // Schéma SQL sans created_at/updated_at

    protected $fillable = [
        'round_id',
        'trick_number',
        'lead_player_id', // ✅ Référence à room_players.player_id (joueur qui a commencé le pli)
        'winner_player_id', // ✅ Référence à room_players.player_id (gagnant du pli)
        'cards_played', // JSON des cartes jouées
        'status', // 'in_progress' ou 'completed'
    ];

    /**
     * Relations
     */
    public function round()
    {
        return $this->belongsTo(Round::class, 'round_id');
    }

    public function playedCards()
    {
        return $this->hasMany(PlayedCard::class, 'trick_id');
    }

    public function leadPlayer()
    {
        return $this->belongsTo(RoomPlayer::class, 'lead_player_id');
    }

    public function winnerPlayer()
    {
        return $this->belongsTo(RoomPlayer::class, 'winner_player_id');
    }
}
