<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AdminMessage extends Model
{
    use HasFactory;

    protected $fillable = [
        'sender_id',
        'recipient_id',
        'subject',
        'message',
        'media_type',
        'media_url',
        'media_data_temp',
        'status',
        'parent_id',
        'reply_to_message_id',
        'read_at',
        'message_status',
        'is_edited',
        'is_deleted',
        'edited_at',
        'delivered_at',
        'error_message',
        'reactions',
    ];

    protected $casts = [
        'read_at' => 'datetime',
        'edited_at' => 'datetime',
        'delivered_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'is_edited' => 'boolean',
        'is_deleted' => 'boolean',
        'reactions' => 'array',
    ];

    public function sender()
    {
        return $this->belongsTo(User::class, 'sender_id', 'user_id');
    }

    public function recipient()
    {
        return $this->belongsTo(User::class, 'recipient_id', 'user_id');
    }

    public function parent()
    {
        return $this->belongsTo(AdminMessage::class, 'parent_id');
    }

    public function replies()
    {
        return $this->hasMany(AdminMessage::class, 'parent_id');
    }

    public function replyTo()
    {
        return $this->belongsTo(AdminMessage::class, 'reply_to_message_id');
    }

    public function repliedBy()
    {
        return $this->hasMany(AdminMessage::class, 'reply_to_message_id');
    }
}

