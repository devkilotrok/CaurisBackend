<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Models\Room;
use App\Models\User;

class RoomBotController extends Controller
{
    // Remplit un salon avec des bots existants depuis la base de données jusqu'à 4 joueurs
    public function fill(Request $request)
    {
        $validated = $request->validate([
            'room_id' => 'required',
        ]);

        $roomId = $validated['room_id'];

        $room = Room::where('room_id', $roomId)->first();
        if (!$room) {
            return response()->json(['success' => false, 'message' => 'Room not found'], 404);
        }

        // 1. Compter uniquement les joueurs humains (exclure les bots)
        $humanPlayers = DB::table('room_players')
            ->join('users', 'room_players.user_id', '=', 'users.user_id')
            ->where('room_players.room_id', $room->room_id)
            ->where(function($query) {
                $query->where(function($q) {
                    // Joueurs avec is_bot = 0 ou NULL
                    $q->where('users.is_bot', 0)
                      ->orWhereNull('users.is_bot');
                })
                ->where(function($q) {
                    // ET qui ne sont pas des bots par leur pseudo
                    $q->where('users.pseudo', 'NOT LIKE', 'Bot%')
                      ->whereNotIn('users.pseudo', ['Bot1', 'Bot2', 'Bot3']);
                });
            })
            ->count();
        
        // 2. Calculer combien de bots sont nécessaires (4 - nombre de joueurs humains)
        $maxPlayers = 4;
        $needed = max(0, $maxPlayers - $humanPlayers);
        
        Log::info("🤖 fillBots: Room {$roomId} a {$humanPlayers} joueurs humains, besoin de {$needed} bots");
        
        if ($needed === 0) {
            return response()->json([
                'success' => true, 
                'message' => 'Room already full',
                'data' => [
                    'room_id' => $room->room_id,
                    'human_players' => $humanPlayers,
                    'needed_bots' => 0,
                ]
            ]);
        }

        // 3. Récupérer les bots disponibles depuis users WHERE is_bot = 1
        // Exclure les bots déjà dans cette room
        $botsInRoom = DB::table('room_players')
            ->where('room_id', $room->room_id)
            ->pluck('user_id')
            ->toArray();
        
        $bots = User::where(function($query) {
                $query->where('is_bot', 1)
                      ->orWhere('pseudo', 'LIKE', 'Bot%')
                      ->orWhereIn('pseudo', ['Bot1', 'Bot2', 'Bot3']);
            })
            ->where('is_active', 1)
            ->whereNotIn('user_id', $botsInRoom)
            ->limit($needed)
            ->get();
        
        Log::info("🤖 fillBots: {$needed} bots demandés, " . $bots->count() . " bots disponibles trouvés");
        
        if ($bots->count() < $needed) {
            return response()->json([
                'success' => false, 
                'message' => "Pas assez de bots disponibles. Nécessaire: $needed, Disponible: {$bots->count()}",
                'data' => [
                    'needed' => $needed,
                    'available' => $bots->count(),
                ]
            ], 400);
        }
        
        // 4. Ajouter les bots à room_players pour cette room
        $currentCount = DB::table('room_players')->where('room_id', $room->room_id)->count();
        $pos = $currentCount + 1;
        $addedBots = [];
        
        foreach ($bots as $bot) {
            // Vérifier que le bot n'est pas déjà dans cette room
            $exists = DB::table('room_players')
                ->where('room_id', $room->room_id)
                ->where('user_id', $bot->user_id)
                ->exists();
            
            if (!$exists) {
                DB::table('room_players')->insert([
                    'room_id' => $room->room_id,
                    'user_id' => $bot->user_id,
                    'position' => $pos++,
                    'is_creator' => false,
                    'status' => 'ready',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                
                $addedBots[] = $bot;
                Log::info("✅ Bot {$bot->pseudo} (ID: {$bot->user_id}) ajouté à la room {$roomId}");
            }
        }

        // Récupérer tous les joueurs de la room (mise à jour)
        $players = DB::table('room_players')
            ->join('users', 'room_players.user_id', '=', 'users.user_id')
            ->where('room_players.room_id', $room->room_id)
            ->select('users.user_id as id', 'users.first_name', 'users.last_name', 'users.pseudo', 'users.is_bot')
            ->orderBy('room_players.position')
            ->get();

        return response()->json([
            'success' => true,
            'message' => count($addedBots) . ' bot(s) ajouté(s) à la salle',
            'data' => [
                'room_id' => $room->room_id,
                'human_players' => $humanPlayers,
                'added_bots' => count($addedBots),
                'players' => $players,
            ],
        ]);
    }
}


