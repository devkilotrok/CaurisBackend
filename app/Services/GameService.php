<?php

namespace App\Services;

use App\Models\PlayedCard;
use App\Models\Room;
use App\Models\Round;
use App\Models\Trick;
use App\Models\RoomPlayer;
use App\Models\Announcement;
use App\Models\Game;
use App\Jobs\ProcessTrickEndJob;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class GameService
{
    /** Somme minimale des annonces ; en dessous, +1 par joueur (règle Callbreak). */
    public const ANNOUNCEMENTS_MIN_TOTAL = 10;

    public const ANNOUNCEMENTS_MAX_PER_PLAYER = 13;

    /**
     * Calculer le gagnant d'un pli basé sur les règles du Callbreak
     * 
     * Règles :
     * - Les piques (S) sont les atouts
     * - La carte la plus forte gagne
     * - Si une carte atout est jouée, elle bat toutes les cartes non-atout
     * - Parmi les atouts, la carte la plus forte gagne
     * - Parmi les cartes de la même couleur (non-atout), la carte la plus forte gagne
     * 
     * Ordre des valeurs : A > K > Q > J > 10 > 9 > 8 > 7 > 6 > 5 > 4 > 3 > 2
     */
    public function calculateTrickWinner($trickId, $roundId = null)
    {
        $details = $this->calculateTrickWinnerDetails($trickId);
        return $details['winner_name'] ?? null;
    }

    /**
     * Retourne les informations complètes du gagnant d'un pli
     * (player_id, user_id, pseudo, carte gagnante)
     */
    public function calculateTrickWinnerDetails($trickId): ?array
    {
        // Récupérer toutes les cartes jouées dans ce pli, dans l'ordre
        // ✅ Utiliser played_at pour l'ordre (moment où la carte a été jouée)
        $playedCards = PlayedCard::where('trick_id', $trickId)
            ->orderBy('played_at', 'asc')
            ->with(['player.user' => function ($query) {
                $query->select('user_id', 'pseudo');
            }])
            ->get();

        if ($playedCards->isEmpty() || $playedCards->count() < 4) {
            Log::error('Cannot calculate trick winner: insufficient cards', [
                'trick_id' => $trickId,
                'cards_count' => $playedCards->count()
            ]);
            return null;
        }

        // Déterminer la couleur demandée (première carte jouée)
        $winnerCardData = $this->determineWinningCard($playedCards);
        if (!$winnerCardData) {
            Log::warning('Unable to determine winning card', ['trick_id' => $trickId]);
            return null;
        }

        $winnerCard = $winnerCardData['card'];
        $winnerPlayer = $winnerCard->player;
        $winnerUser = $winnerPlayer ? $winnerPlayer->user : null;

        if (!$winnerPlayer || !$winnerUser) {
            Log::warning('Winner player or user missing for trick', ['trick_id' => $trickId]);
            return null;
        }

        $winnerName = $winnerUser->pseudo ?? ($winnerUser->first_name ?? 'Joueur');

        return [
            'winner_name' => $winnerName,
            'winner_player_id' => $winnerPlayer->player_id,
            'winner_user_id' => $winnerUser->user_id,
            'winner_card_code' => $winnerCard->card_code,
        ];
    }

    /**
     * Obtenir la valeur numérique d'une carte pour comparaison
     * Ordre : A=14, K=13, Q=12, J=11, 10=10, 9=9, ..., 2=2
     * 
     * ⚠️ Cette méthode a été déplacée plus bas dans le fichier (ligne ~751)
     * pour utiliser extractValueFromCardCode() de manière cohérente
     */

    /**
     * Trouver la carte la plus forte parmi une liste de cartes
     */
    private function findHighestCard($cards)
    {
        $highest = $cards[0];
        
        foreach ($cards as $card) {
            if ($card['value'] > $highest['value']) {
                $highest = $card;
            } elseif ($card['value'] === $highest['value']) {
                // En cas d'égalité, celui qui a joué en premier gagne (ordre plus petit)
                if ($card['order'] < $highest['order']) {
                    $highest = $card;
                }
            }
        }

        return $highest;
    }

    /**
     * Trouver la carte gagnante parmi les cartes jouées
     */
    private function determineWinningCard($playedCards): ?array
    {
        $firstCard = $playedCards->first();
        $requestedSuit = substr($firstCard->card_code, -1);

        $trumpCards = [];
        $nonTrumpCards = [];

        foreach ($playedCards as $playedCard) {
            $cardCode = $playedCard->card_code;
            $suit = substr($cardCode, -1);
            $order = $playedCard->played_at ? strtotime($playedCard->played_at) : 0;

            $cardData = [
                'card' => $playedCard,
                'value' => $this->getCardValue($cardCode),
                'order' => $order,
            ];

            if ($suit === 'S') {
                $trumpCards[] = $cardData;
            } elseif ($suit === $requestedSuit) {
                $nonTrumpCards[] = $cardData;
            }
        }

        if (!empty($trumpCards)) {
            return $this->findHighestCard($trumpCards);
        }

        if (!empty($nonTrumpCards)) {
            return $this->findHighestCard($nonTrumpCards);
        }

        return null;
    }

    /**
     * Mettre à jour le gagnant du pli dans la base de données
     */
    public function updateTrickWinner($trickId, $winnerName, $roomId, $roundNumber, $trickNumber)
    {
        try {
            // Récupérer ou créer le round
            $room = Room::where('room_id', $roomId)->first();
            if (!$room) {
                Log::error('Room not found for trick winner update', ['room_id' => $roomId]);
                return false;
            }

            $game = \App\Models\Game::firstOrCreate(
                ['room_id' => $room->room_id],
                ['deck_id' => 0]
            );

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

            // Utiliser une transaction pour éviter les race conditions
            DB::transaction(function () use ($round, $winnerName, &$obtainedTricks) {
                // Verrouiller la ligne pour éviter les conflits
                $round = Round::lockForUpdate()->find($round->getKey());
                
                // Récupérer les compteurs actuels
                $obtainedTricks = $round->obtained_tricks ?? [];
                
                // Incrémenter le compteur du gagnant de manière atomique
                if (!isset($obtainedTricks[$winnerName])) {
                    $obtainedTricks[$winnerName] = 0;
                }
                $obtainedTricks[$winnerName]++;

                // Mettre à jour le round
                $round->obtained_tricks = $obtainedTricks;
                $round->save();
            });

            Log::info('Trick winner updated', [
                'room_id' => $roomId,
                'round_number' => $roundNumber,
                'winner' => $winnerName,
                'trick_number' => $trickNumber,
                'obtained_tricks' => $obtainedTricks ?? [],
            ]);

            return $obtainedTricks ?? [];

        } catch (\Exception $e) {
            Log::error('Error updating trick winner', [
                'trick_id' => $trickId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return false;
        }
    }

    /**
     * Reconstruit le résultat d'un pli déjà marqué completed (évite double comptage).
     */
    protected function buildCompletedTrickResult(
        Trick $trick,
        $roundId,
        $roundNumber,
        $trickNumber
    ): ?array {
        $round = Round::find($roundId);
        if (!$round) {
            return null;
        }

        $winnerUser = $trick->winnerPlayer?->user;
        $winnerName = $winnerUser?->pseudo ?? ($winnerUser?->first_name ?? 'Joueur');

        $playedCards = PlayedCard::where('trick_id', $trick->trick_id)
            ->orderBy('played_at', 'asc')
            ->with(['player.user' => function ($query) {
                $query->select('user_id', 'pseudo', 'first_name');
            }])
            ->get();

        $trickCards = $playedCards->map(function ($card) {
            $user = $card->player?->user;
            $playerName = $user?->pseudo ?? $user?->first_name ?? 'Joueur';

            return [
                'player_name' => $playerName,
                'player_id' => $card->player_id,
                'card_code' => $card->card_code,
                'card_value' => $card->card_value,
                'card_suit' => $card->card_suit,
            ];
        })->values()->toArray();

        return [
            'winner_player_id' => $trick->winner_player_id,
            'winner_name' => $winnerName,
            'obtained_tricks' => $round->obtained_tricks ?? [],
            'trick_cards' => $trickCards,
            'already_completed' => true,
        ];
    }

    /**
     * Traiter complètement la fin d'un pli :
     * - Met à jour les compteurs de plis obtenus (rounds.obtained_tricks)
     * - Met à jour la table tricks avec winner_player_id et status
     * - Retourne un tableau avec :
     *     - winner_player_id (room_players.player_id) pour enchaîner le pli suivant
     *     - obtained_tricks (rounds.obtained_tricks) pour le broadcast temps réel
     *
     * Cette méthode sert de façade "métier" pour le Job ProcessTrickEndJob.
     */
    public function processTrickWinner($trickId, $roundId, $roomId, $roundNumber, $trickNumber): ?array
    {
        try {
            $existingTrick = Trick::with('winnerPlayer.user')->find($trickId);
            if ($existingTrick
                && $existingTrick->status === 'completed'
                && $existingTrick->winner_player_id) {
                return $this->buildCompletedTrickResult(
                    $existingTrick,
                    $roundId,
                    $roundNumber,
                    $trickNumber
                );
            }

            $winnerDetails = $this->calculateTrickWinnerDetails($trickId);
            if (!$winnerDetails || empty($winnerDetails['winner_player_id'])) {
                Log::warning('processTrickWinner: unable to resolve winner details', [
                    'trick_id' => $trickId,
                ]);
                return null;
            }

            $round = Round::find($roundId);
            if (!$round) {
                Log::warning('processTrickWinner: round not found', [
                    'round_id' => $roundId,
                ]);
                return null;
            }

            $obtainedTricks = [];
            DB::transaction(function () use ($round, $winnerDetails, &$obtainedTricks) {
                /** @var Round|null $lockedRound */
                $lockedRound = Round::lockForUpdate()->find($round->getKey());
                if (!$lockedRound) {
                    throw new \RuntimeException('Unable to lock round for trick processing');
                }

                $obtainedTricks = $lockedRound->obtained_tricks ?? [];
                $winnerName = $winnerDetails['winner_name'] ?? 'Joueur';
                $obtainedTricks[$winnerName] = ($obtainedTricks[$winnerName] ?? 0) + 1;
                $lockedRound->obtained_tricks = $obtainedTricks;
                $lockedRound->save();
                
                // ✅ OPTIMISATION: Invalider le cache des scores car les plis obtenus ont changé
                Cache::forget("round_scores_{$round->getKey()}");
            });

            $trickCards = [];
            try {
                $playedCards = PlayedCard::where('trick_id', $trickId)
                    ->orderBy('played_at', 'asc')
                    ->with(['player.user' => function ($query) {
                        $query->select('user_id', 'pseudo', 'first_name');
                    }])
                    ->get();

                $cardsPlayedJson = $playedCards->map(function ($card) {
                    return [
                        'player_id' => $card->player_id,
                        'card_code' => $card->card_code,
                        'card_value' => $card->card_value,
                        'card_suit' => $card->card_suit,
                        'played_at' => $card->played_at,
                    ];
                })->toArray();

                $trickCards = $playedCards->map(function ($card) {
                    $user = $card->player?->user;
                    $playerName = $user?->pseudo ?? $user?->first_name ?? 'Joueur';

                    return [
                        'player_name' => $playerName,
                        'player_id' => $card->player_id,
                        'card_code' => $card->card_code,
                        'card_value' => $card->card_value,
                        'card_suit' => $card->card_suit,
                    ];
                })->values()->toArray();

                Trick::where('trick_id', $trickId)->update([
                    'winner_player_id' => $winnerDetails['winner_player_id'],
                    'cards_played' => json_encode($cardsPlayedJson),
                    'status' => 'completed',
                    'finished_at' => now(),
                ]);
            } catch (\Exception $e) {
                Log::warning('processTrickWinner: could not update tricks table', [
                    'error' => $e->getMessage(),
                ]);
            }

            return [
                'winner_player_id' => $winnerDetails['winner_player_id'],
                'winner_name' => $winnerDetails['winner_name'] ?? 'Joueur',
                'obtained_tricks' => $obtainedTricks,
                'trick_cards' => $trickCards,
            ];
        } catch (\Exception $e) {
            Log::error('Error in processTrickWinner', [
                'trick_id' => $trickId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return null;
        }
    }

    /**
     * Calculer les scores d'un round basé sur les annonces et les plis obtenus
     * 
     * Règles de calcul :
     * 1. Si obtenu == annoncé : Score = annoncé × 10
     * 2. Si obtenu < annoncé : Score = -(annoncé × 10)
     * 3. Si obtenu > annoncé et obtenu ≤ annoncé + 2 : Score = (annoncé × 10) + (obtenu - annoncé)
     * 4. Si obtenu ≥ annoncé + 3 : Score = -(annoncé × 10)
     * 
     * @param int $roundId ID du round
     * @return array|null Tableau avec les scores par joueur, ou null si erreur
     */
    public function calculateRoundScores($roundId): ?array
    {
        try {
            // ✅ OPTIMISATION: Utiliser le cache pour éviter les recalculs inutiles
            $cacheKey = "round_scores_{$roundId}";
            $cached = Cache::get($cacheKey);
            
            // Récupérer le round pour vérifier si les données ont changé
            $round = Round::find($roundId);
            if (!$round) {
                Log::warning('Round not found for score calculation', ['round_id' => $roundId]);
                return null;
            }

            // Créer une clé de version basée sur les données actuelles
            $dataHash = md5(json_encode([
                'announcements' => $round->announcements ?? [],
                'obtained_tricks' => $round->obtained_tricks ?? [],
                'updated_at' => $round->updated_at?->timestamp ?? 0,
            ]));

            // Si le cache existe et que les données n'ont pas changé, retourner le cache
            if ($cached && isset($cached['data_hash']) && $cached['data_hash'] === $dataHash) {
                unset($cached['data_hash']); // Retirer la clé interne avant de retourner
                return $cached;
            }

            $announcements = $round->announcements ?? [];
            $obtainedTricks = $round->obtained_tricks ?? [];

            if (empty($announcements)) {
                Log::warning('No announcements found for round', ['round_id' => $roundId]);
                return null;
            }

            $scores = [];

            // Calculer le score pour chaque joueur
            foreach ($announcements as $playerName => $announced) {
                $obtained = $obtainedTricks[$playerName] ?? 0;
                $announced = (int) $announced;
                $obtained = (int) $obtained;

                // Appliquer les règles de calcul
                if ($obtained == $announced) {
                    // Règle 1: Plis obtenus = Plis annoncés
                    $scores[$playerName] = $announced * 10;
                } elseif ($obtained < $announced) {
                    // Règle 2: Plis obtenus < Plis annoncés
                    $scores[$playerName] = -($announced * 10);
                } elseif ($obtained > $announced && $obtained <= $announced + 2) {
                    // Règle 3: Plis obtenus > Plis annoncés (1 ou 2 plis en plus)
                    $surplus = $obtained - $announced;
                    $scores[$playerName] = ($announced * 10) + $surplus;
                } elseif ($obtained >= $announced + 3) {
                    // Règle 4: Plis obtenus ≥ Plis annoncés + 3
                    $scores[$playerName] = -($announced * 10);
                } else {
                    // Cas par défaut (ne devrait pas arriver)
                    $scores[$playerName] = 0;
                }
            }

            $result = [
                'round_id' => $roundId,
                'round_number' => $round->round_number,
                'announcements' => $announcements,
                'obtained_tricks' => $obtainedTricks,
                'scores' => $scores,
                'data_hash' => $dataHash, // Pour la vérification du cache
            ];

            // ✅ Mettre en cache pendant 5 minutes (les scores changent seulement quand un pli est gagné)
            Cache::put($cacheKey, $result, now()->addMinutes(5));

            // Retirer la clé interne avant de retourner
            unset($result['data_hash']);
            return $result;

        } catch (\Exception $e) {
            Log::error('Error calculating round scores', [
                'round_id' => $roundId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return null;
        }
    }

    /**
     * ✅ NOUVEAU: Obtenir les cartes jouables pour un joueur dans un pli donné
     * 
     * Règles :
     * 1. Si c'est le premier joueur du pli, toutes les cartes sont jouables
     * 2. Si le joueur a la couleur demandée, il DOIT jouer cette couleur
     * 3. Si le joueur n'a pas la couleur demandée mais a des atouts (piques), il DOIT jouer un atout
     * 4. Si le joueur n'a ni la couleur ni d'atout, il peut jouer n'importe quelle carte
     * 5. "Surmontage forcé" : si on peut battre la meilleure carte, on DOIT jouer une carte qui bat
     * 
     * @param int $roundId ID du round
     * @param int $trickId ID du pli actuel
     * @param int $playerId ID du joueur (room_players.player_id)
     * @return array|null Liste des codes de cartes jouables, ou null si erreur
     */
    public function getPlayableCards($roundId, $trickId, $playerId): ?array
    {
        try {
            // 1. Récupérer le round pour obtenir les cartes distribuées
            $round = Round::find($roundId);
            if (!$round) {
                Log::error('Round not found for getPlayableCards', ['round_id' => $roundId]);
                return null;
            }

            // 2. Récupérer le joueur pour obtenir son nom
            $player = RoomPlayer::with('user')->find($playerId);
            if (!$player || !$player->user) {
                Log::error('Player not found for getPlayableCards', ['player_id' => $playerId]);
                return null;
            }
            $playerName = $player->user->pseudo;

            // 3. Récupérer les cartes distribuées au joueur
            $distributedCards = $round->distributed_cards ?? [];
            
            // ----------------------------------------------------
            // ✅ LOGS DE DÉBOGAGE CRITIQUES
            Log::info('DEBUG DISTRIB CARDS CHECK', [
                'round_id_received' => $roundId, // Ex: 261
                'player_name_key_used' => $playerName, // Ex: "Alpha"
                'distributed_cards_type' => gettype($distributedCards), // Devrait être 'array'
                'available_keys' => is_array($distributedCards) ? array_keys($distributedCards) : 'Not Array', // Ex: ["alpha", "elias", "bot1", "bot2"]
            ]);
            // ----------------------------------------------------
            
            // ✅ DEBUG CRITIQUE: Log complet pour diagnostiquer le problème de cartes distribuées
            Log::info('DEBUG DISTRIB CARDS', [
                'round_id' => $roundId,
                'player_id' => $playerId,
                'player_name' => $playerName,
                'distributed_cards_round' => $distributedCards, // Voir toute la colonne (après cast)
                'distributed_cards_type' => gettype($distributedCards),
                'distributed_cards_is_array' => is_array($distributedCards),
                'distributed_cards_keys' => is_array($distributedCards) ? array_keys($distributedCards) : 'not_array',
                'distributed_cards_count' => is_array($distributedCards) ? count($distributedCards) : 0,
                'round_raw_distributed_cards' => method_exists($round, 'getRawOriginal') 
                    ? $round->getRawOriginal('distributed_cards') 
                    : $round->getOriginal('distributed_cards'), // Voir la valeur brute de la BDD (avant cast)
                'player_cards_found' => isset($distributedCards[$playerName]),
                'player_cards_value' => $distributedCards[$playerName] ?? 'NOT_FOUND',
            ]);
            
            $playerCards = $distributedCards[$playerName] ?? [];
            
            // ✅ AMÉLIORATION: Essayer aussi avec d'autres variantes du nom si la clé exacte ne fonctionne pas
            if (empty($playerCards) && is_array($distributedCards)) {
                // Essayer avec différentes variantes (minuscules, majuscules, etc.)
                foreach ($distributedCards as $key => $cards) {
                    if (strtolower($key) === strtolower($playerName)) {
                        $playerCards = $cards;
                        Log::info('Found cards with case-insensitive match', [
                            'requested_name' => $playerName,
                            'found_key' => $key,
                        ]);
                        break;
                    }
                }
            }
            
            if (empty($playerCards)) {
                Log::warning('No distributed cards found for player', [
                    'player_id' => $playerId,
                    'player_name' => $playerName,
                    'round_id' => $roundId,
                    'available_keys' => is_array($distributedCards) ? array_keys($distributedCards) : 'not_array',
                    'distributed_cards_sample' => is_array($distributedCards) ? array_slice($distributedCards, 0, 1, true) : 'not_array',
                ]);
                return [];
            }

            // 4. Récupérer les cartes déjà jouées dans ce pli
            // ✅ Utiliser played_at pour l'ordre (moment où la carte a été jouée)
            $playedCardsInTrick = PlayedCard::where('trick_id', $trickId)
                ->orderBy('played_at', 'asc')
                ->get();

            // 5. Récupérer les cartes déjà jouées par ce joueur dans ce round (pour calculer les cartes restantes)
            $playedCardsInRound = PlayedCard::whereHas('trick', function ($query) use ($roundId) {
                $query->where('round_id', $roundId);
            })
            ->where('player_id', $playerId)
            ->pluck('card_code')
            ->toArray();

            // 6. Calculer les cartes restantes du joueur
            $remainingCards = array_diff($playerCards, $playedCardsInRound);
            if (empty($remainingCards)) {
                return [];
            }

            // 7. Si c'est le premier joueur du pli, toutes les cartes restantes sont jouables
            if ($playedCardsInTrick->isEmpty()) {
                return array_values($remainingCards);
            }

            // 8. Déterminer la couleur demandée (première carte du pli)
            $firstCard = $playedCardsInTrick->first();
            $leadingSuit = $this->extractSuitFromCardCode($firstCard->card_code);

            // 9. Vérifier si le joueur a la couleur demandée
            $hasLeadingSuit = $this->hasSuit($remainingCards, $leadingSuit);
            if ($hasLeadingSuit) {
                // Le joueur DOIT jouer la couleur demandée
                $playableCards = array_filter($remainingCards, function ($card) use ($leadingSuit) {
                    return $this->extractSuitFromCardCode($card) === $leadingSuit;
                });
            } elseif ($leadingSuit !== 'S' && $this->hasSpades($remainingCards)) {
                // Le joueur n'a pas la couleur demandée mais a des atouts (piques)
                // Il DOIT jouer un atout
                $playableCards = array_filter($remainingCards, function ($card) {
                    return $this->extractSuitFromCardCode($card) === 'S';
                });
            } else {
                // Le joueur n'a ni la couleur ni d'atout, il peut jouer n'importe quelle carte
                $playableCards = $remainingCards;
            }

            // 10. "Surmontage forcé" : si on peut battre la meilleure carte, on DOIT jouer une carte qui bat
            // ⚠️ CORRECTION: Ne pas appliquer cette règle si le joueur n'a qu'une seule carte jouable
            // Car dans ce cas, il n'a pas le choix et doit jouer cette carte même si elle ne bat pas
            if (!empty($playableCards) && $playedCardsInTrick->isNotEmpty() && count($playableCards) > 1) {
                $bestCard = $this->getCurrentBestCard($playedCardsInTrick, $leadingSuit);
                if ($bestCard) {
                    $winningCards = array_filter($playableCards, function ($card) use ($bestCard, $leadingSuit) {
                        return $this->doesCardBeat($card, $bestCard, $leadingSuit);
                    });
                    if (!empty($winningCards)) {
                        // Le joueur DOIT jouer une carte qui bat (seulement s'il a plusieurs options)
                        Log::info('Surmontage forcé appliqué', [
                            'player_id' => $playerId,
                            'playable_cards' => $playableCards,
                            'winning_cards' => array_values($winningCards),
                            'best_card' => $bestCard,
                        ]);
                        return array_values($winningCards);
                    }
                }
            }

            // Si le joueur n'a qu'une seule carte jouable, la retourner même si elle ne bat pas
            Log::info('Cartes jouables calculées', [
                'player_id' => $playerId,
                'trick_id' => $trickId,
                'playable_cards' => $playableCards,
                'count' => count($playableCards),
                'leading_suit' => $leadingSuit,
            ]);

            return array_values($playableCards);

        } catch (\Exception $e) {
            Log::error('Error in getPlayableCards', [
                'round_id' => $roundId,
                'trick_id' => $trickId,
                'player_id' => $playerId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return null;
        }
    }

    /**
     * ✅ NOUVEAU: Obtenir le joueur actuel qui doit jouer
     * 
     * @param int $roundId ID du round
     * @param int $trickId ID du pli actuel
     * @return array|null Informations sur le joueur actuel, ou null si erreur
     */
    public function getCurrentTurn($roundId, $trickId): ?array
    {
        try {
            $trick = Trick::with(['round', 'leadPlayer.user'])->find($trickId);
            if (!$trick) {
                Log::error('Trick not found for getCurrentTurn', ['trick_id' => $trickId]);
                return null;
            }

            // Récupérer les cartes déjà jouées dans ce pli
            // ✅ Utiliser played_at pour l'ordre (moment où la carte a été jouée)
            $playedCards = PlayedCard::where('trick_id', $trickId)
                ->orderBy('played_at', 'asc')
                ->with('player.user')
                ->get();

            $cardsPlayed = $playedCards->count();
            $playerCount = 4; // Par défaut 4 joueurs

            // Si le pli est complet (4 cartes), retourner null
            if ($cardsPlayed >= $playerCount) {
                return null;
            }

            // Si c'est le premier joueur, c'est le lead_player
            if ($cardsPlayed === 0) {
                $currentPlayer = $trick->leadPlayer;
                if (!$currentPlayer || !$currentPlayer->user) {
                    return null;
                }
                return [
                    'player_id' => $currentPlayer->player_id,
                    'player_name' => $currentPlayer->user->pseudo,
                    'position' => $currentPlayer->position,
                ];
            }

            // Sinon, déterminer le prochain joueur dans l'ordre
            $lastPlayer = $playedCards->last()->player;
            $roomId = $trick->round->room_id;

            // Récupérer tous les joueurs dans l'ordre de position
            $allPlayers = RoomPlayer::where('room_id', $roomId)
                ->orderBy('position', 'asc')
                ->with('user')
                ->get();

            if ($allPlayers->isEmpty()) {
                return null;
            }

            // Trouver l'index du dernier joueur
            $lastPlayerIndex = $allPlayers->search(function ($p) use ($lastPlayer) {
                return $p->player_id === $lastPlayer->player_id;
            });

            if ($lastPlayerIndex === false) {
                return null;
            }

            // Prochain joueur (circulaire)
            $nextPlayerIndex = ($lastPlayerIndex + 1) % $allPlayers->count();
            $nextPlayer = $allPlayers[$nextPlayerIndex];

            if (!$nextPlayer->user) {
                return null;
            }

            return [
                'player_id' => $nextPlayer->player_id,
                'player_name' => $nextPlayer->user->pseudo,
                'position' => $nextPlayer->position,
            ];

        } catch (\Exception $e) {
            Log::error('Error in getCurrentTurn', [
                'round_id' => $roundId,
                'trick_id' => $trickId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return null;
        }
    }

    /**
     * Joueur qui ouvre le premier pli d'une manche (même règle partout : créateur + rotation).
     */
    public function resolveRoundLeadPlayer(int $roomId, int $roundNumber): ?RoomPlayer
    {
        $players = RoomPlayer::where('room_id', $roomId)
            ->orderBy('position', 'asc')
            ->with('user')
            ->get();

        if ($players->isEmpty()) {
            return null;
        }

        $creatorIndex = $players->search(function ($player) {
            return (bool) $player->is_creator === true;
        });

        if ($creatorIndex === false) {
            return $players->first();
        }

        $playerCount = $players->count();
        $offset = ($roundNumber - 1) % $playerCount;
        $leaderIndex = ($creatorIndex + $offset) % $playerCount;

        return $players[$leaderIndex];
    }

    /**
     * Progression de la phase d'annonces simultanées (source de vérité = BDD, joueurs distincts).
     *
     * @return array{player_count: int, announced_count: int, missing_player_ids: array<int>, is_complete: bool}
     */
    public function getAnnouncementPhaseProgress(int $gameId, int $roundNumber, int $roomId): array
    {
        $roomPlayerIds = RoomPlayer::where('room_id', $roomId)
            ->pluck('player_id')
            ->unique()
            ->values();

        $announcedPlayerIds = Announcement::where('game_id', $gameId)
            ->where('round_number', $roundNumber)
            ->pluck('player_id')
            ->unique()
            ->values();

        $missing = $roomPlayerIds->diff($announcedPlayerIds);

        return [
            'player_count' => $roomPlayerIds->count(),
            'announced_count' => $announcedPlayerIds->count(),
            'missing_player_ids' => $missing->values()->all(),
            'is_complete' => $roomPlayerIds->isNotEmpty() && $missing->isEmpty(),
        ];
    }

    /**
     * Si la somme des annonces est inférieure à 10, ajoute +1 à chaque joueur en BDD (plafond 13).
     *
     * @return array{adjusted: bool, previous_total: int, new_total: int, announcements: array<string, int>}
     */
    public function applyLowTotalAnnouncementAdjustment(int $gameId, int $roundNumber): array
    {
        $announcements = Announcement::where('game_id', $gameId)
            ->where('round_number', $roundNumber)
            ->with('player.user')
            ->orderByDesc('id')
            ->get()
            ->unique('player_id')
            ->values();

        $map = [];
        $previousTotal = 0;

        foreach ($announcements as $ann) {
            $playerName = $ann->player->user->pseudo ?? 'Joueur';
            $value = (int) $ann->announcement_value;
            $map[$playerName] = $value;
            $previousTotal += $value;
        }

        if ($announcements->isEmpty()) {
            return [
                'adjusted' => false,
                'previous_total' => 0,
                'new_total' => 0,
                'announcements' => $map,
            ];
        }

        // Interdit : total exactement 13 — réduire le plus gros annonceur de 1
        if ($previousTotal === 13) {
            $target = $announcements->sortByDesc('announcement_value')->first();
            if ($target && (int) $target->announcement_value > 0) {
                $target->announcement_value = (int) $target->announcement_value - 1;
                $target->save();
                $target->refresh();
                $targetName = $target->player->user->pseudo ?? 'Joueur';
                $map[$targetName] = (int) $target->announcement_value;
                $newTotal = array_sum($map);

                Log::info('Announcements adjusted (-1 highest): total was exactly 13', [
                    'game_id' => $gameId,
                    'round_number' => $roundNumber,
                    'player_adjusted' => $targetName,
                    'new_total' => $newTotal,
                ]);

                return [
                    'adjusted' => true,
                    'previous_total' => $previousTotal,
                    'new_total' => $newTotal,
                    'announcements' => $map,
                ];
            }
        }

        if ($previousTotal >= self::ANNOUNCEMENTS_MIN_TOTAL) {
            return [
                'adjusted' => false,
                'previous_total' => $previousTotal,
                'new_total' => $previousTotal,
                'announcements' => $map,
            ];
        }

        foreach ($announcements as $ann) {
            $ann->announcement_value = min(
                (int) $ann->announcement_value + 1,
                self::ANNOUNCEMENTS_MAX_PER_PLAYER
            );
            $ann->save();
        }

        $map = [];
        $newTotal = 0;

        foreach ($announcements as $ann) {
            $ann->refresh();
            $playerName = $ann->player->user->pseudo ?? 'Joueur';
            $value = (int) $ann->announcement_value;
            $map[$playerName] = $value;
            $newTotal += $value;
        }

        // Interdit en Callbreak : total exactement 13 plis annoncés
        if ($newTotal === 13) {
            $lastAnn = $announcements->last();
            if ($lastAnn && (int) $lastAnn->announcement_value > 2) {
                $lastAnn->announcement_value = (int) $lastAnn->announcement_value - 1;
                $lastAnn->save();
                $lastAnn->refresh();
                $lastName = $lastAnn->player->user->pseudo ?? 'Joueur';
                $map[$lastName] = (int) $lastAnn->announcement_value;
                $newTotal = array_sum($map);
            }
        }

        Log::info('Announcements adjusted (+1 each): total below minimum', [
            'game_id' => $gameId,
            'round_number' => $roundNumber,
            'previous_total' => $previousTotal,
            'new_total' => $newTotal,
            'announcements' => $map,
        ]);

        return [
            'adjusted' => true,
            'previous_total' => $previousTotal,
            'new_total' => $newTotal,
            'announcements' => $map,
        ];
    }

    /**
     * ✅ NOUVEAU: Valider les annonces d'un round
     * 
     * Règles :
     * - Chaque joueur doit annoncer entre 0 et 13 plis
     * - La somme des annonces ne peut pas être égale au nombre total de plis (13)
     * - Si la somme est inférieure à 10, l'ajustement (+1/joueur) est appliqué à la fin de phase
     *   via applyLowTotalAnnouncementAdjustment(), pas ici.
     * 
     * @param int $roundId ID du round
     * @param array $announcements Tableau associatif [player_name => announcement]
     * @return array|null Résultat de validation avec 'valid' (bool) et 'message' (string)
     */
    public function validateAnnouncements($roundId, array $announcements): ?array
    {
        try {
            $round = Round::find($roundId);
            if (!$round) {
                return [
                    'valid' => false,
                    'message' => 'Round not found',
                ];
            }

            $totalTricks = 13;
            $totalAnnounced = 0;
            $errors = [];

            // Vérifier chaque annonce
            foreach ($announcements as $playerName => $announcement) {
                $announcement = (int) $announcement;
                
                if ($announcement < 0 || $announcement > $totalTricks) {
                    $errors[] = "L'annonce de $playerName doit être entre 0 et $totalTricks";
                }
                
                $totalAnnounced += $announcement;
            }

            // Vérifier que la somme n'est pas égale au total de plis
            if ($totalAnnounced === $totalTricks) {
                $errors[] = "La somme des annonces ne peut pas être égale au nombre total de plis ($totalTricks)";
            }

            if (!empty($errors)) {
                return [
                    'valid' => false,
                    'message' => implode('. ', $errors),
                    'errors' => $errors,
                ];
            }

            return [
                'valid' => true,
                'message' => 'Annonces valides',
            ];

        } catch (\Exception $e) {
            Log::error('Error in validateAnnouncements', [
                'round_id' => $roundId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return [
                'valid' => false,
                'message' => 'Erreur lors de la validation: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * ✅ NOUVEAU: Obtenir le tour d'annonces actuel pour un round
     * 
     * @param int $gameId ID de la partie
     * @param int $roundNumber Numéro du round
     * @return array|null Informations sur le tour d'annonces (joueur actuel, annonces faites, etc.)
     */
    public function getAnnouncementTurn($gameId, $roundNumber): ?array
    {
        try {
            $game = Game::find($gameId);
            if (!$game) {
                Log::error('Game not found for getAnnouncementTurn', ['game_id' => $gameId]);
                return null;
            }

            // Récupérer les joueurs de la partie dans l'ordre
            $players = RoomPlayer::where('room_id', $game->room_id)
                ->orderBy('position', 'asc')
                ->with('user')
                ->get();

            if ($players->isEmpty()) {
                Log::error('No players found for getAnnouncementTurn', ['game_id' => $gameId]);
                return null;
            }

            // Récupérer les annonces déjà faites pour ce round
            $announcements = Announcement::where('game_id', $gameId)
                ->where('round_number', $roundNumber)
                ->with('player.user')
                ->get();

            $announcedPlayerIds = $announcements->pluck('player_id')->toArray();
            $announcementsMap = [];
            foreach ($announcements as $ann) {
                $announcementsMap[$ann->player->user->pseudo] = $ann->announcement_value;
            }

            // Trouver le premier joueur qui n'a pas encore annoncé
            $currentPlayer = null;
            $currentPlayerName = null;
            foreach ($players as $player) {
                if (!in_array($player->player_id, $announcedPlayerIds)) {
                    $currentPlayer = $player;
                    $currentPlayerName = $player->user->pseudo ?? 'Joueur';
                    break;
                }
            }

            // Si tous les joueurs ont annoncé
            $allAnnounced = count($announcedPlayerIds) >= $players->count();

            return [
                'current_player_name' => $currentPlayerName,
                'current_player_id' => $currentPlayer ? $currentPlayer->player_id : null,
                'all_announced' => $allAnnounced,
                'announcements' => $announcementsMap,
                'announcements_count' => count($announcements),
                'players_count' => $players->count(),
            ];

        } catch (\Exception $e) {
            Log::error('Error in getAnnouncementTurn', [
                'game_id' => $gameId,
                'round_number' => $roundNumber,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return null;
        }
    }

    // ========== MÉTHODES UTILITAIRES PRIVÉES ==========

    /**
     * Extraire la couleur d'un code de carte (ex: "AS" -> "S")
     */
    private function extractSuitFromCardCode(string $cardCode): string
    {
        return substr($cardCode, -1); // Dernier caractère
    }

    /**
     * Extraire la valeur d'un code de carte (ex: "AS" -> "A")
     */
    private function extractValueFromCardCode(string $cardCode): string
    {
        return substr($cardCode, 0, -1); // Tout sauf le dernier caractère
    }

    /**
     * Vérifier si le joueur a une couleur donnée dans ses cartes
     */
    private function hasSuit(array $cards, string $suit): bool
    {
        foreach ($cards as $card) {
            if ($this->extractSuitFromCardCode($card) === $suit) {
                return true;
            }
        }
        return false;
    }

    /**
     * Vérifier si le joueur a des atouts (piques) dans ses cartes
     */
    private function hasSpades(array $cards): bool
    {
        return $this->hasSuit($cards, 'S');
    }

    /**
     * Obtenir la valeur numérique d'une carte (A=14, K=13, Q=12, J=11, 10=10, 2-9=2-9)
     */
    private function getCardValue(string $cardCode): int
    {
        $value = $this->extractValueFromCardCode($cardCode);
        $values = [
            'A' => 14, 'K' => 13, 'Q' => 12, 'J' => 11, '0' => 10,
            '9' => 9, '8' => 8, '7' => 7, '6' => 6, '5' => 5,
            '4' => 4, '3' => 3, '2' => 2,
        ];
        return $values[$value] ?? 0;
    }

    /**
     * Obtenir la meilleure carte actuelle dans le pli
     */
    private function getCurrentBestCard($playedCards, string $leadingSuit): ?string
    {
        $bestCard = null;
        $bestValue = 0;
        $bestIsSpade = false;

        foreach ($playedCards as $playedCard) {
            $cardCode = $playedCard->card_code;
            $suit = $this->extractSuitFromCardCode($cardCode);
            $value = $this->getCardValue($cardCode);
            $isSpade = ($suit === 'S');

            // Les atouts (piques) battent toujours les non-atouts
            if ($isSpade && !$bestIsSpade) {
                $bestCard = $cardCode;
                $bestValue = $value;
                $bestIsSpade = true;
            } elseif ($isSpade && $bestIsSpade && $value > $bestValue) {
                // Parmi les atouts, la plus forte gagne
                $bestCard = $cardCode;
                $bestValue = $value;
            } elseif (!$isSpade && !$bestIsSpade && $suit === $leadingSuit && $value > $bestValue) {
                // Parmi la couleur demandée, la plus forte gagne
                $bestCard = $cardCode;
                $bestValue = $value;
            }
        }

        return $bestCard;
    }

    /**
     * Vérifier si une carte bat une autre carte
     */
    private function doesCardBeat(string $card1, string $card2, string $leadingSuit): bool
    {
        $suit1 = $this->extractSuitFromCardCode($card1);
        $suit2 = $this->extractSuitFromCardCode($card2);
        $value1 = $this->getCardValue($card1);
        $value2 = $this->getCardValue($card2);

        $isSpade1 = ($suit1 === 'S');
        $isSpade2 = ($suit2 === 'S');

        // Les atouts battent toujours les non-atouts
        if ($isSpade1 && !$isSpade2) {
            return true;
        }
        if (!$isSpade1 && $isSpade2) {
            return false;
        }

        // Si les deux sont des atouts, la plus forte gagne
        if ($isSpade1 && $isSpade2) {
            return $value1 > $value2;
        }

        // Si les deux sont de la couleur demandée, la plus forte gagne
        if ($suit1 === $leadingSuit && $suit2 === $leadingSuit) {
            return $value1 > $value2;
        }

        return false;
    }

    /**
     * Joue automatiquement une carte pour un bot si c'est son tour.
     *
     * @return array{played: bool, reason?: string, next_player_id?: int, next_trick_id?: int, next_trick_number?: int}
     */
    public function autoPlayBotIfTheirTurn(
        int $gameId,
        int $trickId,
        int $roundId,
        $roomId,
        int $roundNumber,
        int $trickNumber,
        ?int $expectedPlayerId,
        WebSocketService $wsService
    ): array {
        try {
            $currentTurn = $this->getCurrentTurn($roundId, $trickId);
            if (!$currentTurn || empty($currentTurn['player_id'])) {
                return ['played' => false, 'reason' => 'no_current_turn'];
            }

            $playerId = (int) $currentTurn['player_id'];
            if ($expectedPlayerId !== null && $playerId !== $expectedPlayerId) {
                return ['played' => false, 'reason' => 'turn_changed'];
            }

            $roomPlayer = RoomPlayer::with('user')->find($playerId);
            if (!$roomPlayer || !$roomPlayer->user || !($roomPlayer->user->is_bot ?? false)) {
                return ['played' => false, 'reason' => 'not_bot_turn'];
            }

            $alreadyPlayed = PlayedCard::where('trick_id', $trickId)
                ->where('player_id', $playerId)
                ->exists();
            if ($alreadyPlayed) {
                return ['played' => false, 'reason' => 'already_played'];
            }

            $playableCards = $this->getPlayableCards($roundId, $trickId, $playerId);
            if (empty($playableCards)) {
                return ['played' => false, 'reason' => 'no_playable_cards'];
            }

            $cardCode = $playableCards[0];
            $playerName = $roomPlayer->user->pseudo ?? 'Joueur';

            $cardSuit = substr($cardCode, -1);
            $cardValue = substr($cardCode, 0, -1);
            $cardValueForDB = ($cardValue === '0') ? '10' : $cardValue;
            $suitMapping = ['S' => 'SPADES', 'H' => 'HEARTS', 'D' => 'DIAMONDS', 'C' => 'CLUBS'];
            $cardSuitForDB = $suitMapping[$cardSuit] ?? $cardSuit;

            $cardsInTrick = PlayedCard::where('trick_id', $trickId)->count();
            $cardOrder = $cardsInTrick + 1;

            if ($cardOrder === 1) {
                Trick::where('trick_id', $trickId)->update([
                    'lead_player_id' => $playerId,
                    'status' => 'in_progress',
                ]);
            }

            PlayedCard::create([
                'trick_id' => $trickId,
                'player_id' => $playerId,
                'card_code' => $cardCode,
                'card_value' => $cardValueForDB,
                'card_suit' => $cardSuitForDB,
                'played_at' => now(),
            ]);

            Log::info('Bot auto-play', [
                'game_id' => $gameId,
                'trick_id' => $trickId,
                'player' => $playerName,
                'card' => $cardCode,
            ]);

            $wsService->broadcastToRoom($roomId, [
                'event' => 'card_played',
                'data' => [
                    'roomId' => (string) $roomId,
                    'playerName' => $playerName,
                    'card' => [
                        'suit' => $cardSuit,
                        'value' => $cardValue,
                    ],
                    'trickNumber' => $trickNumber,
                    'timestamp' => now()->toIso8601String(),
                ],
            ]);

            if ($cardOrder === 4) {
                ProcessTrickEndJob::dispatchSync(
                    $gameId,
                    $trickId,
                    $roundId,
                    $roomId,
                    $roundNumber,
                    $trickNumber
                );

                return ['played' => true, 'reason' => 'trick_completed'];
            }

            $nextTurn = $this->getCurrentTurn($roundId, $trickId);
            if ($nextTurn && isset($nextTurn['player_name'])) {
                $wsService->broadcastToRoom($roomId, [
                    'event' => 'turn_changed',
                    'data' => [
                        'roomId' => (string) $roomId,
                        'round_id' => $roundId,
                        'trick_id' => $trickId,
                        'current_player_name' => $nextTurn['player_name'],
                        'current_player_id' => $nextTurn['player_id'] ?? null,
                        'position' => $nextTurn['position'] ?? null,
                    ],
                ]);
            }

            return [
                'played' => true,
                'next_player_id' => $nextTurn['player_id'] ?? null,
                'next_trick_id' => $trickId,
                'next_trick_number' => $trickNumber,
            ];
        } catch (\Exception $e) {
            Log::error('autoPlayBotIfTheirTurn failed', [
                'game_id' => $gameId,
                'trick_id' => $trickId,
                'error' => $e->getMessage(),
            ]);

            return ['played' => false, 'reason' => 'exception'];
        }
    }
}

