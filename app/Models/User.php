<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $primaryKey = 'user_id';
    public $timestamps = true;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'name',
        'pseudo',
        'email',
        'password_hash',
        'first_name',
        'last_name',
        'phone',
        'address',
        'avatar',
        'theme_preference',
        'role',
        'is_active',
        'is_bot',
        'last_login',
        'cauris_balance',
        'is_admin',
    ];

    /**
     * The attributes that should be hidden for serialization.
     */
    protected $hidden = [
        'password_hash',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'is_active' => 'boolean',
        'is_bot' => 'boolean',
        'is_admin' => 'boolean',
        'cauris_balance' => 'integer',
        'company_balance' => 'integer',
        'last_login' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Relations
     */
    public function friendships()
    {
        return $this->hasMany(Friendship::class, 'user_id');
    }

    public function friendRequests()
    {
        return $this->hasMany(FriendRequest::class, 'sender_id');
    }

    public function rooms()
    {
        return $this->hasMany(Room::class, 'creator_id');
    }

    public function roomPlayers()
    {
        return $this->hasMany(RoomPlayer::class, 'user_id');
    }

    public function settings()
    {
        return $this->hasOne(UserSetting::class, 'user_id');
    }

    public function scores()
    {
        return $this->hasMany(Score::class, 'user_id');
    }

    /**
     * Get the name of the unique identifier for the user.
     */
    public function getAuthIdentifierName()
    {
        return 'user_id';
    }

    /**
     * Get the unique identifier for the user.
     */
    public function getAuthIdentifier()
    {
        return $this->user_id;
    }

    /**
     * Get the password for the user.
     */
    public function getAuthPassword()
    {
        return $this->password_hash;
    }

    /**
     * Accessor pour le rôle (gère la transition is_admin -> role)
     */
    public function getRoleAttribute($value)
    {
        if ($value) {
            return $value;
        }

        // Fallback sur is_admin si la colonne role n'est pas remplie ou absente
        if (isset($this->attributes['is_admin']) && $this->attributes['is_admin']) {
            return 'admin';
        }

        return 'user';
    }

}
