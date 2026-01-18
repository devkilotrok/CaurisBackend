<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class CardPlayed implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $roomId;
    public $playerName;
    public $card;
    public $trickNumber;
    public $isWinner;

    /**
     * Create a new event instance.
     */
    public function __construct($roomId, $playerName, $card, $trickNumber, $isWinner = false)
    {
        $this->roomId = $roomId;
        $this->playerName = $playerName;
        $this->card = $card;
        $this->trickNumber = $trickNumber;
        $this->isWinner = $isWinner;
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
        return 'card.played';
    }

    /**
     * Get the data to broadcast.
     */
    public function broadcastWith()
    {
        return [
            'roomId' => $this->roomId,
            'playerName' => $this->playerName,
            'card' => $this->card,
            'trickNumber' => $this->trickNumber,
            'isWinner' => $this->isWinner,
            'timestamp' => now()->toIso8601String(),
        ];
    }
}

