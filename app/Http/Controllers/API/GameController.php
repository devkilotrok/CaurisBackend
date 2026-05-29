<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Game;
use App\Models\Room;
use App\Models\Announcement;
use App\Models\PlayedCard;
use App\Models\Round;
use App\Models\Trick;
use App\Models\User;
use App\Services\WebSocketService;
use App\Services\GameService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

class GameController extends Controller
{
    protected $wsService;
    protected $gameService;

    public function __construct(WebSocketService $wsService, GameService $gameService)
    {
        $this->wsService = $wsService;
        $this->gameService = $gameService;
    }
    /**
     * Historique des parties
     */
    public function history(Request $request)
    {
        try {
            $user = $request->user();

            $games = Game::whereHas('room', function($query) use ($user) {
                $query->whereHas('players', function($q) use ($user) {
                    $q->where('user_id', $user->user_id);
                });
            })
            ->with(['room', 'room.players.user'])
            ->orderBy('started_at', 'desc')
            ->limit(20)
            ->get();

            return response()->json([
                'success' => true,
                'data' => $games
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Détails d'une partie
     */
    public function show($gameId)
    {
        try {
            $game = Game::with(['room', 'room.players.user'])->find($gameId);

            if (!$game) {
                return response()->json([
                    'success' => false,
                    'message' => 'Partie non trouvée'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => $game
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Mélanger et distribuer les cartes
     */
    public function dealCards($gameId)
    {
        try {
            $game = Game::find($gameId);

            if (!$game) {
                return response()->json([
                    'success' => false,
                    'message' => 'Partie non trouvée'
                ], 404);
            }

            // TODO: Appeler l'API externe Deck of Cards (ou simulation locale)
            // Simuler la distribution des cartes
            $cards = $this->simulateCardDeal();

            return response()->json([
                'success' => true,
                'data' => [
                    'deck_id' => $game->deck_id,
                    'cards' => $cards
                ]
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Faire une annonce
     */
    public function announce($gameId, Request $request)
    {
        try {
            // ✅ Validation des données
            $validator = \Illuminate\Support\Facades\Validator::make($request->all(), [
                'round_number' => 'required|integer|min:1',
                'announcement_value' => 'required|integer|between:2,13', // ✅ Entre 2 et 13 plis
                'player_name' => 'nullable|string|max:100', // ✅ Optionnel pour les bots
            ]);

            if ($validator->fails()) {
                Log::warning('Erreur de validation dans announce', [
                    'game_id' => $gameId,
                    'errors' => $validator->errors()->toArray(),
                    'request_data' => $request->all(),
                ]);
                return response()->json([
                    'success' => false,
                    'message' => 'Erreur de validation',
                    'errors' => $validator->errors()
                ], 422);
            }

            $user = $request->user();
            $game = Game::find($gameId);

            if (!$game) {
                Log::warning('Game not found in announce', ['game_id' => $gameId]);
                return response()->json([
                    'success' => false,
                    'message' => 'Partie non trouvée'
                ], 404);
            }

            // ✅ NOUVEAU: Vérifier que la phase d'annonces est active (BDD comme source de vérité)
            $roundNumber = (int)$request->round_number;
            
            // ✅ AMÉLIORATION: Log détaillé des paramètres reçus
            Log::info('Announce request received', [
                'game_id' => $gameId,
                'round_number' => $roundNumber,
                'player_name' => $request->input('player_name'),
                'announcement_value' => $request->input('announcement_value'),
                'room_id' => $game->room_id ?? 'N/A',
            ]);
            
            // ✅ CRITIQUE: Récupérer le round depuis la BDD (source de vérité)
            $round = Round::where('game_id', $gameId)
                ->where('round_number', $roundNumber)
                ->first();

            if (!$round) {
                Log::warning('Round not found in BDD', [
                    'game_id' => $gameId,
                    'round_number' => $roundNumber,
                    'room_id' => $game->room_id ?? null,
                ]);
                return response()->json([
                    'success' => false,
                    'message' => 'Round non trouvé'
                ], 404);
            }
            
            // ✅ VÉRIFICATION BDD: Le round doit être en phase d'annonces
            if ($round->status !== Round::STATUS_ANNOUNCEMENT_PHASE) {
                Log::warning('Announcement phase not active (BDD check failed)', [
                    'game_id' => $gameId,
                    'round_number' => $roundNumber,
                    'round_id' => $round->round_id,
                    'current_status' => $round->status,
                    'expected_status' => Round::STATUS_ANNOUNCEMENT_PHASE,
                    'room_id' => $game->room_id ?? null,
                ]);
                return response()->json([
                    'success' => false,
                    'message' => 'La phase d\'annonces n\'est pas active',
                    'debug' => [
                        'game_id' => $gameId,
                        'round_number' => $roundNumber,
                        'round_status' => $round->status,
                    ]
                ], 400);
            }
            
            // ✅ VÉRIFICATION DU TIMEOUT (BDD)
            if ($round->announcement_end_at && now()->isAfter($round->announcement_end_at)) {
                Log::info('Announcement time elapsed (BDD check)', [
                    'game_id' => $gameId,
                    'round_number' => $roundNumber,
                    'announcement_end_at' => $round->announcement_end_at->toIso8601String(),
                    'current_time' => now()->toIso8601String(),
                ]);
                return response()->json([
                    'success' => false,
                    'message' => 'Le temps d\'annonces est écoulé'
                ], 400);
            }
            
            // ✅ Log pour débogage
            Log::info('Announcement phase verified in BDD', [
                'game_id' => $gameId,
                'round_number' => $roundNumber,
                'round_id' => $round->round_id,
                'status' => $round->status,
                'announcement_end_at' => $round->announcement_end_at?->toIso8601String(),
                'time_remaining_seconds' => $round->announcement_end_at 
                    ? max(0, $round->announcement_end_at->diffInSeconds(now())) 
                    : 'N/A',
            ]);
            
            // ✅ Récupérer les données du cache pour le compteur (optionnel, pour compatibilité)
            $cacheKey = "announcement_phase_{$gameId}_{$roundNumber}";
            $phaseData = Cache::get($cacheKey);
            
            // ✅ AMÉLIORATION: Synchroniser le cache avec la BDD pour éviter les désynchronisations
            $actualAnnouncementCount = Announcement::where('game_id', $gameId)
                ->where('round_number', $roundNumber)
                ->count();
            
            if (!$phaseData) {
                // Si le cache n'existe pas, le créer à partir de la BDD
                $phaseData = [
                    'game_id' => $gameId,
                    'round_number' => $roundNumber,
                    'room_id' => $game->room_id,
                    'start_timestamp' => $round->announcement_end_at 
                        ? $round->announcement_end_at->subSeconds(30)->timestamp 
                        : now()->timestamp,
                    'duration' => 30,
                    'submitted_count' => $actualAnnouncementCount, // ✅ Utiliser le compteur BDD
                    'submitted_players' => Announcement::where('game_id', $gameId)
                        ->where('round_number', $roundNumber)
                        ->pluck('player_id')
                        ->toArray(),
                    'is_complete' => false,
                ];
                Cache::put($cacheKey, $phaseData, 60);
            } else {
                // ✅ Synchroniser le compteur du cache avec la BDD
                if ($phaseData['submitted_count'] != $actualAnnouncementCount) {
                    Log::info('Cache desynchronized, syncing from BDD', [
                        'game_id' => $gameId,
                        'round_number' => $roundNumber,
                        'cache_count' => $phaseData['submitted_count'],
                        'bdd_count' => $actualAnnouncementCount,
                    ]);
                    $phaseData['submitted_count'] = $actualAnnouncementCount;
                    $phaseData['submitted_players'] = Announcement::where('game_id', $gameId)
                        ->where('round_number', $roundNumber)
                        ->pluck('player_id')
                        ->toArray();
                    Cache::put($cacheKey, $phaseData, 60);
                }
            }

            // ✅ NOUVEAU: Si player_name est fourni (pour les bots), trouver le joueur par son nom
            $playerName = $request->input('player_name');
            $roomPlayer = null;
            
            if ($playerName) {
                // Trouver le joueur par son pseudo (pour les bots)
                $roomPlayer = \App\Models\RoomPlayer::where('room_id', $game->room_id)
                    ->whereHas('user', function ($query) use ($playerName) {
                        $query->where('pseudo', $playerName);
                    })
                    ->with('user')
                    ->first();
                
                if (!$roomPlayer) {
                    Log::warning('Player not found by name', [
                        'game_id' => $gameId,
                        'room_id' => $game->room_id,
                        'player_name' => $playerName,
                    ]);
                    return response()->json([
                        'success' => false,
                        'message' => "Joueur '$playerName' non trouvé dans cette partie"
                    ], 404);
                }
                
                Log::info('Bot announcement', [
                    'game_id' => $gameId,
                    'round_number' => $roundNumber,
                    'bot_name' => $playerName,
                    'player_id' => $roomPlayer->player_id,
                    'user_id' => $roomPlayer->user_id,
                ]);
            } else {
                // Trouver le joueur par user_id (pour les joueurs humains)
                if (!$user) {
                    Log::warning('User not authenticated for human announcement', [
                        'game_id' => $gameId,
                    ]);
                    return response()->json([
                        'success' => false,
                        'message' => 'Authentification requise'
                    ], 401);
                }
                
                $roomPlayer = \App\Models\RoomPlayer::where('room_id', $game->room_id)
                    ->where('user_id', $user->user_id)
                    ->with('user')
                    ->first();

                if (!$roomPlayer) {
                    Log::warning('Human player not found in room', [
                        'game_id' => $gameId,
                        'room_id' => $game->room_id,
                        'user_id' => $user->user_id,
                    ]);
                    return response()->json([
                        'success' => false,
                        'message' => 'Vous n\'êtes pas dans cette partie'
                    ], 403);
                }
            }

            // ✅ Vérifier que le joueur n'a pas déjà soumis d'annonce
            $existingAnnouncement = Announcement::where('game_id', $gameId)
                ->where('round_number', $request->round_number)
                ->where('player_id', $roomPlayer->player_id)
                ->first();

            if ($existingAnnouncement) {
                return response()->json([
                    'success' => false,
                    'message' => 'Vous avez déjà soumis votre annonce pour ce round'
                ], 400);
            }

            // ✅ S'assurer que les valeurs sont correctes
            $announcementValue = (int)$request->announcement_value;
            $roundNumber = (int)$request->round_number;
            
            // ✅ Vérifier que l'annonce est entre 2 et 13
            if ($announcementValue < 2 || $announcementValue > 13) {
                Log::warning('Invalid announcement value', [
                    'game_id' => $gameId,
                    'round_number' => $roundNumber,
                    'announcement_value' => $announcementValue,
                    'player_name' => $playerName ?? ($roomPlayer->user->pseudo ?? 'Unknown'),
                ]);
                return response()->json([
                    'success' => false,
                    'message' => 'La valeur d\'annonce doit être entre 2 et 13 plis'
                ], 422);
            }
            
            // Enregistrer l'annonce
            try {
                Announcement::create([
                    'game_id' => $gameId,
                    'round_number' => $roundNumber,
                    'player_id' => $roomPlayer->player_id,
                    'user_id' => $roomPlayer->user_id,
                    'announcement_value' => $announcementValue,
                ]);
                
                Log::info('Announcement created successfully', [
                    'game_id' => $gameId,
                    'round_number' => $roundNumber,
                    'player_id' => $roomPlayer->player_id,
                    'user_id' => $roomPlayer->user_id,
                    'announcement_value' => $announcementValue,
                    'player_name' => $playerName ?? ($roomPlayer->user->pseudo ?? 'Unknown'),
                ]);
            } catch (\Exception $e) {
                Log::error('Error creating announcement', [
                    'game_id' => $gameId,
                    'round_number' => $roundNumber,
                    'player_id' => $roomPlayer->player_id,
                    'user_id' => $roomPlayer->user_id,
                    'announcement_value' => $announcementValue,
                    'error' => $e->getMessage(),
                ]);
                throw $e;
            }

            // ✅ Déterminer le nom du joueur (bot ou humain)
            $announcingPlayerName = $playerName ?? ($roomPlayer->user->pseudo ?? 'Joueur');
            
            // ✅ Mettre à jour le cache avec la nouvelle soumission
            $phaseData['submitted_count'] = ($phaseData['submitted_count'] ?? 0) + 1;
            $phaseData['submitted_players'][] = $roomPlayer->player_id;
            Cache::put($cacheKey, $phaseData, 60);
            
            // ✅ Log pour débogage
            Log::info('Announcement submitted - cache updated', [
                'game_id' => $gameId,
                'round_number' => $roundNumber,
                'player' => $announcingPlayerName,
                'submitted_count' => $phaseData['submitted_count'],
                'submitted_players' => $phaseData['submitted_players'],
            ]);

            // ✅ Émettre announcement_submitted (nouveau nom pour le système simultané)
            $this->wsService->broadcastToRoom($game->room_id, [
                'event' => 'announcement_submitted',
                'data' => [
                    'roomId' => (string) $game->room_id,
                    'round_number' => $request->round_number,
                    'playerName' => $announcingPlayerName,
                    'player_pseudo' => $announcingPlayerName,
                    'announcement' => $request->announcement_value,
                    'announcement_value' => $request->announcement_value,
                    'submitted_count' => $phaseData['submitted_count'],
                ],
            ]);

            // ✅ Vérifier si toutes les soumissions sont faites
            $playerCount = \App\Models\RoomPlayer::where('room_id', $game->room_id)->count();
            
            // ✅ AMÉLIORATION: Vérifier aussi depuis la BDD pour éviter les problèmes de cache
            $actualAnnouncementCount = Announcement::where('game_id', $gameId)
                ->where('round_number', $roundNumber)
                ->count();
            
            Log::info('Checking if all announcements are complete', [
                'game_id' => $gameId,
                'round_number' => $roundNumber,
                'cache_submitted_count' => $phaseData['submitted_count'],
                'bdd_announcement_count' => $actualAnnouncementCount,
                'player_count' => $playerCount,
                'should_complete' => ($phaseData['submitted_count'] >= $playerCount) || ($actualAnnouncementCount >= $playerCount),
            ]);
            
            $isComplete = ($phaseData['submitted_count'] >= $playerCount)
                || ($actualAnnouncementCount >= $playerCount);
            $announcementsMap = null;
            $firstPlayerName = null;

            // ✅ Utiliser la BDD comme source de vérité si le cache est désynchronisé
            if ($isComplete) {
                // ✅ Marquer la phase comme complétée dans la BDD
                $round->status = Round::STATUS_PLAYING;
                $round->announcement_end_at = null; // Plus besoin du timeout
                $round->save();
                
                // Marquer la phase comme complétée dans le cache aussi
                $phaseData['is_complete'] = true;
                Cache::put($cacheKey, $phaseData, 60);

                // Récupérer toutes les annonces
                $allAnnouncements = Announcement::where('game_id', $gameId)
                    ->where('round_number', $request->round_number)
                    ->with('player.user')
                    ->get();

                $announcementsMap = [];
                foreach ($allAnnouncements as $ann) {
                    $playerName = $ann->player->user->pseudo ?? 'Joueur';
                    $announcementsMap[$playerName] = $ann->announcement_value;
                }

                // Déterminer le premier joueur pour le premier pli
                $players = \App\Models\RoomPlayer::where('room_id', $game->room_id)
                    ->with('user')
                    ->get();
                $firstPlayer = $players->first();
                $firstPlayerName = $firstPlayer->user->pseudo ?? 'Joueur';

                // Émettre announcements_complete
                $this->wsService->broadcastToRoom($game->room_id, [
                    'event' => 'announcements_complete',
                    'data' => [
                        'roomId' => (string) $game->room_id,
                        'round_number' => $request->round_number,
                        'announcements' => $announcementsMap,
                        'first_player' => $firstPlayerName,
                    ],
                ]);

                Log::info('All announcements completed (early completion)', [
                    'game_id' => $gameId,
                    'round_number' => $request->round_number,
                    'round_id' => $round->round_id,
                    'round_status' => $round->status,
                    'announcements' => $announcementsMap,
                ]);
            }

            $responseData = [
                'submitted_count' => $phaseData['submitted_count'],
                'players_count' => $playerCount,
                'is_complete' => $isComplete,
                'round_number' => (int) $request->round_number,
            ];

            // ✅ Secours HTTP : le client qui soumet la dernière annonce peut démarrer sans WS
            if ($isComplete && $announcementsMap !== null) {
                $responseData['announcements'] = $announcementsMap;
                $responseData['first_player'] = $firstPlayerName;
                $responseData['round_status'] = Round::STATUS_PLAYING;
            }

            return response()->json([
                'success' => true,
                'message' => 'Annonce enregistrée',
                'data' => $responseData,
            ], 200);

        } catch (\Exception $e) {
            Log::error('Error in announce', [
                'game_id' => $gameId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Erreur: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Jouer une carte
     * 
     * Cette méthode gère :
     * 1. L'enregistrement de la carte jouée
     * 2. La vérification si c'est la 4ème carte du pli
     * 3. Le calcul du gagnant si c'est la 4ème carte
     * 4. L'ajout d'un délai pour permettre l'affichage des 4 cartes
     * 5. La mise à jour de la base de données et la diffusion de l'événement
     */
    public function playCard($gameId, Request $request)
    {
        try {
            $validator = \Illuminate\Support\Facades\Validator::make($request->all(), [
                'round_id' => 'required|integer',
                'trick_id' => 'required|integer',
                'card_code' => 'required|string|size:2',
                'round_number' => 'nullable|integer|min:1',
                'trick_number' => 'nullable|integer|min:1',
                'player_name' => 'nullable|string',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Erreur de validation',
                    'errors' => $validator->errors()
                ], 422);
            }

            $user = $request->user();

            $game = Game::find($gameId);
            if (!$game) {
                return response()->json([
                    'success' => false,
                    'message' => 'Partie non trouvée'
                ], 404);
            }

            $roundId = $request->input('round_id');
            $trickId = $request->input('trick_id');
            $cardCode = $request->input('card_code');
            $roundNumber = $request->input('round_number');
            $trickNumber = $request->input('trick_number');

            // Déterminer le joueur qui joue la carte
            $playerName = $request->input('player_name'); // Optionnel : permet de jouer une carte pour un bot

            if ($playerName) {
                // Chercher l'utilisateur par pseudo (bots compris)
                $targetUser = User::where('pseudo', $playerName)->first();
                if (!$targetUser) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Joueur (player_name) introuvable'
                    ], 404);
                }

                $roomPlayer = \App\Models\RoomPlayer::where('room_id', $game->room_id)
                    ->where('user_id', $targetUser->user_id)
                    ->first();
            } else {
                if (!$user) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Utilisateur non authentifié'
                    ], 401);
                }

                $roomPlayer = \App\Models\RoomPlayer::where('room_id', $game->room_id)
                    ->where('user_id', $user->user_id)
                    ->first();
            }

            if (!$roomPlayer) {
                return response()->json([
                    'success' => false,
                    'message' => 'Vous n\'êtes pas dans cette partie'
                ], 403);
            }

            // 1. Obtenir l'ordre de la carte dans le pli (combien de cartes ont déjà été jouées)
            $cardsInTrick = PlayedCard::where('trick_id', $trickId)->count();
            $cardOrder = $cardsInTrick + 1;
            
            // 2. Si c'est la première carte du pli, mettre à jour lead_player_id dans tricks
            if ($cardOrder === 1) {
                Trick::where('trick_id', $trickId)->update([
                    'lead_player_id' => $roomPlayer->player_id,
                    'status' => 'in_progress',
                ]);
            }

            // 3. Extraire card_value et card_suit depuis card_code
            // Format card_code: "AS" (A=value, S=suit) ou "0H" (0=10, H=suit)
            $cardSuit = substr($cardCode, -1); // Dernier caractère = couleur (S, H, D, C)
            $cardValue = substr($cardCode, 0, -1); // Tout sauf le dernier = valeur (A, K, Q, J, 0, 2-9)
            
            // Convertir "0" en "10" pour card_value
            $cardValueForDB = ($cardValue === '0') ? '10' : $cardValue;
            
            // Convertir les codes courts en noms complets pour card_suit
            $suitMapping = [
                'S' => 'SPADES',
                'H' => 'HEARTS',
                'D' => 'DIAMONDS',
                'C' => 'CLUBS',
            ];
            $cardSuitForDB = $suitMapping[$cardSuit] ?? $cardSuit;

            // ✅ SÉCURITÉ: Vérifier que le round appartient à cette partie
            $round = \App\Models\Round::where('round_id', $roundId)
                ->where('game_id', $gameId)
                ->first();
            
            if (!$round) {
                return response()->json([
                    'success' => false,
                    'message' => 'Round non trouvé ou n\'appartient pas à cette partie'
                ], 404);
            }

            // ✅ SÉCURITÉ: Vérifier que le trick appartient à ce round
            $trick = Trick::where('trick_id', $trickId)
                ->where('round_id', $roundId)
                ->first();
            
            if (!$trick) {
                return response()->json([
                    'success' => false,
                    'message' => 'Pli non trouvé ou n\'appartient pas à ce round'
                ], 404);
            }

            // ✅ SÉCURITÉ: Vérifier que c'est bien le tour de ce joueur
            $currentTurn = $this->gameService->getCurrentTurn($roundId, $trickId);
            if ($currentTurn && $currentTurn['player_id'] != $roomPlayer->player_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Ce n\'est pas votre tour de jouer',
                    'current_player_id' => $currentTurn['player_id'],
                ], 403);
            }

            // ✅ SÉCURITÉ: Valider le format de la carte
            if (!preg_match('/^[AKQJ0-9][SHDC]$/', $cardCode)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Format de carte invalide'
                ], 422);
            }

            // ✅ NOUVEAU: Valider que la carte est jouable selon les règles
            $playableCards = $this->gameService->getPlayableCards($roundId, $trickId, $roomPlayer->player_id);
            if ($playableCards === null) {
                return response()->json([
                    'success' => false,
                    'message' => 'Erreur lors de la validation des cartes jouables'
                ], 500);
            }
            if (!in_array($cardCode, $playableCards)) {
                Log::warning('Carte non jouable rejetée', [
                    'game_id' => $gameId,
                    'trick_id' => $trickId,
                    'player_id' => $roomPlayer->player_id,
                    'player_name' => $playerName,
                    'card_code' => $cardCode,
                    'playable_cards' => $playableCards,
                    'playable_count' => count($playableCards),
                ]);
                
                return response()->json([
                    'success' => false,
                    'message' => 'Cette carte n\'est pas jouable selon les règles du jeu',
                    'playable_cards_count' => count($playableCards), // Aide au débogage
                    // Ne pas renvoyer playable_cards pour éviter l'exploitation
                ], 422);
            }

            // ✅ SÉCURITÉ: Vérifier que le joueur possède bien cette carte
            $distributedCards = $round->distributed_cards ?? [];
            $playerName = $roomPlayer->user->pseudo ?? '';
            $playerCards = $distributedCards[$playerName] ?? [];
            
            // Récupérer les cartes déjà jouées par ce joueur dans ce round
            $playedCardsInRound = PlayedCard::whereHas('trick', function ($query) use ($roundId) {
                $query->where('round_id', $roundId);
            })
            ->where('player_id', $roomPlayer->player_id)
            ->pluck('card_code')
            ->toArray();
            
            $remainingCards = array_diff($playerCards, $playedCardsInRound);
            if (!in_array($cardCode, $remainingCards)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Vous ne possédez pas cette carte'
                ], 403);
            }

            // 4. Enregistrer la carte jouée (format compatible avec le schéma SQL)
            // Le schéma attend: player_id (room_players), card_code, card_value, card_suit, played_at
            $playedCard = PlayedCard::create([
                'trick_id' => $trickId,
                'player_id' => $roomPlayer->player_id, // ✅ Utiliser player_id au lieu de user_id
                'card_code' => $cardCode,
                'card_value' => $cardValueForDB,
                'card_suit' => $cardSuitForDB,
                'played_at' => now(), // ✅ Moment précis où la carte est jouée (pour l'ordre)
            ]);

            Log::info('Card played', [
                'game_id' => $gameId,
                'trick_id' => $trickId,
                'player_id' => $roomPlayer->player_id,
                'user_id' => $roomPlayer->user_id ?? null,
                'card_code' => $cardCode,
                'card_value' => $cardValueForDB,
                'card_suit' => $cardSuitForDB,
                'cards_in_trick' => $cardOrder,
            ]);

            // 5. Diffuser la carte jouée via WebSocket (pour affichage immédiat)
            // Format attendu par Flutter: { 'suit': 'S', 'value': 'A' }
            $cardSuit = substr($cardCode, -1); // Dernier caractère = couleur (S, H, D, C)
            $cardValue = substr($cardCode, 0, -1); // Tout sauf le dernier = valeur (A, K, Q, J, 0, 2-9)
            
            $broadcastPlayerName = $playerName ?: ($user->pseudo ?? 'Joueur');

            $this->wsService->broadcastToRoom($game->room_id, [
                'event' => 'card_played',
                'data' => [
                    'roomId' => (string) $game->room_id,
                    'playerName' => $broadcastPlayerName,
                    'card' => [
                        'suit' => $cardSuit,
                        'value' => $cardValue,
                    ],
                    'trickNumber' => $trickNumber ?? 1,
                    'timestamp' => now()->toIso8601String(),
                ],
            ]);

            // 6. VÉRIFIER SI C'EST LA 4ÈME CARTE
            if ($cardOrder === 4) {
                Log::info('4th card played - queueing trick resolution', [
                    'game_id' => $gameId,
                    'trick_id' => $trickId,
                    'round_id' => $roundId,
                ]);

                // 6. EXÉCUTER LE JOB SYNCHRONEMENT pour gérer le délai et la diffusion
                // Utilisation de dispatchSync() pour exécuter immédiatement sans avoir besoin d'un worker de queue
                // Le Job attendra 1-2 secondes, mettra à jour la BDD et diffusera l'événement
                // Cela garantit que le traitement se fait même sans worker de queue actif
                \App\Jobs\ProcessTrickEndJob::dispatchSync(
                    $gameId,
                    $trickId,
                    $roundId,
                    $game->room_id,
                    $roundNumber ?? 1,
                    $trickNumber ?? 1
                );

                Log::info('Trick end job dispatched', [
                    'game_id' => $gameId,
                    'trick_id' => $trickId,
                ]);

                // 7. Retourner immédiatement la réponse (sans attendre le Job)
                // Le gagnant sera envoyé via l'événement WebSocket trick_completed
                return response()->json([
                    'success' => true,
                    'message' => 'Pli terminé - traitement en cours',
                    'data' => [
                        'trick_completed' => true,
                        'processing' => true, // Indique que le traitement est en cours
                        // Le winner_name sera envoyé via WebSocket dans l'événement trick_completed
                    ]
                ], 200);
            }

            // ✅ SOLUTION 1: Calculer le prochain joueur et le retourner dans la réponse
            // Cela évite que le frontend doive recalculer le tour et crée une race condition
            $currentTurn = null;
            if ($cardOrder < 4) {
                // Calculer le prochain joueur qui doit jouer
                $currentTurn = $this->gameService->getCurrentTurn($roundId, $trickId);
                
                Log::info('Next turn calculated after card played', [
                    'game_id' => $gameId,
                    'trick_id' => $trickId,
                    'round_id' => $roundId,
                    'card_order' => $cardOrder,
                    'current_turn' => $currentTurn,
                ]);
                
                // ✅ SOLUTION 3: Émettre aussi un événement WebSocket pour synchroniser le tour
                // Cela garantit que tous les clients sont synchronisés même si la réponse HTTP est perdue
                if ($currentTurn && isset($currentTurn['player_name'])) {
                    $this->wsService->broadcastToRoom($game->room_id, [
                        'event' => 'turn_changed',
                        'data' => [
                            'roomId' => (string) $game->room_id,
                            'round_id' => $roundId,
                            'trick_id' => $trickId,
                            'current_player_name' => $currentTurn['player_name'],
                            'current_player_id' => $currentTurn['player_id'] ?? null,
                            'position' => $currentTurn['position'] ?? null,
                        ],
                    ]);
                    
                    Log::info('Turn changed event broadcasted', [
                        'game_id' => $gameId,
                        'trick_id' => $trickId,
                        'next_player' => $currentTurn['player_name'],
                    ]);
                }
            }

            // Si ce n'est pas la 4ème carte, retourner le succès avec le prochain joueur
            return response()->json([
                'success' => true,
                'message' => 'Carte jouée',
                'data' => [
                    'cards_in_trick' => $cardOrder,
                    'trick_completed' => false,
                    'current_turn' => $currentTurn, // ✅ Ajouter le prochain joueur
                ]
            ], 200);

        } catch (\Exception $e) {
            Log::error('Error playing card', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Erreur: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtenir ou créer le round_id et trick_id actuels
     * Cette méthode est appelée par Flutter avant de jouer une carte
     * Accepte room_id au lieu de game_id pour simplifier l'appel depuis Flutter
     */
    public function getCurrentTrick(Request $request)
    {
        try {
            $validator = \Illuminate\Support\Facades\Validator::make($request->all(), [
                'room_id' => 'required|string',
                'round_number' => 'required|integer|min:1',
                'trick_number' => 'required|integer|min:1',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Erreur de validation',
                    'errors' => $validator->errors()
                ], 422);
            }

            $roomId = $request->input('room_id');
            $roundNumber = $request->input('round_number');
            $trickNumber = $request->input('trick_number');

            // ✅ Normaliser room_id (int) pour éviter les doublons game_id
            $roomIdNormalized = (int) $roomId;

            // Récupérer ou créer le game depuis le room_id
            $game = Game::firstOrCreate(
                ['room_id' => $roomIdNormalized],
                ['deck_id' => 0]
            );

            // ✅ AMÉLIORATION: Chercher d'abord le round existant avec les cartes distribuées
            // Cela évite de créer un doublon si le round existe déjà
            // ✅ DEBUG: Vérifier tous les rounds existants pour ce game_id et round_number
            $existingRounds = Round::where('game_id', $game->game_id)
                ->where('round_number', $roundNumber)
                ->get(['round_id', 'room_id', 'distributed_cards']);
            
            Log::info('getCurrentTrick: Searching for round', [
                'game_id' => $game->game_id,
                'round_number' => $roundNumber,
                'room_id_search' => $roomIdNormalized,
                'room_id_type' => gettype($roomIdNormalized),
                'existing_rounds_count' => $existingRounds->count(),
                'existing_rounds' => $existingRounds->map(function($r) {
                    return [
                        'round_id' => $r->round_id,
                        'room_id' => $r->room_id,
                        'room_id_type' => gettype($r->room_id),
                        'has_distributed_cards' => !empty($r->distributed_cards),
                    ];
                })->toArray(),
            ]);
            
            $round = Round::where('game_id', $game->game_id)
                ->where('round_number', $roundNumber)
                ->where('room_id', $roomIdNormalized)
                ->first();

            // Si le round n'existe pas, le créer (mais normalement il devrait exister après distributeCards)
            if (!$round) {
                Log::warning('getCurrentTrick: Round not found, creating new one', [
                    'game_id' => $game->game_id,
                    'round_number' => $roundNumber,
                    'room_id' => $roomIdNormalized,
                    'room_id_type' => gettype($roomIdNormalized),
                    'search_criteria' => [
                        'game_id' => $game->game_id,
                        'round_number' => $roundNumber,
                        'room_id' => $roomIdNormalized,
                    ],
                    'existing_rounds_found' => $existingRounds->count(),
                ]);
                
                $round = Round::create([
                    'game_id' => $game->game_id,
                    'round_number' => $roundNumber,
                    'room_id' => $roomIdNormalized,
                    'announcements' => [],
                    'obtained_tricks' => [],
                ]);
            } else {
                Log::info('getCurrentTrick: Round found', [
                    'round_id' => $round->round_id,
                    'game_id' => $game->game_id,
                    'round_number' => $roundNumber,
                    'room_id' => $roomIdNormalized,
                    'has_distributed_cards' => !empty($round->distributed_cards),
                ]);
            }
            
            // ✅ VÉRIFICATION CRITIQUE: Si le round n'a pas de cartes distribuées, c'est une erreur
            // Cela signifie que distributeCards() n'a pas été appelé pour ce round
            if (empty($round->distributed_cards)) {
                Log::error('getCurrentTrick: Round exists but has no distributed cards', [
                    'round_id' => $round->round_id,
                    'game_id' => $game->game_id,
                    'round_number' => $roundNumber,
                    'room_id' => $roomIdNormalized,
                    'round_status' => $round->status ?? 'N/A',
                ]);
                
                return response()->json([
                    'success' => false,
                    'message' => 'Les cartes n\'ont pas encore été distribuées pour ce round. Veuillez attendre la distribution.',
                    'error_code' => 'CARDS_NOT_DISTRIBUTED',
                    'round_id' => $round->round_id,
                ], 409); // 409 Conflict - le round existe mais n'est pas prêt
            }

            // Récupérer le trick s'il existe déjà
            $trick = Trick::where('round_id', $round->round_id)
                ->where('trick_number', $trickNumber)
                ->first();

            // Si le trick n'existe pas encore
            if (!$trick) {
                // ✅ Pour le tout premier pli d'un round (trick 1), on détermine le leader
                //    en fonction du créateur du salon et du numéro de manche :
                //    - Round 1, Trick 1  -> le créateur commence
                //    - Round 2, Trick 1  -> joueur suivant (sens des tours)
                //    - Round 3, Trick 1  -> encore le joueur suivant, etc.
                if ($trickNumber == 1) {
                    // Récupérer tous les joueurs de la room dans l'ordre de position (sens des tours)
                    $players = \App\Models\RoomPlayer::where('room_id', $roomId)
                        ->orderBy('position', 'asc')
                        ->get();

                    if ($players->isEmpty()) {
                        throw new \Exception("Aucun joueur trouvé dans room_players pour room_id={$roomId}");
                    }

                    // Trouver l'index du créateur dans cette liste
                    $creatorIndex = $players->search(function ($p) {
                        return (bool) $p->is_creator === true;
                    });

                    if ($creatorIndex === false) {
                        throw new \Exception("Créateur non marqué dans room_players (is_creator) pour room_id={$roomId}");
                    }

                    $playerCount = $players->count();
                    // Décalage en fonction du numéro de manche (round)
                    // Round 1  -> offset 0 (créateur)
                    // Round 2  -> offset 1 (joueur suivant)
                    // Round 3  -> offset 2, etc.
                    $offset = ($roundNumber - 1) % $playerCount;
                    $leaderIndex = ($creatorIndex + $offset) % $playerCount;
                    $leaderPlayer = $players[$leaderIndex];

                    // Création du pli 1 avec le bon leader pour ce round
                    $trick = Trick::create([
                        'round_id' => $round->round_id,
                        'trick_number' => $trickNumber,
                        'lead_player_id' => $leaderPlayer->player_id,
                        'winner_player_id' => null,
                        'cards_played' => '[]',
                        'status' => 'in_progress',
                    ]);
                } else {
                    // ⚠️ Pour les plis suivants, le leader est le gagnant du pli précédent.
                    //    Ce nouveau pli doit être créé par le backend (ProcessTrickEndJob) une fois
                    //    le pli précédent terminé. Si on arrive ici, c'est que le pli n'est pas prêt.
                    return response()->json([
                        'success' => false,
                        'message' => 'Trick not ready yet',
                    ], 409);
                }
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'game_id' => $game->game_id,
                    'round_id' => $round->round_id,
                    'trick_id' => $trick->trick_id,
                    'round_number' => $roundNumber,
                    'trick_number' => $trickNumber,
                ]
            ], 200);

        } catch (\Exception $e) {
            Log::error('Error getting current trick', [
                'room_id' => $request->input('room_id'),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Erreur: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * ✅ NOUVEAU: Obtenir les cartes jouables pour un joueur
     * GET /api/games/{game_id}/playable-cards
     * 
     * 🔒 SÉCURITÉ:
     * - Authentification requise (middleware auth:sanctum)
     * - Vérification que l'utilisateur fait partie de la partie
     * - Vérification de cohérence des IDs (game_id, round_id, trick_id)
     * - Vérification que le player_id appartient à la partie
     */
    public function getPlayableCards(Request $request, $gameId)
    {
        try {
            $user = $request->user();
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Non authentifié'
                ], 401);
            }

            $request->validate([
                'round_id' => 'required|integer|min:1',
                'trick_id' => 'required|integer|min:1',
                'player_id' => 'required|integer|min:1',
            ]);

            // 1. Vérifier que la partie existe
            $game = Game::find($gameId);
            if (!$game) {
                return response()->json([
                    'success' => false,
                    'message' => 'Partie non trouvée'
                ], 404);
            }

            // 2. Vérifier que l'utilisateur fait partie de cette partie
            $roomPlayer = \App\Models\RoomPlayer::where('room_id', $game->room_id)
                ->where('user_id', $user->user_id)
                ->first();
            
            if (!$roomPlayer) {
                return response()->json([
                    'success' => false,
                    'message' => 'Vous n\'êtes pas dans cette partie'
                ], 403);
            }

            $roundId = $request->input('round_id');
            $trickId = $request->input('trick_id');
            $playerId = $request->input('player_id');

            // 3. Vérifier que le round appartient à cette partie
            $round = \App\Models\Round::where('round_id', $roundId)
                ->where('game_id', $gameId)
                ->first();
            
            if (!$round) {
                return response()->json([
                    'success' => false,
                    'message' => 'Round non trouvé ou n\'appartient pas à cette partie'
                ], 404);
            }

            // 4. Vérifier que le trick appartient à ce round
            $trick = \App\Models\Trick::where('trick_id', $trickId)
                ->where('round_id', $roundId)
                ->first();
            
            if (!$trick) {
                return response()->json([
                    'success' => false,
                    'message' => 'Pli non trouvé ou n\'appartient pas à ce round'
                ], 404);
            }

            // 5. Vérifier que le player_id appartient à cette partie
            $targetPlayer = \App\Models\RoomPlayer::where('player_id', $playerId)
                ->where('room_id', $game->room_id)
                ->first();
            
            if (!$targetPlayer) {
                return response()->json([
                    'success' => false,
                    'message' => 'Joueur non trouvé ou n\'appartient pas à cette partie'
                ], 404);
            }

            // 6. Calculer les cartes jouables
            $playableCards = $this->gameService->getPlayableCards($roundId, $trickId, $playerId);

            if ($playableCards === null) {
                return response()->json([
                    'success' => false,
                    'message' => 'Erreur lors du calcul des cartes jouables'
                ], 500);
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'playable_cards' => $playableCards,
                    'count' => count($playableCards),
                ]
            ], 200);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur de validation',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            Log::error('Error in getPlayableCards', [
                'game_id' => $gameId,
                'user_id' => $request->user()?->user_id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Erreur: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * ✅ NOUVEAU: Obtenir le tour actuel (joueur qui doit jouer)
     * GET /api/games/{game_id}/current-turn
     * 
     * 🔒 SÉCURITÉ:
     * - Authentification requise (middleware auth:sanctum)
     * - Vérification que l'utilisateur fait partie de la partie
     * - Vérification de cohérence des IDs (game_id, round_id, trick_id)
     */
    public function getCurrentTurn(Request $request, $gameId)
    {
        try {
            $user = $request->user();
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Non authentifié'
                ], 401);
            }

            $request->validate([
                'round_id' => 'required|integer|min:1',
                'trick_id' => 'required|integer|min:1',
            ]);

            // 1. Vérifier que la partie existe
            $game = Game::find($gameId);
            if (!$game) {
                return response()->json([
                    'success' => false,
                    'message' => 'Partie non trouvée'
                ], 404);
            }

            // 2. Vérifier que l'utilisateur fait partie de cette partie
            $roomPlayer = \App\Models\RoomPlayer::where('room_id', $game->room_id)
                ->where('user_id', $user->user_id)
                ->first();
            
            if (!$roomPlayer) {
                return response()->json([
                    'success' => false,
                    'message' => 'Vous n\'êtes pas dans cette partie'
                ], 403);
            }

            $roundId = $request->input('round_id');
            $trickId = $request->input('trick_id');

            // 3. Vérifier que le round appartient à cette partie
            $round = \App\Models\Round::where('round_id', $roundId)
                ->where('game_id', $gameId)
                ->first();
            
            if (!$round) {
                return response()->json([
                    'success' => false,
                    'message' => 'Round non trouvé ou n\'appartient pas à cette partie'
                ], 404);
            }

            // 4. Vérifier que le trick appartient à ce round
            $trick = \App\Models\Trick::where('trick_id', $trickId)
                ->where('round_id', $roundId)
                ->first();
            
            if (!$trick) {
                return response()->json([
                    'success' => false,
                    'message' => 'Pli non trouvé ou n\'appartient pas à ce round'
                ], 404);
            }

            // 5. Calculer le tour actuel
            $currentTurn = $this->gameService->getCurrentTurn($roundId, $trickId);

            if ($currentTurn === null) {
                return response()->json([
                    'success' => false,
                    'message' => 'Pli terminé ou erreur lors du calcul du tour'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => $currentTurn
            ], 200);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur de validation',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            Log::error('Error in getCurrentTurn', [
                'game_id' => $gameId,
                'user_id' => $request->user()?->user_id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Erreur: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * ✅ NOUVEAU: Obtenir le tour d'annonces actuel
     * GET /api/games/{game_id}/announcement-turn
     */
    public function getAnnouncementTurn(Request $request, $gameId)
    {
        try {
            $user = $request->user();
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Non authentifié'
                ], 401);
            }

            $request->validate([
                'round_number' => 'required|integer|min:1',
            ]);

            $game = Game::find($gameId);
            if (!$game) {
                return response()->json([
                    'success' => false,
                    'message' => 'Partie non trouvée'
                ], 404);
            }

            // Vérifier que l'utilisateur fait partie de cette partie
            $roomPlayer = \App\Models\RoomPlayer::where('room_id', $game->room_id)
                ->where('user_id', $user->user_id)
                ->first();
            
            if (!$roomPlayer) {
                return response()->json([
                    'success' => false,
                    'message' => 'Vous n\'êtes pas dans cette partie'
                ], 403);
            }

            $roundNumber = $request->input('round_number');
            $turnData = $this->gameService->getAnnouncementTurn($gameId, $roundNumber);

            if ($turnData === null) {
                return response()->json([
                    'success' => false,
                    'message' => 'Erreur lors de la récupération du tour d\'annonces'
                ], 500);
            }

            return response()->json([
                'success' => true,
                'data' => $turnData
            ], 200);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur de validation',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            Log::error('Error in getAnnouncementTurn', [
                'game_id' => $gameId,
                'user_id' => $request->user()?->user_id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Erreur: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Tableau des scores
     */
    public function scores($gameId)
    {
        try {
            $game = Game::find($gameId);

            if (!$game) {
                return response()->json([
                    'success' => false,
                    'message' => 'Partie non trouvée'
                ], 404);
            }

            // TODO: Calculer les scores réels

            return response()->json([
                'success' => true,
                'data' => [
                    'rounds' => [],
                    'global_scores' => [0, 0, 0, 0]
                ]
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Simuler la distribution de cartes
     */
    private function simulateCardDeal()
    {
        // Simuler la distribution de 52 cartes pour 4 joueurs
        $deck = [];
        $suits = ['S', 'H', 'D', 'C'];
        $values = ['A', '2', '3', '4', '5', '6', '7', '8', '9', '0', 'J', 'Q', 'K'];

        foreach ($suits as $suit) {
            foreach ($values as $value) {
                $deck[] = $value . $suit;
            }
        }

        shuffle($deck);

        // Distribuer 13 cartes à chaque joueur
        return [
            'player_1' => array_slice($deck, 0, 13),
            'player_2' => array_slice($deck, 13, 13),
            'player_3' => array_slice($deck, 26, 13),
            'player_4' => array_slice($deck, 39, 13),
        ];
    }
}
