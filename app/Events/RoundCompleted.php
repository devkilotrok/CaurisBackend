<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class RoundCompleted implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $roomId;
    public $roundNumber;
    public $scores;
    public $hasWinner;

    /**
     * Create a new event instance.
     */
    public function __construct($roomId, $roundNumber, $scores, $hasWinner = false)
    {
        $this->roomId = $roomId;
        $this->roundNumber = $roundNumber;
        $this->scores = $scores;
        $this->hasWinner = $hasWinner;
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
        return 'round.completed';
    }

    /**
     * Get the data to broadcast.
     */
    public function broadcastWith()
    {
        return [
            'roomId' => $this->roomId,
            'roundNumber' => $this->roundNumber,
            'scores' => $this->scores,
            'hasWinner' => $this->hasWinner,
            'timestamp' => now()->toIso8601String(),
        ];
    }
}

