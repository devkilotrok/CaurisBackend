<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Services\WebSocketService;
use App\Services\GameService;
use App\Models\Trick;
use App\Models\User;
use App\Models\Room;
use App\Jobs\PlayBotTurnJob;
use App\Models\RoomPlayer;
use Illuminate\Support\Facades\Log;

class ProcessTrickEndJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $gameId;
    protected $trickId;
    protected $roundId;
    protected $roomId;
    protected $roundNumber;
    protected $trickNumber;
    /**
     * Create a new job instance.
     */
    public function __construct(
        $gameId,
        $trickId,
        $roundId,
        $roomId,
        $roundNumber,
        $trickNumber
    ) {
        $this->gameId = $gameId;
        $this->trickId = $trickId;
        $this->roundId = $roundId;
        $this->roomId = $roomId;
        $this->roundNumber = $roundNumber;
        $this->trickNumber = $trickNumber;
    }

    /**
     * Execute the job.
     * 
     * Cette méthode :
     * 1. Attend 2 secondes pour permettre l'affichage des 4 cartes
     * 2. Met à jour la base de données avec le gagnant
     * 3. Diffuse l'événement trick_completed via WebSocket
     */
    public function handle(WebSocketService $wsService, GameService $gameService): void
    {
        try {
            Log::info('ProcessTrickEndJob started', [
                'game_id' => $this->gameId,
                'trick_id' => $this->trickId,
            ]);

            // 1. Attendre un délai configurable pour permettre l'affichage des 4 cartes au centre
            // 2 secondes par défaut : laisse le temps aux clients d'afficher la 4e carte avant trick_completed
            $animationDelay = env('TRICK_ANIMATION_DELAY', 2);
            sleep((int) $animationDelay);

            // 2. Traiter complètement la fin du pli via GameService (compteurs + trick + winner_player_id)
            $result = $gameService->processTrickWinner(
                $this->trickId,
                $this->roundId,
                $this->roomId,
                $this->roundNumber,
                $this->trickNumber
            );

            if (!$result || !isset($result['winner_player_id'])) {
                Log::error('Failed to process trick winner in ProcessTrickEndJob', [
                    'trick_id' => $this->trickId,
                ]);
                return;
            }

            $winnerPlayerId = $result['winner_player_id'];
            $obtainedTricks = $result['obtained_tricks'] ?? [];
            $winnerName = $result['winner_name'] ?? 'Joueur';
            $trickCards = $result['trick_cards'] ?? [];
            $nextTrickId = null;
            $nextTrickNumber = null;

            // 3. Créer le pli suivant (trick N+1) avec le gagnant comme leader,
            //    sauf si on est déjà au 13e pli.
            $nextTrickId = null;
            $nextTrickNumber = null;
            try {
                if ($this->trickNumber < 13) {
                    $nextTrick = Trick::firstOrCreate(
                        [
                            'round_id' => $this->roundId,
                            'trick_number' => $this->trickNumber + 1,
                        ],
                        [
                            'lead_player_id' => $winnerPlayerId,
                            'winner_player_id' => null,
                            'cards_played' => '[]',
                            'status' => 'in_progress', // ✅ Utiliser 'in_progress' car c'est la seule valeur acceptée par l'ENUM
                        ]
                    );
                    $nextTrickId = $nextTrick->trick_id;
                    $nextTrickNumber = $nextTrick->trick_number;
                    
                    Log::info('Next trick created', [
                        'current_trick_id' => $this->trickId,
                        'next_trick_id' => $nextTrickId,
                        'next_trick_number' => $nextTrickNumber,
                        'winner_player_id' => $winnerPlayerId,
                        'winner_name' => $winnerName,
                    ]);
                } else {
                    Log::info('Last trick completed (trick 13)', [
                        'trick_id' => $this->trickId,
                        'trick_number' => $this->trickNumber,
                    ]);
                }
            } catch (\Exception $e) {
                Log::error('Could not create next trick in ProcessTrickEndJob', [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                    'round_id' => $this->roundId,
                    'current_trick_number' => $this->trickNumber,
                ]);
            }

            // 4. Calculer les scores actuels du round
            $roundScores = $gameService->calculateRoundScores($this->roundId);
            $scores = $roundScores['scores'] ?? [];

            // 5. Diffuser l'événement de fin de pli via WebSocket
            $broadcastData = [
                'roomId' => (string) $this->roomId,
                'round_id' => $this->roundId, // ✅ Ajouter round_id pour faciliter la récupération
                'round_number' => $this->roundNumber,
                'current_trick_id' => $this->trickId, // ✅ Ajouter current_trick_id
                'current_trick_number' => $this->trickNumber,
                'winner_name' => $winnerName,
                'winner_player_id' => $winnerPlayerId,
                'next_trick_number' => $nextTrickNumber,
                'next_trick_id' => $nextTrickId,
                // Compteurs de plis mis à jour (par joueur) pour ce round
                'obtained_tricks' => $obtainedTricks,
                // Les 4 cartes du pli terminé (source de vérité pour resync clients)
                'trick_cards' => $trickCards,
                // Scores calculés par le backend
                'scores' => $scores,
                'timestamp' => now()->toIso8601String(),
            ];
            
            Log::info('Broadcasting trick_completed event', [
                'room_id' => $this->roomId,
                'next_trick_id' => $nextTrickId,
                'next_trick_number' => $nextTrickNumber,
                'winner_name' => $winnerName,
            ]);
            
            $wsService->broadcastToRoom($this->roomId, [
                'event' => 'trick_completed',
                'data' => $broadcastData,
            ]);

            // 6. Diffuser également un événement dédié pour la mise à jour des scores
            $wsService->broadcastToRoom($this->roomId, [
                'event' => 'round_scores_updated',
                'data' => [
                    'roomId' => (string) $this->roomId,
                    'round_id' => $this->roundId,
                    'round_number' => $this->roundNumber,
                    'announcements' => $roundScores['announcements'] ?? [],
                    'obtained_tricks' => $obtainedTricks,
                    'scores' => $scores,
                    'timestamp' => now()->toIso8601String(),
                ],
            ]);

            Log::info('ProcessTrickEndJob completed', [
                'game_id' => $this->gameId,
                'trick_id' => $this->trickId,
                'winner_name' => $winnerName,
                'obtained_tricks' => $obtainedTricks,
            ]);

            // Si le gagnant du pli est un bot, lancer le pli suivant côté serveur
            if ($nextTrickId && $nextTrickNumber && $winnerPlayerId) {
                $winnerPlayer = RoomPlayer::with('user')->find($winnerPlayerId);
                if ($winnerPlayer && ($winnerPlayer->user->is_bot ?? false)) {
                    PlayBotTurnJob::dispatchSync(
                        $this->gameId,
                        $nextTrickId,
                        $this->roundId,
                        $this->roomId,
                        $this->roundNumber,
                        $nextTrickNumber,
                        $winnerPlayerId
                    );
                }
            }

        } catch (\Exception $e) {
            Log::error('Error in ProcessTrickEndJob', [
                'game_id' => $this->gameId,
                'trick_id' => $this->trickId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }
}
