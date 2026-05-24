<?php

namespace App\Services;

use App\Models\Room;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class RoomBotService
{
    public const MAX_PLAYERS = 4;

    /**
     * Crée des comptes bots en base si le seed n'a pas été exécuté.
     */
    public function ensureBotsExist(int $minimum): void
    {
        $available = $this->countAvailableBots([]);
        if ($available >= $minimum) {
            return;
        }

        $toCreate = $minimum - $available;
        for ($i = 0; $i < $toCreate; $i++) {
            $n = 1;
            do {
                $pseudo = 'Bot' . $n++;
            } while (User::where('pseudo', $pseudo)->exists());

            $attributes = [
                'pseudo' => $pseudo,
                'email' => strtolower($pseudo) . '@bots.cauris.local',
                'password_hash' => Hash::make('CaurisBot123!'),
                'avatar' => '🤖',
                'role' => 'user',
                'is_active' => true,
                'is_bot' => true,
                'cauris_balance' => 0,
            ];

            if (Schema::hasColumn('users', 'name')) {
                $attributes['name'] = $pseudo;
            }

            User::create($attributes);

            Log::info("🤖 Bot créé automatiquement: {$pseudo}");
        }
    }

    /**
     * Remplit une salle jusqu'à 4 joueurs avec des bots.
     *
     * @return array{added: int, players: \Illuminate\Support\Collection, human_players: int}
     */
    public function fillRoom(Room $room): array
    {
        $humanPlayers = DB::table('room_players')
            ->join('users', 'room_players.user_id', '=', 'users.user_id')
            ->where('room_players.room_id', $room->room_id)
            ->where(function ($query) {
                $query->where(function ($q) {
                    $q->where('users.is_bot', 0)
                        ->orWhereNull('users.is_bot');
                })
                ->where(function ($q) {
                    $q->where('users.pseudo', 'NOT LIKE', 'Bot%')
                        ->whereNotIn('users.pseudo', ['Bot1', 'Bot2', 'Bot3']);
                });
            })
            ->count();

        $needed = max(0, self::MAX_PLAYERS - $humanPlayers);

        if ($needed === 0) {
            return [
                'added' => 0,
                'human_players' => $humanPlayers,
                'players' => $this->getRoomPlayers($room->room_id),
            ];
        }

        $botsInRoom = DB::table('room_players')
            ->where('room_id', $room->room_id)
            ->pluck('user_id')
            ->toArray();

        $this->ensureBotsExist($needed);

        $bots = User::where(function ($query) {
                $query->where('is_bot', 1)
                    ->orWhere('pseudo', 'LIKE', 'Bot%')
                    ->orWhereIn('pseudo', ['Bot1', 'Bot2', 'Bot3', 'Lewis', 'Bil', 'Jonh']);
            })
            ->where('is_active', 1)
            ->whereNotIn('user_id', $botsInRoom)
            ->limit($needed)
            ->get();

        if ($bots->count() < $needed) {
            throw new \RuntimeException(
                "Pas assez de bots disponibles. Nécessaire: {$needed}, Disponible: {$bots->count()}"
            );
        }

        $currentCount = DB::table('room_players')->where('room_id', $room->room_id)->count();
        $pos = $currentCount + 1;
        $added = 0;

        foreach ($bots as $bot) {
            $exists = DB::table('room_players')
                ->where('room_id', $room->room_id)
                ->where('user_id', $bot->user_id)
                ->exists();

            if ($exists) {
                continue;
            }

            DB::table('room_players')->insert([
                'room_id' => $room->room_id,
                'user_id' => $bot->user_id,
                'position' => $pos++,
                'is_creator' => false,
                'status' => 'ready',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $added++;
            Log::info("✅ Bot {$bot->pseudo} ajouté à la room {$room->room_id}");
        }

        return [
            'added' => $added,
            'human_players' => $humanPlayers,
            'players' => $this->getRoomPlayers($room->room_id),
        ];
    }

    public function getRoomPlayers(int $roomId)
    {
        return DB::table('room_players')
            ->join('users', 'room_players.user_id', '=', 'users.user_id')
            ->where('room_players.room_id', $roomId)
            ->select(
                'users.user_id as id',
                'users.user_id',
                'users.pseudo',
                'users.first_name',
                'users.last_name',
                'users.is_bot',
                'room_players.position'
            )
            ->orderBy('room_players.position')
            ->get();
    }

    private function countAvailableBots(array $excludeUserIds): int
    {
        return User::where(function ($query) {
                $query->where('is_bot', 1)
                    ->orWhere('pseudo', 'LIKE', 'Bot%')
                    ->orWhereIn('pseudo', ['Bot1', 'Bot2', 'Bot3', 'Lewis', 'Bil', 'Jonh']);
            })
            ->where('is_active', 1)
            ->whereNotIn('user_id', $excludeUserIds)
            ->count();
    }
}
