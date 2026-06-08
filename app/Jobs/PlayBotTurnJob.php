<?php

namespace App\Jobs;

use App\Models\RoomPlayer;
use App\Services\GameService;
use App\Services\WebSocketService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Joue automatiquement pour un bot quand c'est son tour.
 * Garantit la continuité même si aucun client humain ne pilote les bots.
 */
class PlayBotTurnJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        protected int $gameId,
        protected int $trickId,
        protected int $roundId,
        protected $roomId,
        protected int $roundNumber,
        protected int $trickNumber,
        protected ?int $expectedPlayerId = null,
    ) {
    }

    public function handle(GameService $gameService, WebSocketService $wsService): void
    {
        $delay = (int) env('BOT_PLAY_DELAY', 2);
        if ($delay > 0) {
            sleep($delay);
        }

        $result = $gameService->autoPlayBotIfTheirTurn(
            $this->gameId,
            $this->trickId,
            $this->roundId,
            $this->roomId,
            $this->roundNumber,
            $this->trickNumber,
            $this->expectedPlayerId,
            $wsService
        );

        if (!$result['played'] ?? false) {
            Log::info('PlayBotTurnJob: bot did not play', [
                'game_id' => $this->gameId,
                'trick_id' => $this->trickId,
                'reason' => $result['reason'] ?? 'unknown',
            ]);
            return;
        }

        // Enchaîner si le joueur suivant est aussi un bot
        $nextPlayerId = $result['next_player_id'] ?? null;
        if ($nextPlayerId === null) {
            return;
        }

        $nextPlayer = RoomPlayer::with('user')->find($nextPlayerId);
        if (!$nextPlayer || !($nextPlayer->user->is_bot ?? false)) {
            return;
        }

        $nextTrickId = $result['next_trick_id'] ?? $this->trickId;
        $nextTrickNumber = $result['next_trick_number'] ?? $this->trickNumber;

        self::dispatchSync(
            $this->gameId,
            $nextTrickId,
            $this->roundId,
            $this->roomId,
            $this->roundNumber,
            $nextTrickNumber,
            $nextPlayerId
        );
    }
}
