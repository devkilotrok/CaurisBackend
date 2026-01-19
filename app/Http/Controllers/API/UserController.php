<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use App\Models\User;
use App\Models\UserSetting;

class UserController extends Controller
{
    /**
     * Récupérer le profil de l'utilisateur connecté
     */
    public function profile(Request $request)
    {
        try {
            $user = $request->user();

            // Récupérer les statistiques
            $stats = $this->calculateUserStats($user->user_id);

            // Déterminer le rôle si non défini (pour compatibilité avec anciennes données)
            $role = $user->role ?? (
                ($user->pseudo === 'superAdmin' || $user->email === 'superadmin@cauris.com') ? 'superadmin' :
                (($user->pseudo === 'manageradmin' || $user->email === 'manageradmin@cauris.com') ? 'manager' : 'user')
            );

            return $this->apiResponse(true, 'Profil récupéré', [
                'user' => [
                    'user_id' => $user->user_id,
                    'pseudo' => $user->pseudo,
                    'email' => $user->email,
                    'avatar' => $user->avatar,
                    'theme_preference' => $user->theme_preference,
                    'role' => $role,
                    'is_active' => $user->is_active,
                    'last_login' => $user->last_login,
                    'cauris_balance' => (int)($user->cauris_balance ?? 0),
                    'balance' => (int)($user->cauris_balance ?? 0),
                    'solde' => (int)($user->cauris_balance ?? 0),
                    'stats' => $stats,
                ]
            ], 200, false);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Mettre à jour le profil
     */
    public function updateProfile(Request $request)
    {
        try {
            $user = $request->user();

            // Validation
            $validator = Validator::make($request->all(), [
                'pseudo' => 'sometimes|string|max:50|unique:users,pseudo,' . $user->user_id . ',user_id',
                'avatar' => 'sometimes|string|max:255',
                'theme_preference' => 'sometimes|string|in:light,dark',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Erreur de validation',
                    'errors' => $validator->errors()
                ], 422);
            }

            $user->update($request->only(['pseudo', 'avatar', 'theme_preference']));

            return response()->json([
                'success' => true,
                'message' => 'Profil mis à jour',
                'data' => $user
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtenir les statistiques de l'utilisateur
     */
    public function stats(Request $request)
    {
        try {
            $user = $request->user();
            $stats = $this->calculateUserStats($user->user_id);

            return response()->json([
                'success' => true,
                'data' => $stats
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Rechercher des utilisateurs
     */
    public function search(Request $request)
    {
        try {
            $query = $request->get('query');

            if (!$query) {
                return response()->json([
                    'success' => false,
                    'message' => 'Paramètre query requis'
                ], 400);
            }

            $users = User::where('pseudo', 'LIKE', "%{$query}%")
                ->orWhere('email', 'LIKE', "%{$query}%")
                ->where('is_active', true)
                ->where('user_id', '!=', $request->user()->user_id)
                ->select('user_id', 'pseudo', 'email', 'avatar', 'role', 'is_bot')
                ->limit(20)
                ->get();

            return response()->json([
                'success' => true,
                'data' => $users
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Calculer les statistiques d'un utilisateur
     */
    private function calculateUserStats($userId)
    {
        $user = User::find($userId);
        
        return [
            'total_games' => DB::table('room_players')->where('user_id', $userId)->count(),
            'games_won' => 0, // À implémenter avec la table scores plus tard
            'games_lost' => 0,
            'avg_score' => 0,
            'best_score' => (int)DB::table('scores')->where('user_id', $userId)->max('score') ?? 0,
            'current_balance' => (int)($user->cauris_balance ?? 0),
        ];
    }
}
