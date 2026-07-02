<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Announcement;
use App\Models\Game;
use App\Models\Room;
use App\Models\RoomChatMessage;
use App\Models\RoomPlayer;
use App\Models\Round;
use App\Models\User;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Services\RoomBotService;
use App\Services\WebSocketService;

class RoomController extends Controller
{
    protected $wsService;

    public function __construct(
        WebSocketService $wsService,
        private RoomBotService $roomBotService
    ) {
        $this->wsService = $wsService;
    }

    /**
     * Liste des salles disponibles
     */
    public function index(Request $request)
    {
        try {
            $rooms = Room::where('status', 'waiting')
                ->with(['creator', 'players'])
                ->orderBy('created_at', 'desc')
                ->get();

            return $this->apiResponse(true, 'Salles récupérées', $rooms->map(function($room) {
                return [
                    'room_id' => $room->room_id,
                    'room_name' => $room->room_name,
                    'room_code' => $room->room_code,
                    'minimum_bet' => $room->minimum_bet,
                    'player_count' => $room->players->count(),
                    'max_players' => $room->max_players,
                    'status' => $room->status,
                    'created_at' => $room->created_at,
                ];
            }));

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Créer une nouvelle salle
     * En mode humain, vérifie le solde et débite automatiquement la mise minimale
     */
    public function create(Request $request)
    {
        DB::beginTransaction();
        
        try {
            $validator = \Illuminate\Support\Facades\Validator::make($request->all(), [
                'room_name' => 'nullable|string|max:100',
                'minimum_bet' => 'required|integer|min:10',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Erreur de validation',
                    'errors' => $validator->errors()
                ], 422);
            }

            $user = $request->user();
            $minimumBet = $request->minimum_bet;
            
            // ⚠️ VÉRIFIER LE SOLDE AVANT DE CRÉER (seulement si play_with_bots = false, mais on ne le sait pas encore)
            // On laisse le frontend gérer la vérification initiale, mais on double-vérifie ici
            $user = User::find($user->user_id); // Recharger pour avoir le solde à jour
            if (($user->cauris_balance ?? 0) < $minimumBet) {
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'message' => 'Solde insuffisant. Vous avez ' . ($user->cauris_balance ?? 0) . ' cauris, il vous faut ' . $minimumBet . ' cauris.',
                ], 400);
            }
            
            // Générer un code unique
            $roomCode = strtoupper(Str::random(6));
            
            // Créer la salle (nom temporaire, sera mis à jour avec room_id)
            $room = Room::create([
                'room_name' => 'Room ' . $roomCode, // Temporaire, sera remplacé
                'room_code' => $roomCode,
                'creator_id' => $user->user_id,
                'minimum_bet' => $minimumBet,
                'status' => 'waiting',
            ]);

            // TOUJOURS générer un nom unique basé sur room_id (ignore le nom envoyé par le frontend)
            $uniqueName = 'Room ' . $room->room_id;
            $room->room_name = $uniqueName;
            $room->save();

            // Ajouter le créateur comme premier joueur
            RoomPlayer::create([
                'room_id' => $room->room_id,
                'user_id' => $user->user_id,
                'position' => 1,
                'is_creator' => true,
                'status' => 'ready',
            ]);

            // ⚠️ NOTE: Le débit sera fait par le frontend via /api/payment/debit-room-bet
            // On ne débite pas ici car on ne sait pas encore si c'est mode bot ou humain

            DB::commit();

            $payload = [
                'room_id' => $room->room_id,
                'room_name' => $room->room_name,
                'room_code' => $room->room_code,
                'minimum_bet' => $room->minimum_bet,
                'status' => $room->status,
            ];

            $wantsBots = $request->boolean('play_with_bots')
                || $request->boolean('with_bots')
                || $request->boolean('vs_bots');

            if ($wantsBots) {
                try {
                    $botResult = $this->roomBotService->fillRoom($room->fresh());
                    $payload['players'] = $botResult['players'];
                    $payload['bots_added'] = $botResult['added'];
                } catch (\Exception $e) {
                    Log::warning('Création salle OK mais fill bots échoué: ' . $e->getMessage());
                    $payload['bots_fill_error'] = $e->getMessage();
                }
            }

            return $this->apiResponse(true, 'Salle créée avec succès', $payload, 201, false);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Erreur: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Rejoindre une salle
     * En mode humain, vérifie le solde et débite automatiquement la mise minimale
     */
    public function join(Request $request)
    {
        DB::beginTransaction();
        
        try {
            $validator = \Illuminate\Support\Facades\Validator::make($request->all(), [
                'room_code' => 'required|string|size:6',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Erreur de validation',
                    'errors' => $validator->errors()
                ], 422);
            }

            $user = $request->user();
            
            // Trouver la salle
            $room = Room::where('room_code', $request->room_code)
                ->where('status', 'waiting')
                ->first();

            if (!$room) {
                return response()->json([
                    'success' => false,
                    'message' => 'Salle non trouvée ou déjà pleine'
                ], 404);
            }

            // Vérifier si le joueur est déjà dans la salle
            $existingPlayer = RoomPlayer::where('room_id', $room->room_id)
                ->where('user_id', $user->user_id)
                ->first();

            if ($existingPlayer) {
                return response()->json([
                    'success' => false,
                    'message' => 'Vous êtes déjà dans cette salle'
                ], 400);
            }

            // Compter les joueurs
            $playerCount = RoomPlayer::where('room_id', $room->room_id)->count();

            if ($playerCount >= 4) {
                return response()->json([
                    'success' => false,
                    'message' => 'La salle est pleine'
                ], 400);
            }

            // ⚠️ VÉRIFIER LE SOLDE AVANT DE REJOINDRE (en mode humain uniquement)
            // On laisse le frontend gérer la vérification initiale, mais on double-vérifie ici
            $user = User::find($user->user_id); // Recharger pour avoir le solde à jour
            $minimumBet = $room->minimum_bet;
            if (($user->cauris_balance ?? 0) < $minimumBet) {
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'message' => 'Solde insuffisant. Vous avez ' . ($user->cauris_balance ?? 0) . ' cauris, il vous faut ' . $minimumBet . ' cauris.',
                ], 400);
            }

            // Ajouter le joueur
            $position = $playerCount + 1;
            RoomPlayer::create([
                'room_id' => $room->room_id,
                'user_id' => $user->user_id,
                'position' => $position,
                'is_creator' => false,
                'status' => 'ready',
            ]);

            // ⚠️ NOTE: Le débit sera fait par le frontend via /api/payment/debit-room-bet
            // On ne débite pas ici car on ne sait pas encore si c'est mode bot ou humain

            $updatedPlayerCount = RoomPlayer::where('room_id', $room->room_id)->count();

            // ⚠️ HACK TEMPORAIRE POUR TESTS : Si 2 joueurs ont rejoint, on complète avec 2 bots
            // Cela permet de tester le mode multijoueur avec seulement 1 téléphone et 1 PC
            if ($updatedPlayerCount == 2) {
                app(\App\Services\RoomBotService::class)->fillRoom($room->fresh());
                $updatedPlayerCount = RoomPlayer::where('room_id', $room->room_id)->count(); // Devrait être 4
            }

            // Si 4 joueurs, démarrer automatiquement
            if ($updatedPlayerCount >= 4) {
                $this->startGame($room->room_id);
            }

            DB::commit();

            return $this->apiResponse(true, 'Vous avez rejoint la salle', [
                'room_id' => $room->room_id,
                'room_name' => $room->room_name,
                'room_code' => $room->room_code,
                'minimum_bet' => $room->minimum_bet,
            ], 200, false);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Erreur: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Détails d'une salle
     */
    public function show($roomId)
    {
        try {
            $room = Room::with(['creator', 'players.user'])->find($roomId);

            if (!$room) {
                return response()->json([
                    'success' => false,
                    'message' => 'Salle non trouvée'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => $this->formatRoomData($room),
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * État consolidé pour le polling client (joueurs + manche + compteurs + annonces + chat).
     * GET /api/rooms/{room_id}/sync?last_chat_id=123
     */
    public function sync(Request $request, $roomId)
    {
        try {
            $roomId = (int) $roomId;
            $room = Room::with(['creator', 'players.user'])->find($roomId);

            if (!$room) {
                return response()->json([
                    'success' => false,
                    'message' => 'Salle non trouvée',
                ], 404);
            }

            $user = $request->user();
            $isMember = $user && RoomPlayer::where('room_id', $roomId)
                ->where('user_id', $user->user_id)
                ->exists();

            $payload = $this->formatRoomData($room);
            $payload['game_id'] = null;
            $payload['round'] = null;
            $payload['chat'] = $isMember
                ? $this->formatChatSyncData($roomId, $request->query('last_chat_id'))
                : ['messages' => [], 'last_chat_id' => null];

            $roomIdStr = (string) $roomId;

            // Priorité : manche avec cartes distribuées (évite un game_id plus récent sans round)
            $round = Round::where(function ($q) use ($roomId, $roomIdStr) {
                    $q->where('room_id', $roomId)->orWhere('room_id', $roomIdStr);
                })
                ->orderByDesc('round_number')
                ->get()
                ->first(function (Round $candidate) {
                    $cards = $candidate->distributed_cards ?? [];

                    return is_array($cards) && count($cards) > 0;
                });

            if ($round) {
                $game = Game::find($round->game_id);
                if ($game) {
                    $payload['game_id'] = $game->game_id;
                    $payload['round'] = $this->formatRoundSyncData($room, $round, $game->game_id);
                }
            } else {
                $game = Game::where('room_id', $roomId)
                    ->orWhere('room_id', $roomIdStr)
                    ->orderByDesc('game_id')
                    ->first();

                if ($game) {
                    $payload['game_id'] = $game->game_id;

                    $round = Round::where('game_id', $game->game_id)
                        ->orderByDesc('round_number')
                        ->first();

                    if ($round) {
                        $payload['round'] = $this->formatRoundSyncData($room, $round, $game->game_id);
                    }
                }
            }

            return response()->json([
                'success' => true,
                'data' => $payload,
            ], 200);
        } catch (\Exception $e) {
            Log::error('Room sync failed', [
                'room_id' => $roomId,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Erreur: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function formatRoomData(Room $room): array
    {
        return [
            'room_id' => $room->room_id,
            'room_name' => $room->room_name,
            'room_code' => $room->room_code,
            'minimum_bet' => $room->minimum_bet,
            'status' => $room->status,
            'players' => $room->players->map(function ($player) {
                return [
                    'player_id' => $player->player_id,
                    'user_id' => $player->user_id,
                    'pseudo' => $player->user->pseudo,
                    'first_name' => $player->user->first_name ?? '',
                    'last_name' => $player->user->last_name ?? '',
                    'position' => $player->position,
                    'is_creator' => $player->is_creator,
                    'status' => $player->status,
                    'is_bot' => $player->user->is_bot ?? 0,
                    'avatar' => $player->user->avatar ?? '',
                ];
            })->values()->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function formatRoundSyncData(Room $room, Round $round, int $gameId): array
    {
        $playerNames = $room->players->map(function ($player) {
            return $player->user->pseudo ?? 'Joueur';
        })->filter()->values();

        $storedCounters = $round->obtained_tricks ?? [];
        $obtainedTricks = [];
        foreach ($playerNames as $name) {
            $obtainedTricks[$name] = (int) ($storedCounters[$name] ?? 0);
        }
        foreach ($storedCounters as $name => $value) {
            if (!isset($obtainedTricks[$name])) {
                $obtainedTricks[$name] = (int) $value;
            }
        }

        $announcements = Announcement::where('game_id', $gameId)
            ->where('round_number', $round->round_number)
            ->with('player.user')
            ->get();

        $announcementsMap = [];
        foreach ($announcements as $announcement) {
            $playerName = $announcement->player?->user?->pseudo ?? 'Joueur';
            $announcementsMap[$playerName] = $announcement->announcement_value;
        }

        $secondsRemaining = null;
        if ($round->status === Round::STATUS_ANNOUNCEMENT_PHASE && $round->announcement_end_at) {
            $secondsRemaining = max(
                0,
                $round->announcement_end_at->timestamp - now()->timestamp
            );
        }

        return [
            'round_id' => $round->round_id,
            'round_number' => $round->round_number,
            'status' => $round->status,
            'announcement_end_at' => $round->announcement_end_at?->toIso8601String(),
            'announcement_seconds_remaining' => $secondsRemaining,
            'obtained_tricks' => $obtainedTricks,
            'announcements' => $announcementsMap,
            'announcements_count' => count($announcementsMap),
            'distributed_cards' => $round->distributed_cards ?? [],
        ];
    }

    /**
     * Messages de chat récents ou nouveaux depuis last_chat_id.
     *
     * @return array{messages: array<int, array<string, mixed>>, last_chat_id: int|null}
     */
    private function formatChatSyncData(int $roomId, $lastChatId): array
    {
        $lastChatId = $lastChatId !== null && $lastChatId !== ''
            ? (int) $lastChatId
            : null;

        $query = RoomChatMessage::with('user')->where('room_id', $roomId);

        if ($lastChatId) {
            $messages = $query->where('id', '>', $lastChatId)
                ->orderBy('id', 'asc')
                ->limit(50)
                ->get();
        } else {
            $messages = $query->orderBy('id', 'desc')
                ->limit(30)
                ->get()
                ->sortBy('id')
                ->values();
        }

        $formatted = $messages->map(function ($message) {
            return [
                'id' => $message->id,
                'room_id' => $message->room_id,
                'user_id' => $message->user_id,
                'pseudo' => $message->user->pseudo ?? 'Joueur',
                'message' => $message->message,
                'message_type' => $message->message_type,
                'preset_code' => $message->preset_code,
                'created_at' => $message->created_at?->toISOString(),
            ];
        })->values()->all();

        $maxId = !empty($formatted)
            ? (int) max(array_column($formatted, 'id'))
            : $lastChatId;

        return [
            'messages' => $formatted,
            'last_chat_id' => $maxId,
        ];
    }

    /**
     * Démarrer une partie
     */
    public function start($roomId)
    {
        try {
            $room = Room::find($roomId);

            if (!$room) {
                return response()->json([
                    'success' => false,
                    'message' => 'Salle non trouvée'
                ], 404);
            }

            if ($room->status !== 'waiting') {
                return response()->json([
                    'success' => false,
                    'message' => 'La salle n\'est pas en attente'
                ], 400);
            }

            // Démarrer la partie
            $this->startGame($roomId);

            return response()->json([
                'success' => true,
                'message' => 'Partie démarrée'
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Quitter une salle
     */
    public function leave($roomId, Request $request)
    {
        try {
            $user = $request->user();
            
            $player = RoomPlayer::where('room_id', $roomId)
                ->where('user_id', $user->user_id)
                ->first();

            if (!$player) {
                return response()->json([
                    'success' => false,
                    'message' => 'Vous n\'êtes pas dans cette salle'
                ], 404);
            }

            $player->delete();

            // Si c'est le créateur, fermer la salle
            if ($player->is_creator) {
                Room::where('room_id', $roomId)->update(['status' => 'cancelled']);
            }

            return response()->json([
                'success' => true,
                'message' => 'Vous avez quitté la salle'
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Supprimer une salle
     */
    public function destroy($roomId)
    {
        try {
            $room = Room::find($roomId);

            if (!$room) {
                return response()->json([
                    'success' => false,
                    'message' => 'Salle non trouvée'
                ], 404);
            }

            $room->delete();

            return response()->json([
                'success' => true,
                'message' => 'Salle supprimée'
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Démarrer le jeu
     */
    private function startGame($roomId)
    {
        Room::where('room_id', $roomId)->update([
            'status' => 'playing',
            'started_at' => now()
        ]);

        // TODO: Créer le jeu et distribuer les cartes
    }

    /**
     * POST /api/rooms/replace-player
     * Remplace un joueur par un bot
     */
    public function replacePlayerWithBot(Request $request)
    {
        $request->validate([
            'room_id' => 'required',
            'player_name' => 'required|string',
            'bot_name' => 'required|string',
            'is_permanent' => 'required|boolean',
        ]);

        try {
            DB::beginTransaction();

            // Convertir room_id en integer si c'est une string
            $roomId = is_numeric($request->room_id) ? (int)$request->room_id : $request->room_id;
            $playerName = $request->player_name;
            $botName = $request->bot_name;
            $isPermanent = $request->is_permanent;

            // 1. Récupérer le joueur à remplacer (via user_id depuis users)
            $player = DB::table('room_players')
                ->join('users', 'room_players.user_id', '=', 'users.user_id')
                ->where('room_players.room_id', $roomId)
                ->where('users.pseudo', $playerName)
                ->select('room_players.*', 'users.pseudo')
                ->first();

            if (!$player) {
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'message' => 'Joueur non trouvé',
                ], 404);
            }

            // 2. Vérifier si le bot existe déjà dans la room
            $botUser = DB::table('users')
                ->where('pseudo', $botName)
                ->where('is_bot', true)
                ->first();

            if (!$botUser) {
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'message' => 'Bot non trouvé',
                ], 404);
            }

            // 3. Vérifier si le bot est déjà dans room_players
            $botInRoom = DB::table('room_players')
                ->where('room_id', $roomId)
                ->where('user_id', $botUser->user_id)
                ->first();

            if (!$botInRoom) {
                // Créer le bot dans room_players
                DB::table('room_players')->insert([
                    'room_id' => $roomId,
                    'user_id' => $botUser->user_id,
                    'position' => $player->position,
                    'is_replacement_bot' => true,
                    'replaced_player_name' => $playerName,
                    'is_creator' => false,
                    'status' => $player->status ?? 'ready',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            } else {
                // Mettre à jour le bot existant
                DB::table('room_players')
                    ->where('room_id', $roomId)
                    ->where('user_id', $botUser->user_id)
                    ->update([
                        'is_replacement_bot' => true,
                        'replaced_player_name' => $playerName,
                        'updated_at' => now(),
                    ]);
            }

            // 4. Enregistrer le remplacement
            DB::table('player_replacements')->insert([
                'room_id' => $roomId,
                'player_name' => $playerName,
                'bot_name' => $botName,
                'is_permanent' => $isPermanent,
                'disconnected_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // 5. Marquer le joueur comme exclu si permanent
            if ($isPermanent) {
                DB::table('room_players')
                    ->where('room_id', $roomId)
                    ->where('user_id', $player->user_id)
                    ->update(['is_excluded' => true]);
            }

            DB::commit();

            // 6. Émettre l'événement WebSocket à tous les clients
            $this->wsService->broadcastToRoom($roomId, [
                'event' => 'player_replaced',
                'data' => [
                    'room_id' => $roomId,
                    'player_name' => $playerName,
                    'bot_name' => $botName,
                    'is_permanent' => $isPermanent,
                ],
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Joueur remplacé par bot',
                'data' => [
                    'room_id' => $roomId,
                    'player_replaced' => $playerName,
                    'bot_name' => $botName,
                    'is_permanent' => $isPermanent,
                ],
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Erreur remplacement joueur: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors du remplacement: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * POST /api/rooms/restore-player
     * Restaure un joueur qui s'est reconnecté
     */
    public function restorePlayer(Request $request)
    {
        $request->validate([
            'room_id' => 'required',
            'player_name' => 'required|string',
            'bot_name' => 'required|string',
        ]);

        try {
            DB::beginTransaction();

            // Convertir room_id en integer si c'est une string
            $roomId = is_numeric($request->room_id) ? (int)$request->room_id : $request->room_id;
            $playerName = $request->player_name;
            $botName = $request->bot_name;

            // 1. Vérifier que le remplacement existe et n'est pas permanent
            $replacement = DB::table('player_replacements')
                ->where('room_id', $roomId)
                ->where('player_name', $playerName)
                ->where('bot_name', $botName)
                ->whereNull('restored_at')
                ->where('is_permanent', false)
                ->first();

            if (!$replacement) {
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'message' => 'Remplacement non trouvé ou déjà permanent',
                ], 404);
            }

            // 2. Retirer le bot de room_players
            $botUser = DB::table('users')
                ->where('pseudo', $botName)
                ->where('is_bot', true)
                ->first();

            if ($botUser) {
                DB::table('room_players')
                    ->where('room_id', $roomId)
                    ->where('user_id', $botUser->user_id)
                    ->delete();
            }

            // 3. Restaurer le joueur (retirer is_excluded)
            $playerUser = DB::table('users')
                ->where('pseudo', $playerName)
                ->first();

            if ($playerUser) {
                DB::table('room_players')
                    ->where('room_id', $roomId)
                    ->where('user_id', $playerUser->user_id)
                    ->update([
                        'is_excluded' => false,
                        'updated_at' => now(),
                    ]);
            }

            // 4. Marquer le remplacement comme restauré
            DB::table('player_replacements')
                ->where('replacement_id', $replacement->replacement_id)
                ->update(['restored_at' => now(), 'updated_at' => now()]);

            DB::commit();

            // 5. Émettre l'événement WebSocket
            $this->wsService->broadcastToRoom($roomId, [
                'event' => 'player_restored',
                'data' => [
                    'room_id' => $roomId,
                    'player_name' => $playerName,
                    'bot_name' => $botName,
                ],
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Joueur restauré',
                'data' => [
                    'room_id' => $roomId,
                    'player_restored' => $playerName,
                    'bot_removed' => $botName,
                ],
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Erreur restauration joueur: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la restauration: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * POST /api/rooms/player-disconnected
     * Notifie une déconnexion de joueur
     */
    public function notifyPlayerDisconnection(Request $request)
    {
        $request->validate([
            'room_id' => 'required',
            'player_name' => 'required|string',
        ]);

        try {
            // Convertir room_id en integer si c'est une string
            $roomId = is_numeric($request->room_id) ? (int)$request->room_id : $request->room_id;
            $playerName = $request->player_name;

            // Enregistrer la déconnexion avec timestamp
            DB::table('player_disconnections')->insert([
                'room_id' => $roomId,
                'player_name' => $playerName,
                'disconnected_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // Émettre l'événement WebSocket
            $this->wsService->broadcastToRoom($roomId, [
                'event' => 'player_disconnected',
                'data' => [
                    'room_id' => $roomId,
                    'player_name' => $playerName,
                    'timestamp' => now()->toIso8601String(),
                ],
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Déconnexion notifiée',
            ]);

        } catch (\Exception $e) {
            Log::error('Erreur notification déconnexion: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la notification: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * POST /api/rooms/player-reconnected
     * Notifie une reconnexion de joueur
     */
    public function notifyPlayerReconnection(Request $request)
    {
        $request->validate([
            'room_id' => 'required',
            'player_name' => 'required|string',
        ]);

        try {
            // Convertir room_id en integer si c'est une string
            $roomId = is_numeric($request->room_id) ? (int)$request->room_id : $request->room_id;
            $playerName = $request->player_name;

            // Vérifier la dernière déconnexion
            $lastDisconnection = DB::table('player_disconnections')
                ->where('room_id', $roomId)
                ->where('player_name', $playerName)
                ->whereNull('reconnected_at')
                ->orderBy('disconnected_at', 'desc')
                ->first();

            $canRestore = false;
            if ($lastDisconnection) {
                $secondsSinceDisconnection = now()->diffInSeconds($lastDisconnection->disconnected_at);
                $canRestore = $secondsSinceDisconnection < 15;

                // Marquer comme reconnecté
                DB::table('player_disconnections')
                    ->where('id', $lastDisconnection->id)
                    ->update(['reconnected_at' => now(), 'updated_at' => now()]);
            }

            // Émettre l'événement WebSocket
            $this->wsService->broadcastToRoom($roomId, [
                'event' => 'player_reconnected',
                'data' => [
                    'room_id' => $roomId,
                    'player_name' => $playerName,
                    'can_restore' => $canRestore,
                ],
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Reconnexion notifiée',
                'can_restore' => $canRestore,
            ]);

        } catch (\Exception $e) {
            Log::error('Erreur notification reconnexion: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la notification: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * POST /api/rooms/check-exclusion
     * Vérifie si un joueur est exclu
     */
    public function checkPlayerExclusion(Request $request)
    {
        $request->validate([
            'room_id' => 'required',
            'player_name' => 'required|string',
        ]);

        try {
            // Convertir room_id en integer si c'est une string
            $roomId = is_numeric($request->room_id) ? (int)$request->room_id : $request->room_id;
            $playerName = $request->player_name;

            // Récupérer le joueur
            $player = DB::table('room_players')
                ->join('users', 'room_players.user_id', '=', 'users.user_id')
                ->where('room_players.room_id', $roomId)
                ->where('users.pseudo', $playerName)
                ->select('room_players.is_excluded')
                ->first();

            $isExcluded = false;
            $reason = 'not_excluded';

            if (!$player) {
                // Joueur non trouvé = exclu
                $isExcluded = true;
                $reason = 'not_found';
            } else {
                $isExcluded = (bool) $player->is_excluded;

                if ($isExcluded) {
                    // Vérifier si c'est dû à une déconnexion trop longue ou départ manuel
                    $replacement = DB::table('player_replacements')
                        ->where('room_id', $roomId)
                        ->where('player_name', $playerName)
                        ->where('is_permanent', true)
                        ->whereNull('restored_at')
                        ->first();

                    $reason = $replacement ? 'disconnected_too_long' : 'manual_leave';
                }
            }

            return response()->json([
                'success' => true,
                'is_excluded' => $isExcluded,
                'reason' => $reason,
            ]);

        } catch (\Exception $e) {
            Log::error('Erreur vérification exclusion: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la vérification: ' . $e->getMessage(),
            ], 500);
        }
    }
}
