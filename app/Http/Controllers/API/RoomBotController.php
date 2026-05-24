<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Room;
use App\Services\RoomBotService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class RoomBotController extends Controller
{
    public function __construct(private RoomBotService $roomBotService)
    {
    }

    public function fill(Request $request)
    {
        $validated = $request->validate([
            'room_id' => 'required',
        ]);

        $room = Room::where('room_id', $validated['room_id'])->first();
        if (!$room) {
            return response()->json(['success' => false, 'message' => 'Room not found'], 404);
        }

        try {
            $result = $this->roomBotService->fillRoom($room);

            Log::info("🤖 fillBots: Room {$room->room_id} — {$result['added']} bot(s) ajouté(s)");

            return response()->json([
                'success' => true,
                'message' => $result['added'] > 0
                    ? $result['added'] . ' bot(s) ajouté(s) à la salle'
                    : 'Room already full',
                'data' => [
                    'room_id' => $room->room_id,
                    'human_players' => $result['human_players'],
                    'added_bots' => $result['added'],
                    'players' => $result['players'],
                ],
            ]);
        } catch (\RuntimeException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        } catch (\Exception $e) {
            Log::error('fillBots error: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Erreur: ' . $e->getMessage(),
            ], 500);
        }
    }
}
