<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use App\Models\User;
use App\Mail\VerificationEmail;
use App\Mail\PasswordResetEmail;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;

class AuthController extends Controller
{
    public function test(){
        dd('test ok');
    }
    /**
     * Inscription d'un nouvel utilisateur avec envoi d'email de vérification
     */
    public function register(Request $request)
    {
        try {
            // Validation
            $validator = Validator::make($request->all(), [
                'pseudo' => 'required|string|max:50|unique:users',
                'email' => 'required|string|email|max:100|unique:users',
                'password' => 'required|string|min:8',
                'first_name' => 'nullable|string|max:50',
                'last_name' => 'nullable|string|max:50',
                'phone' => 'nullable|string|max:20',
                'address' => 'nullable|string',
                'avatar' => 'nullable|string|max:255',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Erreur de validation',
                    'errors' => $validator->errors()
                ], 422);
            }

            // ⚠️ SÉCURITÉ : Toujours forcer role = 'user' pour les inscriptions publiques
            // Le champ 'role' dans la requête est IGNORÉ pour éviter l'élévation de privilèges
            // Les admins doivent être créés uniquement via le panel d'administration
            
            // Créer l'utilisateur inactif avec role = 'user' (toujours)
            $user = User::create([
                'pseudo' => $request->pseudo,
                'email' => $request->email,
                'password_hash' => Hash::make($request->password),
                'first_name' => $request->first_name,
                'last_name' => $request->last_name,
                'phone' => $request->phone,
                'address' => $request->address,
                'avatar' => $request->avatar ?? '👤',
                'theme_preference' => 'light',
                'role' => 'user', // ⚠️ Toujours 'user' pour les inscriptions publiques
                'is_active' => false, // ⭐ Inactif jusqu'à vérification
            ]);

            // Générer un code de vérification
            $code = str_pad(rand(0, 999999), 6, '0', STR_PAD_LEFT);
            
            // Stocker le code dans la base de données
            DB::table('email_verification_codes')->insert([
                'email' => $request->email,
                'code' => $code,
                'type' => 'verification',
                'expires_at' => Carbon::now()->addHours(24),
                'used' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // 📧 Envoyer l'email de vérification
            try {
                $userName = $request->first_name ?? $request->pseudo;
                Mail::to($request->email)->send(new VerificationEmail($code, $request->pseudo, 24, $request->first_name));
            } catch (\Exception $mailException) {
                // En cas d'erreur d'email, continuer quand même
                \Log::error('Erreur envoi email: ' . $mailException->getMessage());
            }

            // Créer les paramètres par défaut
            $user->settings()->create([
                'language' => 'fr',
                'theme_mode' => 'light',
                'notifications_enabled' => true,
                'sound_enabled' => true,
                'vibration_enabled' => true,
            ]);

            return $this->apiResponse(true, 'Email de vérification envoyé', [
                'user' => [
                    'user_id' => $user->user_id,
                    'pseudo' => $user->pseudo,
                    'email' => $user->email,
                ],
            ], 201, false);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de l\'inscription: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Connexion d'un utilisateur
     */
    public function login(Request $request)
    {
        try {
            // Validation
            $validator = Validator::make($request->all(), [
                'login' => 'required|string', // Pseudo ou Email
                'password' => 'required|string',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Erreur de validation',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Rechercher l'utilisateur par email ou pseudo (Insensible à la casse pour PostgreSQL)
            $user = User::where(function($query) use ($request) {
                $query->where('email', 'ILIKE', $request->login)
                      ->orWhere('pseudo', 'ILIKE', $request->login);
            })->first();

            if (!$user || !Hash::check($request->password, $user->password_hash)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Pseudo/Email ou mot de passe incorrect'
                ], 401);
            }

            // Déterminer le rôle si non défini (pour compatibilité avec anciennes données)
            $role = $user->role ?? (
                ($user->pseudo === 'superAdmin' || $user->email === 'superadmin@cauris.com') ? 'superadmin' :
                (($user->pseudo === 'manageradmin' || $user->email === 'manageradmin@cauris.com') ? 'manager' : 'user')
            );

            // ⚠️ Bloquer les utilisateurs avec role = superadmin, admin, ou manager
            // UNIQUEMENT si la requête vient de l'application mobile
            // Si source=web ou pas de source, permettre la connexion (c'est le panel web)
            $source = $request->input('source', 'web'); // Par défaut 'web' pour le panel admin
            
            if ($source === 'mobile' && in_array($role, ['superadmin', 'admin', 'manager'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Les administrateurs ne peuvent pas accéder à l\'application mobile. Veuillez utiliser le panel web d\'administration.',
                    'admin_panel_url' => env('APP_URL') . '/admin',
                    'redirect' => true
                ], 403);
            }

            if (!$user->is_active) {
                return response()->json([
                    'success' => false,
                    'message' => 'Compte désactivé'
                ], 403);
            }

            // Mettre à jour la dernière connexion
            $user->update(['last_login' => now()]);

            // Créer le token
            $token = $user->createToken('cauris-token')->plainTextToken;

            // Déterminer le rôle si non défini (pour compatibilité avec anciennes données)
            $role = $user->role ?? (
                ($user->pseudo === 'superAdmin' || $user->email === 'superadmin@cauris.com') ? 'superadmin' :
                (($user->pseudo === 'manageradmin' || $user->email === 'manageradmin@cauris.com') ? 'manager' : 'user')
            );

            return $this->apiResponse(true, 'Connexion réussie', [
                'token' => $token,
                'user' => [
                    'user_id' => $user->user_id,
                    'pseudo' => $user->pseudo,
                    'email' => $user->email,
                    'avatar' => $user->avatar,
                    'theme_preference' => $user->theme_preference,
                    'role' => $role,
                    'cauris_balance' => $user->cauris_balance ?? 0,
                ]
            ], 200, false);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la connexion: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Vérifier le code d'email et activer le compte
     */
    public function verifyEmail(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'email' => 'required|email',
                'code' => 'required|string|size:6',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Erreur de validation',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Vérifier le code
            $codeRecord = DB::table('email_verification_codes')
                ->where('email', $request->email)
                ->where('code', $request->code)
                ->where('type', 'verification')
                ->where('used', false)
                ->where('expires_at', '>', now())
                ->first();

            if (!$codeRecord) {
                return response()->json([
                    'success' => false,
                    'message' => 'Code invalide ou expiré'
                ], 401);
            }

            // Activer le compte
            $user = User::where('email', 'ILIKE', $request->email)->first();
            $user->update(['is_active' => true]);

            // Marquer le code comme utilisé
            DB::table('email_verification_codes')
                ->where('code_id', $codeRecord->code_id)
                ->update(['used' => true]);

            // Déterminer le rôle si non défini (pour compatibilité avec anciennes données)
            $role = $user->role ?? (
                ($user->pseudo === 'superAdmin' || $user->email === 'superadmin@cauris.com') ? 'superadmin' :
                (($user->pseudo === 'manageradmin' || $user->email === 'manageradmin@cauris.com') ? 'manager' : 'user')
            );

            // ⚠️ Bloquer les utilisateurs avec role = superadmin, admin, ou manager
            // UNIQUEMENT si la requête vient de l'application mobile
            // Si source=web ou pas de source, permettre la connexion (c'est le panel web)
            $source = $request->input('source', 'web'); // Par défaut 'web' pour le panel admin
            
            if ($source === 'mobile' && in_array($role, ['superadmin', 'admin', 'manager'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Les administrateurs ne peuvent pas accéder à l\'application mobile. Veuillez utiliser le panel web d\'administration.',
                    'admin_panel_url' => env('APP_URL') . '/admin',
                    'redirect' => true
                ], 403);
            }

            // Créer le token
            $token = $user->createToken('cauris-token')->plainTextToken;

            return response()->json([
                'success' => true,
                'message' => 'Email vérifié avec succès',
                'token' => $token,
                'user' => [
                    'user_id' => $user->user_id,
                    'pseudo' => $user->pseudo,
                    'email' => $user->email,
                    'avatar' => $user->avatar,
                    'theme_preference' => $user->theme_preference,
                    'role' => $role,
                    'cauris_balance' => $user->cauris_balance ?? 0,
                ]
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Demander un code de réinitialisation
     */
    public function forgotPassword(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'email' => 'required|email',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Erreur de validation'
                ], 422);
            }

            $user = User::where('email', 'ILIKE', $request->email)->first();
            
            if (!$user) {
                // Ne pas révéler que l'email n'existe pas
                return response()->json([
                    'success' => true,
                    'message' => 'Si cet email existe, un code vous sera envoyé'
                ], 200);
            }

            // Générer un code
            $code = str_pad(rand(0, 999999), 6, '0', STR_PAD_LEFT);
            
            // Stocker le code
            DB::table('email_verification_codes')->insert([
                'email' => $request->email,
                'code' => $code,
                'type' => 'reset',
                'expires_at' => Carbon::now()->addHours(1),
                'used' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // 📧 Envoyer l'email
            Mail::to($request->email)->send(new PasswordResetEmail($code, $user->pseudo, 1));

            return response()->json([
                'success' => true,
                'message' => 'Code de réinitialisation envoyé par email'
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Vérifier le code de réinitialisation
     */
    public function verifyResetCode(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'email' => 'required|email',
                'code' => 'required|string|size:6',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Erreur de validation'
                ], 422);
            }

            // Vérifier le code
            $codeRecord = DB::table('email_verification_codes')
                ->where('email', $request->email)
                ->where('code', $request->code)
                ->where('type', 'reset')
                ->where('used', false)
                ->where('expires_at', '>', now())
                ->first();

            if (!$codeRecord) {
                return response()->json([
                    'success' => false,
                    'message' => 'Code invalide ou expiré'
                ], 401);
            }

            // Générer un token temporaire
            $resetToken = bin2hex(random_bytes(32));

            return response()->json([
                'success' => true,
                'resetToken' => $resetToken
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Réinitialiser le mot de passe
     */
    public function resetPassword(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'email' => 'required|email',
                'password' => 'required|string|min:8',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Erreur de validation'
                ], 422);
            }

            $user = User::where('email', 'ILIKE', $request->email)->first();
            
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Utilisateur non trouvé'
                ], 404);
            }

            // Mettre à jour le mot de passe
            $user->update([
                'password_hash' => Hash::make($request->password)
            ]);

            // Marquer tous les codes comme utilisés
            DB::table('email_verification_codes')
                ->where('email', $request->email)
                ->where('type', 'reset')
                ->update(['used' => true]);

            return response()->json([
                'success' => true,
                'message' => 'Mot de passe réinitialisé avec succès'
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Renvoyer le code de vérification
     */
    public function resendVerificationCode(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'email' => 'required|email',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Erreur de validation'
                ], 422);
            }

            // Vérifier si l'utilisateur existe et n'est pas encore activé
            $user = User::where('email', 'ILIKE', $request->email)->first();
            
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Aucun compte trouvé avec cet email'
                ], 404);
            }

            if ($user->is_active) {
                return response()->json([
                    'success' => false,
                    'message' => 'Ce compte est déjà activé'
                ], 400);
            }

            // Générer un nouveau code
            $code = str_pad(rand(0, 999999), 6, '0', STR_PAD_LEFT);
            
            // Marquer l'ancien code comme utilisé
            DB::table('email_verification_codes')
                ->where('email', $request->email)
                ->where('type', 'verification')
                ->where('used', false)
                ->update(['used' => true]);
            
            // Stocker le nouveau code
            DB::table('email_verification_codes')->insert([
                'email' => $request->email,
                'code' => $code,
                'type' => 'verification',
                'expires_at' => Carbon::now()->addHours(24),
                'used' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // 📧 Envoyer l'email
            try {
                Mail::to($request->email)->send(new VerificationEmail($code, $user->pseudo, 24, $user->first_name));
            } catch (\Exception $mailException) {
                \Log::error('Erreur envoi email: ' . $mailException->getMessage());
            }

            return response()->json([
                'success' => true,
                'message' => 'Nouveau code envoyé par email'
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Déconnexion d'un utilisateur
     */
    public function logout(Request $request)
    {
        try {
            // Supprimer tous les tokens de l'utilisateur
            $request->user()->tokens()->delete();

            return response()->json([
                'success' => true,
                'message' => 'Déconnexion réussie'
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la déconnexion: ' . $e->getMessage()
            ], 500);
        }
    }
}
