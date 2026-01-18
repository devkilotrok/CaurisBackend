<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Announcement extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'game_id',
        'round_number',
        'player_id',
        'user_id',
        'announcement_value',
    ];

    /**
     * Get the player that made this announcement.
     */
    public function player()
    {
        return $this->belongsTo(RoomPlayer::class, 'player_id');
    }
}
