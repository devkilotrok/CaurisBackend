<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

class BotUsersSeeder extends Seeder
{
    public function run(): void
    {
        $bots = [
            ['pseudo' => 'Lewis', 'email' => 'bot_lewis@cauris.local'],
            ['pseudo' => 'Bil', 'email' => 'bot_bil@cauris.local'],
            ['pseudo' => 'Jonh', 'email' => 'bot_jonh@cauris.local'],
            ['pseudo' => 'Bot1', 'email' => 'bot1@cauris.local'],
            ['pseudo' => 'Bot2', 'email' => 'bot2@cauris.local'],
            ['pseudo' => 'Bot3', 'email' => 'bot3@cauris.local'],
        ];

        foreach ($bots as $bot) {
            $attributes = [
                'pseudo' => $bot['pseudo'],
                'password_hash' => Hash::make('CaurisBot123!'),
                'avatar' => '🤖',
                'role' => 'user',
                'is_active' => true,
                'is_bot' => true,
                'cauris_balance' => 0,
            ];

            if (Schema::hasColumn('users', 'name')) {
                $attributes['name'] = $bot['pseudo'];
            }

            User::updateOrCreate(
                ['email' => $bot['email']],
                $attributes
            );
        }
    }
}
