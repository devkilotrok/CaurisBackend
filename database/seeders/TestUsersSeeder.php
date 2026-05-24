<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

class TestUsersSeeder extends Seeder
{
    public function run(): void
    {
        $users = [
            ['pseudo' => 'Alpha', 'email' => 'alpha@cauris.com'],
            ['pseudo' => 'Elias', 'email' => 'elias@cauris.com'],
            ['pseudo' => 'Geraulin', 'email' => 'geraulin@cauris.com'],
            ['pseudo' => 'Grace', 'email' => 'grace@cauris.com'],
        ];

        foreach ($users as $u) {
            $attributes = [
                'pseudo' => $u['pseudo'],
                'password_hash' => Hash::make('12345678'),
                'avatar' => '👤',
                'role' => 'user',
                'is_active' => true,
                'is_bot' => false,
                'cauris_balance' => 1000,
            ];

            if (Schema::hasColumn('users', 'name')) {
                $attributes['name'] = $u['pseudo'];
            }

            $user = User::updateOrCreate(
                ['email' => $u['email']],
                $attributes
            );

            if (!Schema::hasTable('user_settings') || !Schema::hasColumn('user_settings', 'user_id')) {
                continue;
            }

            $user->settings()->updateOrCreate(
                ['user_id' => $user->user_id],
                [
                    'language' => 'fr',
                    'theme_mode' => 'light',
                    'notifications_enabled' => true,
                    'sound_enabled' => true,
                    'vibration_enabled' => true,
                ]
            );
        }
    }
}

