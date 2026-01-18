<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Game extends Model
{
    use HasFactory;

    protected $primaryKey = 'game_id';
    public $timestamps = true;

    protected $fillable = [
        'room_id',
        'deck_id',
        'started_at',
        'finished_at',
        'winner_id',
        'final_scores',
    ];

    protected $casts = [
        'final_scores' => 'array',
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
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

    public function winner()
    {
        return $this->belongsTo(User::class, 'winner_id');
    }

    public function announcements()
    {
        return $this->hasMany(Announcement::class, 'game_id');
    }

    public function rounds()
    {
        return $this->hasMany(Round::class, 'game_id');
    }

    public function scores()
    {
        return $this->hasMany(Score::class, 'game_id');
    }
}
