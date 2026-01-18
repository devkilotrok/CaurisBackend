<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Room extends Model
{
    use HasFactory;

    protected $primaryKey = 'room_id';
    public $timestamps = true;

    protected $fillable = [
        'room_name',
        'room_code',
        'creator_id',
        'minimum_bet',
        'status',
        'max_players',
        'started_at',
        'finished_at',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Relations
     */
    public function creator()
    {
        return $this->belongsTo(User::class, 'creator_id');
    }

    public function players()
    {
        return $this->hasMany(RoomPlayer::class, 'room_id');
    }

    public function games()
    {
        return $this->hasMany(Game::class, 'room_id');
    }

    public function invitations()
    {
        return $this->hasMany(RoomInvitation::class, 'room_id');
    }
}
