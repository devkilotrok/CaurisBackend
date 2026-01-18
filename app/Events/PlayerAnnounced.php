<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PlayerAnnounced implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $roomId;
    public $playerName;
    public $announcement;
    public $totalAnnouncements;
    public $nextPlayer;

    /**
     * Create a new event instance.
     */
    public function __construct($roomId, $playerName, $announcement, $totalAnnouncements, $nextPlayer)
    {
        $this->roomId = $roomId;
        $this->playerName = $playerName;
        $this->announcement = $announcement;
        $this->totalAnnouncements = $totalAnnouncements;
        $this->nextPlayer = $nextPlayer;
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
        return 'player.announced';
    }

    /**
     * Get the data to broadcast.
     */
    public function broadcastWith()
    {
        return [
            'roomId' => $this->roomId,
            'playerName' => $this->playerName,
            'announcement' => $this->announcement,
            'totalAnnouncements' => $this->totalAnnouncements,
            'nextPlayer' => $this->nextPlayer,
            'timestamp' => now()->toIso8601String(),
        ];
    }
}

