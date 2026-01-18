<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\Room;
use App\Models\Game;

class GameFinalizeController extends Controller
{
    public function finalize(Request $request)
    {
        $data = $request->validate([
            'room_id' => 'required',
            'winner_name' => 'required|string',
            'winner_score' => 'required|integer',
            'final_scores' => 'sometimes|array',
        ]);

        $room = Room::where('room_id', $data['room_id'])->first();
        if (!$room) {
            return response()->json(['success' => false, 'message' => 'Room not found'], 404);
        }

        // Récupère ou crée l'enregistrement Game lié au salon
        /** @var Game $game */
        $game = Game::firstOrCreate(
            ['room_id' => $room->room_id],
            // Valeurs par défaut si l'enregistrement n'existe pas
            ['deck_id' => 0]
        );

        $game->finished_at = now();
        // Conserver les scores finaux si fournis
        if (isset($data['final_scores'])) {
            $game->final_scores = $data['final_scores'];
        }
        // Stocker le gagnant de façon non destructive: conserver aussi dans final_scores meta
        $scores = $game->final_scores ?? [];
        $scores['winner'] = [
            'name' => $data['winner_name'],
            'score' => $data['winner_score'],
            'saved_at' => now()->toDateTimeString(),
        ];
        $game->final_scores = $scores;
        $game->save();

        return response()->json([
            'success' => true,
            'game_id' => $game->game_id,
        ]);
    }
}
