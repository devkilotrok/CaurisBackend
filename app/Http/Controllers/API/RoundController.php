<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use App\Models\Room;
use App\Models\Game;
use App\Models\Round;
use App\Services\WebSocketService;
use App\Services\GameService;
use App\Jobs\CompleteAnnouncementPhase;

class RoundController extends Controller
{
    protected $wsService;
    protected $gameService;

    public function __construct(WebSocketService $wsService, GameService $gameService)
    {
        $this->wsService = $wsService;
        $this->gameService = $gameService;
    }

    /**
     * Distribuer les cartes pour un nouveau round
     * Le backend gère le mélange et envoie la distribution via WebSocket
     */
    public function distributeCards(Request $request)
    {
        try {
            $data = $request->validate([
                'room_id' => 'required|string',
                'round_number' => 'required|integer|min:1',
                'test_mode' => 'nullable|boolean',
            ]);

            $room = Room::where('room_id', $data['room_id'])->first();
            if (!$room) {
                return response()->json([
                    'success' => false,
                    'message' => 'Room not found'
                ], 404);
            }

            // Récupérer les joueurs de la room
            $players = $room->players()->with('user')->get();
            if ($players->isEmpty()) {
                return response()->json([
                    'success' => false,
                    'message' => 'No players in room'
                ], 400);
            }

            $requiredPlayers = (int) ($room->max_players ?? 4);
            $playerCount = $players->count();
            $testMode = (bool)($data['test_mode'] ?? false);

            if (!$testMode && $playerCount < $requiredPlayers) {
                return response()->json([
                    'success' => false,
                    'code' => 'ROOM_NOT_FULL',
                    'message' => "La salle attend {$requiredPlayers} joueurs ({$playerCount} présents)",
                    'required_players' => $requiredPlayers,
                    'current_players' => $playerCount,
                ], 409);
            }

            // Info WS seulement (ne bloque plus — replay join_room + /sync couvrent les clients en retard)
            if (!$testMode) {
                $wsStatus = $this->wsService->getRoomStatus($data['room_id']);
                $connectedSockets = (int) ($wsStatus['connected_sockets'] ?? 0);
                $joinedCount = (int) ($wsStatus['joined_count'] ?? 0);

                Log::info('Distribution proceeding — WebSocket room snapshot', [
                    'room_id' => $data['room_id'],
                    'connected_sockets' => $connectedSockets,
                    'joined_count' => $joinedCount,
                    'db_players' => $playerCount,
                ]);
            }

            // Créer le deck de 52 cartes
            $suits = ['S', 'H', 'D', 'C'];
            $values = ['A', '2', '3', '4', '5', '6', '7', '8', '9', '0', 'J', 'Q', 'K'];
            $deck = [];
            
            foreach ($suits as $suit) {
                foreach ($values as $value) {
                    $deck[] = $value . $suit;
                }
            }

            // ✅ Vérifier que le deck contient exactement 52 cartes uniques
            if (count($deck) !== 52) {
                Log::error('Deck creation error: Expected 52 cards, got ' . count($deck));
                return response()->json([
                    'success' => false,
                    'message' => 'Erreur lors de la création du deck'
                ], 500);
            }

            // ✅ Vérifier l'unicité des cartes avant mélange
            $uniqueCards = array_unique($deck);
            if (count($uniqueCards) !== 52) {
                Log::error('Deck contains duplicate cards before shuffle');
                return response()->json([
                    'success' => false,
                    'message' => 'Erreur: cartes dupliquées dans le deck'
                ], 500);
            }

            // Mélanger le deck
            if ($testMode) {
                Log::warning('⚠️ Mode TEST activé pour la distribution des cartes', [
                    'room_id' => $data['room_id'],
                    'round_number' => $data['round_number'],
                ]);
            } else {
                shuffle($deck);
            }

            // ✅ Vérifier l'unicité après mélange
            $uniqueCardsAfterShuffle = array_unique($deck);
            if (count($uniqueCardsAfterShuffle) !== 52) {
                Log::error('Deck contains duplicate cards after shuffle');
                return response()->json([
                    'success' => false,
                    'message' => 'Erreur: cartes dupliquées après mélange'
                ], 500);
            }

            // Distribuer les cartes aux joueurs
            $playerCount = $players->count();
            
            // En mode test, chaque joueur reçoit 13 cartes peu importe le nombre de joueurs
            if ($testMode) {
                $cardsPerPlayer = 13;
            } else {
                $cardsPerPlayer = (int)(52 / $playerCount); // 13 pour 4 joueurs, 26 pour 2 joueurs
            }
            
            // ✅ Vérifier que le nombre de cartes par joueur est correct (sauf en mode test)
            $totalCardsToDistribute = $cardsPerPlayer * $playerCount;
            if (!$testMode && $totalCardsToDistribute > 52) {
                Log::error('Too many cards to distribute: ' . $totalCardsToDistribute . ' for ' . $playerCount . ' players');
                return response()->json([
                    'success' => false,
                    'message' => 'Erreur: trop de cartes à distribuer'
                ], 500);
            }

            $distribution = [];
            $distributedCards = []; // ✅ Tracker les cartes déjà distribuées pour éviter les doublons
            $maxSpadeAttempts = 10;
            $attempt = 0;
            $playersMissingSpades = [];
            
            do {
                $attempt++;
                $distribution = [];
                $distributedCards = [];
                $playersMissingSpades = [];
                
                if ($testMode) {
                    // Mode test: chaque joueur reçoit exactement 13 cartes
                    // Distribution fixe et prévisible pour faciliter les tests
                    $baseTestCards = [
                        // Pique (S)
                        'AS','KS','QS','JS','0S','9S','8S','7S','6S','5S','4S','3S','2S',
                        // Cœur (H)
                        'AH','KH','QH','JH','0H','9H','8H','7H','6H','5H','4H','3H','2H',
                        // Carreau (D)
                        'AD','KD','QD','JD','0D','9D','8D','7D','6D','5D','4D','3D','2D',
                        // Trèfle (C)
                        'AC','KC','QC','JC','0C','9C','8C','7C','6C','5C','4C','3C','2C',
                    ];
                    
                    // Répéter le deck si nécessaire (pour plus de 4 joueurs)
                    $totalCardsNeeded = 13 * $playerCount;
                    $testCards = [];
                    while (count($testCards) < $totalCardsNeeded) {
                        $testCards = array_merge($testCards, $baseTestCards);
                    }
                    // Tronquer à exactement le nombre nécessaire
                    $testCards = array_slice($testCards, 0, $totalCardsNeeded);
                    
                    $cardIndex = 0;
                    foreach ($players as $player) {
                        $playerName = $player->user->pseudo ?? 'Joueur';
                        $playerCards = [];
                        
                        // Distribuer 13 cartes à ce joueur
                        for ($i = 0; $i < 13 && $cardIndex < count($testCards); $i++) {
                            $card = $testCards[$cardIndex];
                            $playerCards[] = $card;
                            $distributedCards[] = $card;
                            $cardIndex++;
                        }
                        
                        $distribution[$playerName] = $playerCards;
                    }
                } else {
                    // Mélanger avant chaque tentative pour varier la distribution
                    shuffle($deck);
                    
                    $cardIndex = 0;
                    foreach ($players as $player) {
                        $playerName = $player->user->pseudo ?? 'Joueur';
                        $playerCards = [];
                        
                        for ($i = 0; $i < $cardsPerPlayer && $cardIndex < count($deck); $i++) {
                            $card = $deck[$cardIndex];
                            
                            // ✅ Vérifier que cette carte n'a pas déjà été distribuée
                            if (in_array($card, $distributedCards)) {
                                Log::error("Duplicate card detected: $card already distributed to another player");
                                return response()->json([
                                    'success' => false,
                                    'message' => "Erreur: carte dupliquée détectée ($card)"
                                ], 500);
                            }
                            
                            $playerCards[] = $card;
                            $distributedCards[] = $card; // ✅ Marquer comme distribuée
                            $cardIndex++;
                        }
                    
                    // ✅ Vérifier que le joueur a bien reçu le bon nombre de cartes
                    if (count($playerCards) !== $cardsPerPlayer) {
                        Log::error("Player $playerName received " . count($playerCards) . " cards instead of $cardsPerPlayer");
                        return response()->json([
                            'success' => false,
                            'message' => "Erreur: nombre de cartes incorrect pour $playerName"
                        ], 500);
                    }
                    
                    // ✅ Vérifier l'unicité des cartes pour ce joueur
                    $uniquePlayerCards = array_unique($playerCards);
                    if (count($uniquePlayerCards) !== count($playerCards)) {
                        Log::error("Player $playerName has duplicate cards in their hand");
                        return response()->json([
                            'success' => false,
                            'message' => "Erreur: cartes dupliquées pour $playerName"
                        ], 500);
                    }
                    
                        $distribution[$playerName] = $playerCards;
                    }
                }
                
                // ✅ Vérifier que chaque joueur possède au moins un pique
                foreach ($distribution as $playerName => $playerCards) {
                    $hasSpade = false;
                    foreach ($playerCards as $card) {
                        if (substr($card, -1) === 'S') {
                            $hasSpade = true;
                            break;
                        }
                    }
                    
                    if (!$hasSpade) {
                        $playersMissingSpades[] = $playerName;
                    }
                }
                
                if (!empty($playersMissingSpades)) {
                    Log::warning('Redistribution nécessaire: certains joueurs n\'ont aucun pique', [
                        'room_id' => $data['room_id'],
                        'round_number' => $data['round_number'],
                        'players_without_spades' => $playersMissingSpades,
                        'attempt' => $attempt,
                        'max_attempts' => $maxSpadeAttempts,
                    ]);
                }
                
            } while (!empty($playersMissingSpades) && $attempt < $maxSpadeAttempts);
            
            if (!empty($playersMissingSpades)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Impossible de garantir au moins un pique par joueur après plusieurs tentatives. Veuillez réessayer.'
                ], 500);
            }

            // ✅ Vérification finale : toutes les cartes distribuées sont uniques (sauf en mode test où on peut avoir des doublons si plus de 4 joueurs)
            $allDistributedCards = [];
            foreach ($distribution as $playerCards) {
                $allDistributedCards = array_merge($allDistributedCards, $playerCards);
            }
            
            if (!$testMode) {
                $uniqueDistributedCards = array_unique($allDistributedCards);
                if (count($uniqueDistributedCards) !== count($allDistributedCards)) {
                    Log::error('Final check: duplicate cards found in distribution', [
                        'total' => count($allDistributedCards),
                        'unique' => count($uniqueDistributedCards)
                    ]);
                    return response()->json([
                        'success' => false,
                        'message' => 'Erreur: cartes dupliquées détectées dans la distribution finale'
                    ], 500);
                }
            }

            // ✅ Vérifier que toutes les cartes distribuées sont dans le deck original (sauf en mode test où on peut avoir plus de 52 cartes)
            if (!$testMode) {
                foreach ($allDistributedCards as $card) {
                    if (!in_array($card, $deck)) {
                        Log::error("Card $card was distributed but not in original deck");
                        return response()->json([
                            'success' => false,
                            'message' => "Erreur: carte invalide distribuée ($card)"
                        ], 500);
                    }
                }
            }

            // ✅ Vérifier que le nombre total de cartes distribuées correspond au nombre attendu
            $expectedTotalCards = $cardsPerPlayer * $playerCount;
            if (count($allDistributedCards) !== $expectedTotalCards) {
                Log::error('Total cards distributed mismatch', [
                    'expected' => $expectedTotalCards,
                    'actual' => count($allDistributedCards),
                    'test_mode' => $testMode
                ]);
                return response()->json([
                    'success' => false,
                    'message' => 'Erreur: nombre total de cartes distribuées incorrect'
                ], 500);
            }

            // ✅ Log de validation réussie
            Log::info('Card distribution validated successfully', [
                'room_id' => $data['room_id'],
                'round_number' => $data['round_number'],
                'test_mode' => $testMode,
                'players' => count($distribution),
                'cards_per_player' => $cardsPerPlayer,
                'total_cards_distributed' => count($allDistributedCards),
                'unique_cards' => $testMode ? 'N/A (test mode)' : count(array_unique($allDistributedCards)),
                'expected_total' => $expectedTotalCards
            ]);

            // Sauvegarder le hash du deck pour vérification
            $deckHash = hash('sha256', implode('-', $deck));
            $game = Game::firstOrCreate(
                ['room_id' => $room->room_id],
                ['deck_id' => 0]
            );
            
            // ✅ NOUVEAU: Utiliser la BDD comme source de vérité pour le statut du round
            $startTimestamp = now()->timestamp;
            $announcementEndAt = now()->addSeconds(30);
            
            // ✅ CRITIQUE: Mettre à jour le round dans la BDD AVANT le broadcast WebSocket
            $round = Round::updateOrCreate(
                [
                    'game_id' => $game->game_id,
                    'round_number' => $data['round_number'],
                    'room_id' => $data['room_id'],
                ],
                [
                    'deck_hash' => $deckHash,
                    'distributed_cards' => $distribution, // ✅ TOUJOURS mettre à jour les cartes distribuées
                    'status' => Round::STATUS_ANNOUNCEMENT_PHASE, // ✅ Statut BDD
                    'announcement_end_at' => $announcementEndAt, // ✅ Timeout BDD
                ]
            );
            
            // ✅ VÉRIFICATION: S'assurer que les cartes distribuées sont bien enregistrées
            $round->refresh();
            $savedDistributedCards = $round->distributed_cards ?? [];
            if (empty($savedDistributedCards)) {
                Log::error('CRITICAL: distributed_cards is empty after save', [
                    'round_id' => $round->round_id,
                    'game_id' => $game->game_id,
                    'round_number' => $data['round_number'],
                    'distribution_keys' => array_keys($distribution),
                ]);
                // Forcer la mise à jour
                $round->distributed_cards = $distribution;
                $round->save();
                $round->refresh();
            }
            
            Log::info('Round cards distribution saved', [
                'round_id' => $round->round_id,
                'game_id' => $game->game_id,
                'round_number' => $data['round_number'],
                'distribution_keys' => array_keys($distribution),
                'saved_keys' => array_keys($round->distributed_cards ?? []),
            ]);
            
            // ✅ VÉRIFICATION: S'assurer que le round est bien mis à jour dans la BDD
            $round->refresh(); // Recharger depuis la BDD pour confirmer
            if ($round->status !== Round::STATUS_ANNOUNCEMENT_PHASE) {
                Log::error('CRITICAL: Round status not set correctly in BDD', [
                    'round_id' => $round->round_id,
                    'game_id' => $game->game_id,
                    'round_number' => $data['round_number'],
                    'actual_status' => $round->status,
                    'expected_status' => Round::STATUS_ANNOUNCEMENT_PHASE,
                ]);
                // Forcer la mise à jour
                $round->status = Round::STATUS_ANNOUNCEMENT_PHASE;
                $round->announcement_end_at = $announcementEndAt;
                $round->save();
            }
            
            // ✅ Log pour débogage avec vérification BDD
            Log::info('Announcement phase started - BDD updated', [
                'round_id' => $round->round_id,
                'game_id' => $game->game_id,
                'round_number' => $data['round_number'],
                'room_id' => $data['room_id'],
                'status' => $round->status,
                'announcement_end_at' => $round->announcement_end_at?->toIso8601String(),
                'start_timestamp' => $startTimestamp,
                'timestamp' => now()->toIso8601String(),
            ]);
            
            // ✅ CONSERVER LE CACHE pour compatibilité et performance (optionnel)
            $cacheKey = "announcement_phase_{$game->game_id}_{$data['round_number']}";
            $phaseData = [
                'game_id' => $game->game_id,
                'round_number' => $data['round_number'],
                'room_id' => $data['room_id'],
                'start_timestamp' => $startTimestamp,
                'duration' => 30,
                'submitted_count' => 0,
                'submitted_players' => [],
                'is_complete' => false,
            ];
            Cache::put($cacheKey, $phaseData, 60);

            // ✅ Broadcast cartes APRÈS persistance BDD (sync HTTP prêt pour les non-créateurs)
            $this->wsService->broadcastToRoom($data['room_id'], [
                'event' => 'card_distribution',
                'data' => [
                    'roomId' => (string) $data['room_id'],
                    'distribution' => $distribution,
                    'round_number' => $data['round_number'],
                    'timestamp' => now()->toIso8601String(),
                ],
            ]);

            // ✅ Émettre l'événement announcement_phase_started APRÈS la mise à jour BDD
            $this->wsService->broadcastToRoom($data['room_id'], [
                'event' => 'announcement_phase_started',
                'data' => [
                    'roomId' => (string) $data['room_id'],
                    'game_id' => $game->game_id, // ✅ Ajouter game_id pour que le frontend puisse l'utiliser
                    'round_number' => $data['round_number'],
                    'start_timestamp' => $startTimestamp,
                    'duration' => 30,
                ],
            ]);
            
            // ✅ Log après émission WebSocket pour tracer la séquence
            Log::info('Announcement phase started event broadcasted', [
                'round_id' => $round->round_id,
                'game_id' => $game->game_id,
                'round_number' => $data['round_number'],
                'room_id' => $data['room_id'],
                'status' => $round->status,
            ]);

            // ✅ Programmer la fin automatique après 30 secondes
            Queue::later(
                now()->addSeconds(30),
                new CompleteAnnouncementPhase($game->game_id, $data['round_number'], $data['room_id'])
            );
            
            // ✅ VÉRIFICATION FINALE: S'assurer que le round est bien en phase d'annonces dans la BDD
            $round->refresh();
            Log::info('Card distribution completed - HTTP response ready', [
                'round_id' => $round->round_id,
                'game_id' => $game->game_id,
                'round_number' => $data['round_number'],
                'status' => $round->status,
                'announcement_end_at' => $round->announcement_end_at?->toIso8601String(),
                'bdd_verified' => $round->status === Round::STATUS_ANNOUNCEMENT_PHASE,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Cards distributed and broadcasted',
                'data' => [
                    'round_number' => $data['round_number'],
                    'distribution' => $distribution,
                    'game_id' => $game->game_id,
                    'announcement_phase' => [
                        'room_id' => (string) $data['room_id'],
                        'game_id' => $game->game_id,
                        'round_number' => $data['round_number'],
                        'start_timestamp' => $startTimestamp,
                        'duration' => 30,
                    ],
                ],
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error: ' . $e->getMessage()
            ], 500);
        }
    }

    public function start(Request $request)
    {
        $data = $request->validate([
            'room_id' => 'nullable',
            'game_id' => 'nullable',
            'round_number' => 'required|integer|min:1',
            'deck_hash' => 'required|string|max:128',
        ]);

        // Résoudre le game_id
        $gameId = $data['game_id'] ?? null;
        if (!$gameId) {
            $room = Room::where('room_id', $data['room_id'] ?? '')->first();
            if ($room) {
                try {
                    $game = Game::firstOrCreate(['room_id' => $room->room_id], ['deck_id' => 0]);
                    $gameId = $game->game_id;
                } catch (\Throwable $e) {}
            }
        }

        if (!$gameId) {
            return response()->json(['success' => false, 'message' => 'Game not found'], 404);
        }

        $round = Round::updateOrCreate(
            [
                'game_id' => $gameId,
                'round_number' => $data['round_number'],
                'room_id' => (string)($data['room_id'] ?? $room->room_id ?? ''),
            ],
            [
                'deck_hash' => $data['deck_hash'],
                'room_id' => (string)($data['room_id'] ?? $room->room_id ?? ''),
            ]
        );

        return response()->json(['success' => true, 'round_id' => $round->round_id]);
    }
    /**
     * Enregistrer un pli gagné et mettre à jour les compteurs
     * Le backend maintient l'état des compteurs et diffuse via WebSocket
     */
    public function recordTrickWon(Request $request)
    {
        try {
            $data = $request->validate([
                'room_id' => 'required|string',
                'round_number' => 'required|integer|min:1',
                'winner_name' => 'required|string',
                'trick_number' => 'required|integer|min:1',
            ]);

            $room = Room::where('room_id', $data['room_id'])->first();
            if (!$room) {
                return response()->json([
                    'success' => false,
                    'message' => 'Room not found'
                ], 404);
            }

            // Récupérer ou créer le round
            $game = Game::firstOrCreate(
                ['room_id' => $room->room_id],
                ['deck_id' => 0]
            );

            $round = Round::firstOrCreate(
                [
                    'game_id' => $game->game_id,
                    'round_number' => $data['round_number'],
                    'room_id' => $data['room_id'],
                ],
                [
                    'announcements' => [],
                    'obtained_tricks' => [],
                ]
            );

            // ✅ Utiliser une transaction pour éviter les race conditions
            DB::transaction(function () use ($round, $data, &$obtainedTricks) {
                // Verrouiller la ligne pour éviter les conflits
                $round = Round::lockForUpdate()->find($round->getKey());
                
                // Récupérer les compteurs actuels
                $obtainedTricks = $round->obtained_tricks ?? [];
                
                // Incrémenter le compteur du gagnant de manière atomique
                $winnerName = $data['winner_name'];
                if (!isset($obtainedTricks[$winnerName])) {
                    $obtainedTricks[$winnerName] = 0;
                }
                $obtainedTricks[$winnerName]++;

                // Mettre à jour le round
                $round->obtained_tricks = $obtainedTricks;
                $round->save();
            });

            Log::info('Trick won recorded', [
                'room_id' => $data['room_id'],
                'round_number' => $data['round_number'],
                'winner' => $winnerName,
                'trick_number' => $data['trick_number'],
                'obtained_tricks' => $obtainedTricks,
            ]);

            // Diffuser les compteurs mis à jour via WebSocket
            $this->wsService->broadcastToRoom($data['room_id'], [
                'event' => 'trick_completed',
                'data' => [
                    'roomId' => $data['room_id'],
                    'round_number' => $data['round_number'],
                    'current_trick_number' => $data['trick_number'],
                    'winner_name' => $winnerName,
                    'winner_player_id' => $data['winner_player_id'] ?? null,
                    'next_trick_number' => ($data['trick_number'] ?? 0) + 1,
                    'next_trick_id' => null,
                    'obtained_tricks' => $obtainedTricks,
                    'timestamp' => now()->toIso8601String(),
                ],
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Trick won recorded',
                'data' => [
                    'winner_name' => $winnerName,
                    'obtained_tricks' => $obtainedTricks,
                ]
            ], 200);

        } catch (\Exception $e) {
            Log::error('Error recording trick won', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error: ' . $e->getMessage()
            ], 500);
        }
    }

    public function save(Request $request)
    {
        $data = $request->validate([
            'room_id' => 'required',
            'round_number' => 'required|integer|min:1',
            'announcements' => 'required|array',
            'obtained_tricks' => 'required|array',
        ]);

        $room = Room::where('room_id', $data['room_id'])->first();
        if (!$room) {
            return response()->json(['success' => false, 'message' => 'Room not found'], 404);
        }

        // Associer à un game_id si la colonne existe dans la table
        $gameId = null;
        try {
            // Récupérer/créer un enregistrement game lié au salon
            $game = Game::firstOrCreate(
                ['room_id' => $room->room_id],
                ['deck_id' => 0]
            );
            $gameId = $game->game_id;
        } catch (\Throwable $e) {
            // Si le modèle/colonne n'existe pas, on ignore
        }

        // Upsert de la manche
        /** @var Round $round */
        $attributes = [
            'room_id' => $room->room_id,
            'round_number' => $data['round_number'],
        ];
        $values = [
            'announcements' => $data['announcements'],
            'obtained_tricks' => $data['obtained_tricks'],
        ];
        if ($gameId !== null) {
            $attributes['game_id'] = $gameId;
            $values['game_id'] = $gameId;
        }

        $round = Round::updateOrCreate($attributes, $values);

        return response()->json([
            'success' => true,
            'round_id' => $round->getKey(),
        ]);
    }

    /**
     * Récupérer les compteurs de plis actuels pour une room donnée
     */
    public function getTrickCounters(string $roomId)
    {
        try {
            $room = Room::where('room_id', $roomId)->first();
            if (!$room) {
                return response()->json([
                    'success' => false,
                    'message' => 'Room not found'
                ], 404);
            }

            $players = $room->players()->with('user')->get();
            $playerNames = $players->map(function ($player) {
                return $player->user->pseudo ?? 'Joueur';
            })->filter()->values();

            $round = Round::where('room_id', $roomId)
                ->orderByDesc('round_number')
                ->first();

            $roundNumber = $round->round_number ?? 1;
            $storedCounters = $round->obtained_tricks ?? [];

            $normalizedCounters = [];
            foreach ($playerNames as $name) {
                $normalizedCounters[$name] = (int)($storedCounters[$name] ?? 0);
            }

            // Inclure également les compteurs enregistrés qui ne figurent plus dans la room
            foreach ($storedCounters as $name => $value) {
                if (!isset($normalizedCounters[$name])) {
                    $normalizedCounters[$name] = (int)$value;
                }
            }

            return response()->json([
                'success' => true,
                'roomId' => $roomId,
                'roundNumber' => $roundNumber,
                'obtainedTricks' => $normalizedCounters,
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error retrieving trick counters', [
                'room_id' => $roomId,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Mettre à jour les compteurs via fallback backend lorsque WebSocket échoue
     */
    public function updateTrickCounters(Request $request, string $roomId)
    {
        try {
            $data = $request->validate([
                'trick_number' => 'required|integer|min:1',
                'winner_name' => 'required|string',
                'obtained_tricks' => 'required|array',
                'round_number' => 'nullable|integer|min:1',
            ]);

            $room = Room::where('room_id', $roomId)->first();
            if (!$room) {
                return response()->json([
                    'success' => false,
                    'message' => 'Room not found'
                ], 404);
            }

            $game = Game::firstOrCreate(
                ['room_id' => $room->room_id],
                ['deck_id' => 0]
            );

            $roundNumber = $data['round_number'] ?? null;
            if (!$roundNumber) {
                $latestRound = Round::where('game_id', $game->game_id)
                    ->where('room_id', $roomId)
                    ->orderByDesc('round_number')
                    ->first();
                $roundNumber = $latestRound->round_number ?? 1;
            }

            $round = Round::firstOrCreate(
                [
                    'game_id' => $game->game_id,
                    'round_number' => $roundNumber,
                    'room_id' => $roomId,
                ],
                [
                    'announcements' => [],
                    'obtained_tricks' => [],
                ]
            );

            $incomingCounters = [];
            foreach ($data['obtained_tricks'] as $name => $value) {
                $incomingCounters[(string)$name] = (int)$value;
            }

            DB::transaction(function () use ($round, $incomingCounters, &$mergedCounters) {
                /** @var Round $lockedRound */
                $lockedRound = Round::lockForUpdate()->find($round->getKey());
                $mergedCounters = $lockedRound->obtained_tricks ?? [];

                foreach ($incomingCounters as $playerName => $incomingValue) {
                    $currentValue = (int)($mergedCounters[$playerName] ?? 0);
                    $mergedCounters[$playerName] = max($currentValue, $incomingValue);
                }

                $lockedRound->obtained_tricks = $mergedCounters;
                $lockedRound->save();
            });

            Log::info('Trick counters updated via fallback', [
                'room_id' => $roomId,
                'round_number' => $roundNumber,
                'trick_number' => $data['trick_number'],
                'obtained_tricks' => $mergedCounters ?? $incomingCounters,
            ]);

            $this->wsService->broadcastToRoom($roomId, [
                'event' => 'trick_completed',
                'data' => [
                    'roomId' => $roomId,
                    'round_number' => $roundNumber,
                    'current_trick_number' => $data['trick_number'],
                    'winner_name' => $data['winner_name'],
                    'winner_player_id' => null,
                    'next_trick_number' => ($data['trick_number'] ?? 0) + 1,
                    'next_trick_id' => null,
                    'obtained_tricks' => $mergedCounters ?? $incomingCounters,
                    'timestamp' => now()->toIso8601String(),
                ],
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Trick counters synchronized via backend',
                'obtainedTricks' => $mergedCounters ?? $incomingCounters,
                'roundNumber' => $roundNumber,
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error updating trick counters via fallback', [
                'room_id' => $roomId,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * GET /api/rounds/{round_id}/scores
     * Récupérer les scores calculés d'un round
     */
    public function getRoundScores($roundId)
    {
        try {
            $round = Round::find($roundId);
            if (!$round) {
                return response()->json([
                    'success' => false,
                    'message' => 'Round not found'
                ], 404);
            }

            $scoresData = $this->gameService->calculateRoundScores($roundId);
            if (!$scoresData) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unable to calculate scores for this round'
                ], 500);
            }

            return response()->json([
                'success' => true,
                'data' => $scoresData
            ], 200);

        } catch (\Exception $e) {
            Log::error('Error getting round scores', [
                'round_id' => $roundId,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * ✅ NOUVEAU: Valider les annonces d'un round
     * POST /api/rounds/validate-announcements
     * 
     * 🔒 SÉCURITÉ:
     * - Authentification requise (middleware auth:sanctum)
     * - Vérification que l'utilisateur fait partie de la partie
     * - Vérification que les annonces correspondent aux joueurs de la partie
     * - Validation stricte des valeurs (0-13)
     */
    public function validateAnnouncements(Request $request)
    {
        try {
            $user = $request->user();
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Non authentifié'
                ], 401);
            }

            $data = $request->validate([
                'round_id' => 'required|integer|min:1',
                'announcements' => 'required|array',
                'announcements.*' => 'required|integer|min:0|max:13',
            ]);

            $roundId = $data['round_id'];
            $announcements = $data['announcements'];

            // 1. Vérifier que le round existe
            $round = Round::find($roundId);
            if (!$round) {
                return response()->json([
                    'success' => false,
                    'message' => 'Round non trouvé'
                ], 404);
            }

            // 2. Vérifier que l'utilisateur fait partie de cette partie
            $roomPlayer = \App\Models\RoomPlayer::where('room_id', $round->room_id)
                ->where('user_id', $user->user_id)
                ->first();
            
            if (!$roomPlayer) {
                return response()->json([
                    'success' => false,
                    'message' => 'Vous n\'êtes pas dans cette partie'
                ], 403);
            }

            // 3. Vérifier que les annonces correspondent aux joueurs de la partie
            $players = \App\Models\RoomPlayer::where('room_id', $round->room_id)
                ->with('user')
                ->get();
            
            $playerNames = $players->pluck('user.pseudo')->filter()->toArray();
            $announcementNames = array_keys($announcements);
            
            // Vérifier que tous les joueurs ont une annonce
            if (count($announcementNames) !== count($playerNames)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Le nombre d\'annonces ne correspond pas au nombre de joueurs'
                ], 422);
            }

            // Vérifier que tous les noms correspondent
            foreach ($announcementNames as $name) {
                if (!in_array($name, $playerNames)) {
                    return response()->json([
                        'success' => false,
                        'message' => "Le joueur '$name' n'appartient pas à cette partie"
                    ], 422);
                }
            }

            // 4. Valider les annonces
            $validation = $this->gameService->validateAnnouncements($roundId, $announcements);

            if (!$validation['valid']) {
                return response()->json([
                    'success' => false,
                    'message' => $validation['message'],
                    'errors' => $validation['errors'] ?? [],
                ], 422);
            }

            return response()->json([
                'success' => true,
                'message' => $validation['message'],
            ], 200);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur de validation',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            Log::error('Error validating announcements', [
                'round_id' => $request->input('round_id'),
                'user_id' => $request->user()?->user_id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Error: ' . $e->getMessage()
            ], 500);
        }
    }
}
