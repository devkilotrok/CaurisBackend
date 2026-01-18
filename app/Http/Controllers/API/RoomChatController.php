<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\RoomChatMessage;
use App\Models\RoomPlayer;
use App\Services\WebSocketService;

class RoomChatController extends Controller
{
    protected $wsService;

    public function __construct(WebSocketService $wsService)
    {
        $this->wsService = $wsService;
    }

    /**
     * Liste des messages d'un salon
     */
    public function index(Request $request, $roomId)
    {
        try {
            $roomId = (int) $roomId;
            $user = $request->user();
            if (!$this->isPlayerInRoom($roomId, $user->user_id)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Accès refusé',
                ], 403);
            }

            $lastId = $request->get('last_id');

            $query = RoomChatMessage::with('user')
                ->where('room_id', $roomId);

            if ($lastId) {
                $query->where('id', '>', (int) $lastId)
                    ->orderBy('id', 'asc');
            } else {
                $query->orderBy('id', 'desc')
                    ->limit(50);
            }

            $messages = $query->get();
            if (!$lastId) {
                $messages = $messages->sortBy('id')->values();
            }

            return response()->json([
                'success' => true,
                'data' => $messages->map(function ($message) {
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
                })->values(),
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Envoyer un message dans un salon
     */
    public function store(Request $request, $roomId)
    {
        try {
            $roomId = (int) $roomId;
            $user = $request->user();
            if (!$this->isPlayerInRoom($roomId, $user->user_id)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Accès refusé',
                ], 403);
            }

            $validator = \Validator::make($request->all(), [
                'message' => 'nullable|string|max:200',
                'message_type' => 'nullable|in:text,preset,emoji',
                'preset_code' => 'nullable|string|max:50',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Erreur de validation',
                    'errors' => $validator->errors(),
                ], 422);
            }

            $messageText = trim((string) $request->message);
            $presetCode = $request->preset_code;
            $messageType = $request->message_type ?? ($presetCode ? 'preset' : 'text');

            if (empty($messageText) && !$presetCode) {
                return response()->json([
                    'success' => false,
                    'message' => 'Le message est requis.',
                ], 422);
            }

            if (empty($messageText) && $presetCode) {
                $messageText = $presetCode;
            }

            $chatMessage = RoomChatMessage::create([
                'room_id' => (int) $roomId,
                'user_id' => $user->user_id,
                'message_type' => $messageType,
                'message' => $messageText,
                'preset_code' => $presetCode,
            ]);

            $payload = [
                'id' => $chatMessage->id,
                'room_id' => $chatMessage->room_id,
                'user_id' => $chatMessage->user_id,
                'pseudo' => $user->pseudo ?? 'Joueur',
                'message' => $chatMessage->message,
                'message_type' => $chatMessage->message_type,
                'preset_code' => $chatMessage->preset_code,
                'created_at' => $chatMessage->created_at?->toISOString(),
            ];

            // Diffuser via WebSocket si disponible (non bloquant)
            // Format compatible avec le frontend (playerName au lieu de pseudo)
            $this->wsService->broadcastToRoom($roomId, [
                'event' => 'room_chat_message',
                'data' => [
                    'roomId' => (string) $roomId,
                    'playerName' => $user->pseudo ?? 'Joueur',
                    'message' => $chatMessage->message,
                    'message_type' => $chatMessage->message_type,
                    'preset_code' => $chatMessage->preset_code,
                ],
            ]);

            return response()->json([
                'success' => true,
                'data' => $payload,
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur: ' . $e->getMessage(),
            ], 500);
        }
    }

    private function isPlayerInRoom($roomId, $userId): bool
    {
        return RoomPlayer::where('room_id', $roomId)
            ->where('user_id', $userId)
            ->exists();
    }
}


