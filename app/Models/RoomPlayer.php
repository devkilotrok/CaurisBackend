<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RoomPlayer extends Model
{
    use HasFactory;

    protected $primaryKey = 'player_id';
    public $timestamps = true;

    protected $fillable = [
        'room_id',
        'user_id',
        'position',
        'is_creator',
        'status',
        'joined_at',
        'is_replacement_bot',
        'replaced_player_name',
        'is_excluded',
    ];

    protected $casts = [
        'is_creator' => 'boolean',
        'joined_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Relations
     */
    public function room()
    {
        return $this->belongsTo(Room::class, 'room_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
