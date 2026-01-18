<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Models\Game;
use App\Models\RoomPlayer;
use App\Models\Announcement;
use App\Services\WebSocketService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class CompleteAnnouncementPhase implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $gameId;
    protected $roundNumber;
    protected $roomId;

    /**
     * Create a new job instance.
     */
    public function __construct($gameId, $roundNumber, $roomId)
    {
        $this->gameId = $gameId;
        $this->roundNumber = $roundNumber;
        $this->roomId = $roomId;
    }

    /**
     * Execute the job.
     */
    public function handle(WebSocketService $wsService)
    {
        // ✅ NOUVEAU: Utiliser la BDD comme source de vérité
        $round = \App\Models\Round::where('game_id', $this->gameId)
            ->where('round_number', $this->roundNumber)
            ->first();

        if (!$round) {
            Log::error('Round not found in CompleteAnnouncementPhase', [
                'game_id' => $this->gameId,
                'round_number' => $this->roundNumber,
            ]);
            return;
        }

        // ✅ Vérifier que le round est toujours en phase d'annonces
        if ($round->status !== \App\Models\Round::STATUS_ANNOUNCEMENT_PHASE) {
            Log::info('Announcement phase already completed (BDD check)', [
                'game_id' => $this->gameId,
                'round_number' => $this->roundNumber,
                'round_id' => $round->round_id,
                'current_status' => $round->status,
            ]);
            return;
        }
        
        // ✅ Vérifier que le timeout n'a pas déjà été dépassé (sécurité supplémentaire)
        if ($round->announcement_end_at && now()->isBefore($round->announcement_end_at)) {
            Log::info('Announcement phase timeout not yet reached', [
                'game_id' => $this->gameId,
                'round_number' => $this->roundNumber,
                'announcement_end_at' => $round->announcement_end_at->toIso8601String(),
                'current_time' => now()->toIso8601String(),
            ]);
            return;
        }
        
        // ✅ Récupérer le cache pour compatibilité (optionnel)
        $cacheKey = "announcement_phase_{$this->gameId}_{$this->roundNumber}";
        $phaseData = Cache::get($cacheKey);

        // Récupérer tous les joueurs de la partie
        $game = Game::find($this->gameId);
        if (!$game) {
            Log::error('Game not found in CompleteAnnouncementPhase', [
                'game_id' => $this->gameId,
            ]);
            return;
        }

        $players = RoomPlayer::where('room_id', $this->roomId)
            ->with('user')
            ->get();

        // Récupérer les annonces déjà faites
        $existingAnnouncements = Announcement::where('game_id', $this->gameId)
            ->where('round_number', $this->roundNumber)
            ->with('player.user')
            ->get();

        $announcedPlayerIds = $existingAnnouncements->pluck('player_id')->toArray();
        $announcementsMap = [];

        // Construire la map des annonces existantes
        foreach ($existingAnnouncements as $ann) {
            $playerName = $ann->player->user->pseudo ?? 'Joueur';
            $announcementsMap[$playerName] = $ann->announcement_value;
        }

        // Assigner 2 plis par défaut aux joueurs qui n'ont pas annoncé
        $defaultAnnouncement = 2;
        foreach ($players as $player) {
            $playerId = $player->player_id;
            $playerName = $player->user->pseudo ?? 'Joueur';

            if (!in_array($playerId, $announcedPlayerIds)) {
                // Créer l'annonce par défaut
                Announcement::create([
                    'game_id' => $this->gameId,
                    'round_number' => $this->roundNumber,
                    'player_id' => $playerId,
                    'user_id' => $player->user_id,
                    'announcement_value' => $defaultAnnouncement,
                ]);

                $announcementsMap[$playerName] = $defaultAnnouncement;

                Log::info('Default announcement assigned', [
                    'game_id' => $this->gameId,
                    'round_number' => $this->roundNumber,
                    'player' => $playerName,
                    'announcement' => $defaultAnnouncement,
                ]);
            }
        }

        // ✅ CRITIQUE: Marquer la phase comme complétée dans la BDD
        $round->status = \App\Models\Round::STATUS_PLAYING;
        $round->announcement_end_at = null; // Plus besoin du timeout
        $round->save();
        
        // Marquer la phase comme complétée dans le cache aussi (pour compatibilité)
        if ($phaseData) {
            Cache::put($cacheKey, array_merge($phaseData, [
                'is_complete' => true,
            ]), 60);
        }

        // Déterminer le premier joueur pour le premier pli
        $firstPlayer = $players->first();
        $firstPlayerName = $firstPlayer->user->pseudo ?? 'Joueur';

        // Émettre l'événement announcements_complete
        $wsService->broadcastToRoom($this->roomId, [
            'event' => 'announcements_complete',
            'data' => [
                'roomId' => (string) $this->roomId,
                'round_number' => $this->roundNumber,
                'announcements' => $announcementsMap,
                'first_player' => $firstPlayerName,
            ],
        ]);

        Log::info('Announcement phase completed (timeout - BDD updated)', [
            'game_id' => $this->gameId,
            'round_number' => $this->roundNumber,
            'round_id' => $round->round_id,
            'round_status' => $round->status,
            'announcements' => $announcementsMap,
        ]);
    }
}
