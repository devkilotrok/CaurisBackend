<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class RoomStarted implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $roomId;
    public $players;
    public $roundNumber;

    /**
     * Create a new event instance.
     */
    public function __construct($roomId, $players, $roundNumber = 1)
    {
        $this->roomId = $roomId;
        $this->players = $players;
        $this->roundNumber = $roundNumber;
    }

    /**
     * Get the channels the event should broadcast on.
     */
    public function broadcastOn()
    {
        return new Channel('room.' . $this->roomId);
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs()
    {
        return 'room.started';
    }

    /**
     * Get the data to broadcast.
     */
    public function broadcastWith()
    {
        return [
            'roomId' => $this->roomId,
            'players' => $this->players,
            'roundNumber' => $this->roundNumber,
            'timestamp' => now()->toIso8601String(),
        ];
    }
}

