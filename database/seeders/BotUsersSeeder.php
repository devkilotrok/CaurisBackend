<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use App\Models\User;

class BotUsersSeeder extends Seeder
{
    public function run(): void
    {
        $bots = [
            ['first_name' => 'Lewis', 'last_name' => 'Bot', 'pseudo' => 'Lewis', 'email' => 'bot_lewis@example.com'],
            ['first_name' => 'Bil',   'last_name' => 'Bot', 'pseudo' => 'Bil',   'email' => 'bot_bil@example.com'],
            ['first_name' => 'Jonh',  'last_name' => 'Bot', 'pseudo' => 'Jonh',  'email' => 'bot_jonh@example.com'],
        ];

        foreach ($bots as $bot) {
            $data = [
                'password' => Hash::make('CaurisBot123!'),
                'is_bot' => true,
                'role' => 'admin',
            ];

            // Champs optionnels selon ton schéma réel
            if (Schema::hasColumn('users', 'first_name')) {
                $data['first_name'] = $bot['first_name'];
            }
            if (Schema::hasColumn('users', 'last_name')) {
                $data['last_name'] = $bot['last_name'];
            }
            if (Schema::hasColumn('users', 'pseudo')) {
                $data['pseudo'] = $bot['pseudo'];
            }
            if (Schema::hasColumn('users', 'name')) {
                $data['name'] = $bot['first_name'].' '.$bot['last_name'];
            }
            if (Schema::hasColumn('users', 'email_verified_at')) {
                $data['email_verified_at'] = now();
            }
            if (Schema::hasColumn('users', 'remember_token')) {
                $data['remember_token'] = Str::random(10);
            }

            User::updateOrCreate(
                ['email' => $bot['email']],
                $data
            );
        }
    }
}


