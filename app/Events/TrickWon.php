<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TrickWon implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $roomId;
    public $winnerName;
    public $trickNumber;

    /**
     * Create a new event instance.
     */
    public function __construct($roomId, $winnerName, $trickNumber)
    {
        $this->roomId = $roomId;
        $this->winnerName = $winnerName;
        $this->trickNumber = $trickNumber;
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
        return 'trick.won';
    }

    /**
     * Get the data to broadcast.
     */
    public function broadcastWith()
    {
        return [
            'roomId' => $this->roomId,
            'winnerName' => $this->winnerName,
            'trickNumber' => $this->trickNumber,
            'timestamp' => now()->toIso8601String(),
        ];
    }
}

