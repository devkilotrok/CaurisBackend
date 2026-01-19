<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Room;
use App\Models\Game;
use App\Models\AdminLog;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;
use App\Mail\AccountBannedEmail;
use App\Mail\AccountUnbannedEmail;
use App\Mail\AccountDeletedEmail;

class AdminController extends Controller
{
    /**
     * Vérifier que l'utilisateur est admin (superadmin ou admin, pas manager)
     */
    private function checkAdminAccess($user)
    {
        if (!$user) {
            abort(403, 'Accès refusé. Authentification requise.');
        }
        
        // Déterminer le rôle
        $role = $user->role ?? (
            (($user->pseudo === 'superAdmin' || $user->email === 'superadmin@cauris.com') ? 'superadmin' :
            (($user->pseudo === 'manageradmin' || $user->email === 'manageradmin@cauris.com') ? 'manager' : 'user'))
        );
        
        // Seuls superadmin et admin peuvent accéder (pas manager ni user)
        if (!in_array($role, ['superadmin', 'admin'])) {
            abort(403, 'Accès refusé. Administrateurs uniquement.');
        }
    }
    
    /**
     * Vérifier que l'utilisateur est superadmin
     */
    private function checkSuperAdminAccess($user)
    {
        if (!$user) {
            abort(403, 'Accès refusé. Authentification requise.');
        }
        
        // Déterminer le rôle
        $role = $user->role ?? (
            (($user->pseudo === 'superAdmin' || $user->email === 'superadmin@cauris.com') ? 'superadmin' : null)
        );
        
        if ($role !== 'superadmin') {
            abort(403, 'Accès refusé. Super administrateur uniquement.');
        }
    }

    /**
     * Tableau de bord admin
     */
    public function dashboard(Request $request)
    {
        try {
            $this->checkAdminAccess($request->user());
            $totalUsers = User::count();
            $activeUsers = User::where('is_active', true)->count();
            $totalRooms = Room::count();
            $totalGames = \App\Models\Game::count();
            $activeRooms = Room::where('status', 'playing')->count();
            $waitingRooms = Room::where('status', 'waiting')->count();
            $finishedRooms = Room::where('status', 'finished')->count();
            $cancelledRooms = Room::where('status', 'cancelled')->count();
            
            // Solde total de cauris en circulation (uniquement les users normaux, pas les admins/managers)
            $totalCaurisInCirculation = User::where(function($query) {
                $query->where('role', 'user')
                      ->orWhereNull('role'); // Compatibilité avec l'ancien système (null = user normal)
            })
            ->sum('cauris_balance');
            
            // Solde total de l'entreprise (somme des company_balance)
            $companyBalance = User::sum('company_balance');
            
            // Statistiques des transactions
            $totalTransactions = DB::table('transactions')->count();
            $pendingTransactions = DB::table('transactions')->where('status', 'en_attente')->count();
            $validatedTransactions = DB::table('transactions')->where('status', 'valide')->count();
            $rejectedTransactions = DB::table('transactions')->where('status', 'rejete')->count();
            
            // Montants totaux
            $totalDeposits = DB::table('transactions')->where('type', 'depot')->where('status', 'valide')->sum('fcfa_amount');
            $totalWithdrawals = DB::table('transactions')->where('type', 'retrait')->where('status', 'valide')->sum('fcfa_amount');
            
            // Transactions récentes
            $recentTransactions = DB::table('transactions')
                ->join('users', 'transactions.user_id', '=', 'users.user_id')
                ->select(
                    'transactions.transaction_id',
                    'transactions.type',
                    'transactions.status',
                    'transactions.cauris_amount',
                    'transactions.fcfa_amount',
                    'transactions.created_at',
                    'users.pseudo as user_pseudo',
                    'users.email as user_email',
                    DB::raw('CONCAT(transactions.cauris_amount, " cauris (", transactions.fcfa_amount, " FCFA)") as formatted_amount')
                )
                ->orderBy('transactions.created_at', 'desc')
                ->limit(10)
                ->get();

            return response()->json([
                'success' => true,
                'data' => [
                    'users' => [
                        'total' => $totalUsers,
                        'active' => $activeUsers,
                    ],
                    'rooms' => [
                        'total' => $totalRooms,
                        'active' => $activeRooms,
                        'waiting' => $waitingRooms,
                        'finished' => $finishedRooms,
                        'cancelled' => $cancelledRooms,
                    ],
                    'games' => [
                        'total' => $totalGames,
                    ],
                    'cauris' => [
                        'in_circulation' => $totalCaurisInCirculation ?? 0,
                        'company_balance' => $companyBalance ?? 0,
                    ],
                    'transactions' => [
                        'total' => $totalTransactions,
                        'pending' => $pendingTransactions,
                        'validated' => $validatedTransactions,
                        'rejected' => $rejectedTransactions,
                        'total_deposits' => $totalDeposits ?? 0,
                        'total_withdrawals' => $totalWithdrawals ?? 0,
                    ],
                    'recent_transactions' => $recentTransactions,
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
     * Corriger les séquences/AUTO_INCREMENT de la base de données
     */
    public function fixDatabaseSequences(Request $request)
    {
        try {
            // Permettre l'accès soit par un token secret dans l'URL, soit par authentification admin
            $secretToken = $request->get('token');
            $isValidToken = ($secretToken === 'cauris_fix_2024'); // Token temporaire pour débloquer l'utilisateur
            
            if (!$isValidToken) {
                if (!$request->user()) {
                    return response()->json(['success' => false, 'message' => 'Non authentifié'], 401);
                }
                $this->checkAdminAccess($request->user());
            }

            $schemaReport = [];

            // ÉTAPE 1: Gérer la colonne 'role' manquante ou migrer les administrateurs
            if (DB::getDriverName() === 'mysql') {
                if (!Schema::hasColumn('users', 'role')) {
                    DB::statement("ALTER TABLE users ADD COLUMN role ENUM('superadmin', 'admin', 'manager', 'user') NOT NULL DEFAULT 'user' AFTER company_balance");
                    $schemaReport['role_column'] = 'Created';
                }

                // Migrer TOUS les is_admin vers role
                if (Schema::hasColumn('users', 'is_admin')) {
                    $adminCount = DB::table('users')->where('is_admin', 1)->where('role', 'user')->update(['role' => 'admin']);
                    if ($adminCount > 0) {
                        $schemaReport['admins_migrated'] = $adminCount;
                    }
                }

                // S'assurer que le superAdmin a le bon rôle (insensible à la casse)
                DB::table('users')
                    ->where('pseudo', 'LIKE', 'superadmin')
                    ->orWhere('email', 'superadmin@cauris.com')
                    ->update(['role' => 'superadmin']);
            }
            
            // ÉTAPE 2: Appel de la commande artisan pour corriger les séquences/AUTO_INCREMENT
            Artisan::call('db:fix-sequences');
            $output = Artisan::output();
            
            // Parser le JSON à la fin de l'output
            $lines = explode("\n", trim($output));
            $lastLine = end($lines);
            $details = json_decode($lastLine, true);

            // Diagnostics sur les données utilisateurs
            $usersSnapshot = User::select('user_id', 'pseudo', 'email', 'cauris_balance', 'role', 'is_admin')
                ->orderBy('user_id', 'asc')
                ->limit(10)
                ->get();

            // Simuler un appel à profile() pour Alpha
            $alpha = User::where('pseudo', 'Alpha')->first();
            $alphaProfile = null;
            if ($alpha) {
                $alphaProfile = [
                    'user_id' => $alpha->user_id,
                    'pseudo' => $alpha->pseudo,
                    'cauris_balance' => (int)$alpha->cauris_balance,
                    'role' => $alpha->role,
                    'stats' => $this->calculateUserStats($alpha->user_id)
                ];
            }

            // Diagnostics système
            $diagnostics = [
                'db_driver' => DB::getDriverName(),
                'db_connection' => config('database.default'),
                'user_id' => $request->user() ? $request->user()->user_id : 'via_token',
                'user_role' => $request->user() ? $request->user()->role : 'N/A',
                'schema_updates' => $schemaReport,
                'users_sample' => $usersSnapshot,
                'alpha_profile_test' => $alphaProfile
            ];

            return response()->json([
                'success' => true,
                'message' => 'Système analysé et corrigé',
                'diagnostics' => $diagnostics,
                'details' => $details,
                'raw_output' => $output
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur critique : ' . $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ], 500);
        }
    }

    /**
     * Liste des administrateurs (Super Admin uniquement)
     */
    public function getAdmins(Request $request)
    {
        try {
            $this->checkSuperAdminAccess($request->user());
            
            $perPage = $request->get('per_page', 20);
            // Récupérer tous les admins (superadmin, admin, manager) sauf les users
            $admins = User::whereIn('role', ['superadmin', 'admin', 'manager'])
                ->orderBy('created_at', 'desc')
                ->paginate($perPage);
            
            return response()->json([
                'success' => true,
                'data' => $admins
            ], 200);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Liste des utilisateurs
     */
    public function users(Request $request)
    {
        try {
            $this->checkAdminAccess($request->user());
            $perPage = $request->get('per_page', 20);
            $search = $request->get('search');
            $currentUser = $request->user();

            $query = User::query();

            // Déterminer le rôle de l'utilisateur actuel
            $currentRole = $currentUser->role ?? (
                (($currentUser->pseudo === 'superAdmin' || $currentUser->email === 'superadmin@cauris.com') ? 'superadmin' :
                (($currentUser->pseudo === 'manageradmin' || $currentUser->email === 'manageradmin@cauris.com') ? 'manager' : 'user'))
            );
            
            $isSuperAdmin = $currentRole === 'superadmin';
            
            // ✅ Exclure TOUS les admins/managers/superadmins de la liste des utilisateurs
            // Seuls les utilisateurs normaux sont affichés dans cette liste
            $query->where(function($q) {
                $q->where('role', 'user')
                  ->orWhereNull('role'); // Compatibilité avec l'ancien système (null = user normal)
            });

            if ($search) {
                $query->where(function($q) use ($search) {
                    $q->where('pseudo', 'LIKE', "%{$search}%")
                      ->orWhere('email', 'LIKE', "%{$search}%");
                });
            }

            $users = $query->orderBy('user_id', 'asc')->paginate($perPage);

            return response()->json([
                'success' => true,
                'data' => $users,
                'current_user_id' => $currentUser->user_id,
                'is_super_admin' => $isSuperAdmin
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Supprimer un utilisateur
     */
    public function deleteUser(Request $request, $userId)
    {
        try {
            $this->checkAdminAccess($request->user());
            $user = User::find($userId);

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Utilisateur non trouvé'
                ], 404);
            }

            // Ne pas permettre de supprimer les admins
            // Vérifier si c'est un admin/manager/superadmin
            $targetUserRole = $user->role ?? (
                (($user->pseudo === 'superAdmin' || $user->email === 'superadmin@cauris.com') ? 'superadmin' :
                (($user->pseudo === 'manageradmin' || $user->email === 'manageradmin@cauris.com') ? 'manager' : 'user'))
            );
            
            if (in_array($targetUserRole, ['superadmin', 'admin', 'manager'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Impossible de supprimer un compte administrateur'
                ], 403);
            }

            // Sauvegarder les informations avant suppression pour l'email
            $userPseudo = $user->pseudo;
            $userEmail = $user->email;
            $reason = $request->input('reason', null);

            $user->delete();

            // Envoyer un email de notification
            try {
                Mail::to($userEmail)->send(new AccountDeletedEmail($userPseudo, $reason));
            } catch (\Exception $e) {
                // Logger l'erreur mais ne pas bloquer la suppression
                \Log::error("Erreur lors de l'envoi de l'email de suppression de compte: " . $e->getMessage());
            }

            // Enregistrer dans les logs
            $this->logAction($request->user()->user_id, 'delete_user', 'user', $userId, [
                'user_id' => $userId,
                'pseudo' => $userPseudo
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Utilisateur supprimé avec succès'
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Détails d'un utilisateur
     */
    public function userDetails(Request $request, $userId)
    {
        try {
            $this->checkAdminAccess($request->user());
            $user = User::with(['scores', 'rooms', 'friendships'])->find($userId);

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Utilisateur non trouvé'
                ], 404);
            }

            // Calculer les statistiques
            $totalGames = \App\Models\Game::whereHas('room', function($query) use ($userId) {
                $query->whereHas('players', function($q) use ($userId) {
                    $q->where('user_id', $userId);
                });
            })->count();

            $gamesWon = \App\Models\Game::where('winner_id', $userId)->count();

            return response()->json([
                'success' => true,
                'data' => [
                    'user' => $user,
                    'stats' => [
                        'total_games' => $totalGames,
                        'games_won' => $gamesWon,
                        'games_lost' => $totalGames - $gamesWon,
                    ]
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
     * Activer/Désactiver un utilisateur
     */
    public function toggleUserStatus(Request $request, $userId)
    {
        try {
            $this->checkAdminAccess($request->user());
            $user = User::find($userId);

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Utilisateur non trouvé'
                ], 404);
            }

            $wasActive = $user->is_active;
            $user->is_active = !$user->is_active;
            $user->save();

            // Envoyer un email de notification selon le nouveau statut
            try {
                if ($user->is_active && !$wasActive) {
                    // Compte réactivé (débanni)
                    Mail::to($user->email)->send(new AccountUnbannedEmail($user->pseudo));
                } elseif (!$user->is_active && $wasActive) {
                    // Compte suspendu (banni)
                    $reason = $request->input('reason', null);
                    Mail::to($user->email)->send(new AccountBannedEmail($user->pseudo, $reason));
                }
            } catch (\Exception $e) {
                // Logger l'erreur mais ne pas bloquer le changement de statut
                \Log::error("Erreur lors de l'envoi de l'email de changement de statut: " . $e->getMessage());
            }

            // Enregistrer dans les logs
            $this->logAction($request->user()->user_id, 'toggle_user_status', 'user', $userId, [
                'user_id' => $userId,
                'new_status' => $user->is_active
            ]);

            return response()->json([
                'success' => true,
                'message' => $user->is_active ? 'Utilisateur activé' : 'Utilisateur désactivé',
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
     * Liste des salles
     */
    public function rooms(Request $request)
    {
        try {
            $this->checkAdminAccess($request->user());
            $perPage = $request->get('per_page', 20);
            $status = $request->get('status');

            $query = Room::with(['creator', 'players', 'games.winner']);

            if ($status) {
                $query->where('status', $status);
            }

            $rooms = $query->orderBy('created_at', 'desc')->paginate($perPage);

            // Ajouter le gagnant pour chaque salle terminée
            $rooms->getCollection()->transform(function ($room) {
                if ($room->status === 'finished' && $room->games && $room->games->isNotEmpty()) {
                    // Prendre le dernier jeu terminé de la salle
                    $lastGame = $room->games->where('finished_at', '!=', null)->sortByDesc('finished_at')->first();
                    if ($lastGame && $lastGame->winner) {
                        $room->winner_pseudo = $lastGame->winner->pseudo;
                    } else {
                        $room->winner_pseudo = null;
                    }
                } else {
                    $room->winner_pseudo = null;
                }
                return $room;
            });

            return response()->json([
                'success' => true,
                'data' => $rooms
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Détails d'une salle
     */
    public function roomDetails(Request $request, $roomId)
    {
        try {
            $this->checkAdminAccess($request->user());
            $room = Room::with(['creator', 'players.user'])
                ->with('games')
                ->find($roomId);

            if (!$room) {
                return response()->json([
                    'success' => false,
                    'message' => 'Salle non trouvée'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => $room
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Logs d'administration
     */
    public function logs(Request $request)
    {
        try {
            $this->checkAdminAccess($request->user());
            $perPage = $request->get('per_page', 50);

            $logs = AdminLog::with('adminUser')
                ->orderBy('created_at', 'desc')
                ->paginate($perPage);

            return response()->json([
                'success' => true,
                'data' => $logs
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Calculer les bénéfices de l'entreprise (Super Admin uniquement)
     * 
     * Paramètres de requête:
     * - date_from: Date de début (format Y-m-d)
     * - date_to: Date de fin (format Y-m-d)
     * - room_status: Filtrer par statut de salle (waiting, cancelled, etc.)
     * - room_id: Filtrer par ID de salle spécifique
     */
    public function benefits(Request $request)
    {
        try {
            $this->checkSuperAdminAccess($request->user());

            // Paramètres de filtrage
            $dateFrom = $request->get('date_from');
            $dateTo = $request->get('date_to');
            $roomStatus = $request->get('room_status');
            $roomId = $request->get('room_id');

            // 1. Bénéfices des 10% sur les jeux terminés en mode humains
            // Format des notes: "Gain partie salon #X (Y cauris - 90% gagnant, 10% entreprise)"
            // Y = winnerAmount = 90% du total_pot, donc 10% = Y / 9
            $completedGamesQuery = DB::table('transactions')
                ->where('notes', 'LIKE', '%Gain partie%')
                ->where('notes', 'NOT LIKE', '%bot%')
                ->where('type', 'depot');

            // Appliquer les filtres de date
            if ($dateFrom) {
                $completedGamesQuery->whereDate('created_at', '>=', $dateFrom);
            }
            if ($dateTo) {
                $completedGamesQuery->whereDate('created_at', '<=', $dateTo);
            }

            // Filtrer par room_id si spécifié
            if ($roomId) {
                $completedGamesQuery->where('notes', 'LIKE', "%salon #{$roomId}%");
            }

            $completedGamesTransactions = $completedGamesQuery->get();

            $benefitsFromCompletedGames = $completedGamesTransactions->sum(function($tx) {
                // Extraire le montant gagné (90% du pot)
                // Format: "Gain partie salon #X (Y cauris - 90% gagnant, 10% entreprise)"
                if (preg_match('/\((\d+)\s+cauris/', $tx->notes, $matches)) {
                    $winnerAmount = (int)$matches[1]; // 90% du pot
                    // Calculer 10% du pot = winnerAmount / 9
                    return $winnerAmount / 9;
                }
                return 0;
            });

            // 2. Bénéfices des salles abandonnées (jeu non terminé, joueurs partis)
            // Trouver toutes les salles qui ont des transactions de mise mais pas de transaction de gain
            $betsQuery = DB::table('transactions')
                ->where('notes', 'LIKE', 'Mise salon #%')
                ->where('status', 'valide');

            // Appliquer les filtres de date
            if ($dateFrom) {
                $betsQuery->whereDate('created_at', '>=', $dateFrom);
            }
            if ($dateTo) {
                $betsQuery->whereDate('created_at', '<=', $dateTo);
            }

            $roomsWithBets = $betsQuery
                ->select(DB::raw('SUBSTRING_INDEX(SUBSTRING_INDEX(notes, "#", -1), " ", 1) as room_id'))
                ->distinct()
                ->pluck('room_id')
                ->map(function($id) {
                    return (int)$id;
                })
                ->toArray();

            // Trouver les salles qui ont des gains (jeux terminés)
            $roomsWithGains = DB::table('transactions')
                ->where('notes', 'LIKE', 'Gain partie salon #%')
                ->select(DB::raw('SUBSTRING_INDEX(SUBSTRING_INDEX(notes, "#", -1), " ", 1) as room_id'))
                ->distinct()
                ->pluck('room_id')
                ->map(function($id) {
                    return (int)$id;
                })
                ->toArray();

            // Les salles abandonnées sont celles qui ont des mises mais pas de gains
            $abandonedRoomIds = array_diff($roomsWithBets, $roomsWithGains);

            $benefitsFromAbandonedRooms = 0;
            $abandonedRoomsDetails = [];

            foreach ($abandonedRoomIds as $roomId) {
                // Filtrer par room_id si spécifié
                if ($roomIdFilter = $request->get('room_id')) {
                    if ($roomId != $roomIdFilter) {
                        continue;
                    }
                }

                // Récupérer les informations de la salle
                $room = Room::find($roomId);
                if (!$room) {
                    continue;
                }

                // Filtrer par statut de salle si spécifié
                if ($roomStatus && $room->status !== $roomStatus) {
                    continue;
                }

                // Compter les transactions de mise pour cette salle et calculer le total
                $betsQuery = DB::table('transactions')
                    ->where('notes', 'LIKE', "Mise salon #{$roomId}%")
                    ->where('status', 'valide');

                // Appliquer les filtres de date
                if ($dateFrom) {
                    $betsQuery->whereDate('created_at', '>=', $dateFrom);
                }
                if ($dateTo) {
                    $betsQuery->whereDate('created_at', '<=', $dateTo);
                }

                $bets = $betsQuery->get();

                $betsCount = $bets->count();
                // Le total des mises est la somme des montants débités, pas minimum_bet * count
                $totalBets = $bets->sum('cauris_amount');

                if ($betsCount > 0 && $totalBets > 0) {
                    $benefitsFromAbandonedRooms += $totalBets;
                    $abandonedRoomsDetails[] = [
                        'room_id' => $room->room_id,
                        'room_code' => $room->room_code,
                        'status' => $room->status,
                        'minimum_bet' => $room->minimum_bet,
                        'players_count' => $betsCount,
                        'total_bets' => $totalBets,
                        'created_at' => $room->created_at
                    ];
                }
            }

            // Trier par date de création (plus récentes en premier)
            usort($abandonedRoomsDetails, function($a, $b) {
                return strtotime($b['created_at']) - strtotime($a['created_at']);
            });

            // Total des bénéfices
            $totalBenefits = $benefitsFromCompletedGames + $benefitsFromAbandonedRooms;

            // Compter les jeux terminés
            $completedGamesCount = DB::table('transactions')
                ->where('notes', 'LIKE', '%Gain partie%')
                ->where('notes', 'NOT LIKE', '%bot%')
                ->count();

            return response()->json([
                'success' => true,
                'data' => [
                    'total_benefits' => $totalBenefits,
                    'benefits_from_completed_games' => $benefitsFromCompletedGames,
                    'benefits_from_abandoned_rooms' => $benefitsFromAbandonedRooms,
                    'completed_games_count' => $completedGamesCount,
                    'abandoned_rooms_count' => count($abandonedRoomsDetails),
                    'abandoned_rooms_details' => $abandonedRoomsDetails
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
     * Enregistrer une action dans les logs
     */
    private function logAction($adminUserId, $action, $targetType, $targetId, $details = null)
    {
        AdminLog::create([
            'admin_user_id' => $adminUserId,
            'action' => $action,
            'target_type' => $targetType,
            'target_id' => $targetId,
            'details' => json_encode($details),
            'ip_address' => request()->ip(),
            'created_at' => now(),
        ]);
    }

    /**
     * Créer un administrateur (Super Admin uniquement)
     */
    public function createAdmin(Request $request)
    {
        try {
            $this->checkSuperAdminAccess($request->user());
            
            $validator = \Validator::make($request->all(), [
                'pseudo' => 'required|string|max:50|unique:users',
                'email' => 'required|string|email|max:100|unique:users',
                'password' => 'required|string|min:8',
                'role' => 'required|in:admin,manager',
                'is_active' => 'boolean',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Erreur de validation',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Créer l'utilisateur avec le rôle spécifié
            $user = User::create([
                'pseudo' => $request->pseudo,
                'email' => $request->email,
                'password_hash' => \Hash::make($request->password),
                'role' => $request->role, // 'admin' ou 'manager'
                'is_active' => $request->input('is_active', true),
                'avatar' => '👤',
                'theme_preference' => 'light',
            ]);

            // Créer les paramètres par défaut
            $user->settings()->create([
                'language' => 'fr',
                'theme_mode' => 'light',
                'notifications_enabled' => true,
                'sound_enabled' => true,
                'vibration_enabled' => true,
            ]);

            // Enregistrer dans les logs
            $this->logAction($request->user()->user_id, 'create_admin', 'user', $user->user_id, [
                'pseudo' => $user->pseudo,
                'email' => $user->email,
                'role' => $user->role,
            ]);

            return response()->json([
                'success' => true,
                'message' => ucfirst($request->role) . ' créé avec succès',
                'data' => [
                    'user' => [
                        'user_id' => $user->user_id,
                        'pseudo' => $user->pseudo,
                        'email' => $user->email,
                        'role' => $user->role,
                        'is_active' => $user->is_active,
                    ]
                ]
            ], 201);

        } catch (\Exception $e) {
            \Log::error('Create admin error', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la création: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Modifier un administrateur (Super Admin uniquement)
     */
    public function updateAdmin(Request $request, $userId)
    {
        try {
            $this->checkSuperAdminAccess($request->user());
            
            $user = User::find($userId);
            
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Administrateur non trouvé'
                ], 404);
            }

            // Vérifier que ce n'est pas le superadmin
            $targetRole = $user->role ?? (
                (($user->pseudo === 'superAdmin' || $user->email === 'superadmin@cauris.com') ? 'superadmin' : 'user')
            );
            
            if ($targetRole === 'superadmin') {
                return response()->json([
                    'success' => false,
                    'message' => 'Impossible de modifier le super administrateur'
                ], 403);
            }

            $validator = \Validator::make($request->all(), [
                'pseudo' => 'sometimes|required|string|max:50|unique:users,pseudo,' . $userId . ',user_id',
                'email' => 'sometimes|required|string|email|max:100|unique:users,email,' . $userId . ',user_id',
                'password' => 'sometimes|string|min:8',
                'role' => 'sometimes|required|in:admin,manager',
                'is_active' => 'boolean',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Erreur de validation',
                    'errors' => $validator->errors()
                ], 422);
            }

            $updateData = [];
            
            if ($request->has('pseudo')) {
                $updateData['pseudo'] = $request->pseudo;
            }
            
            if ($request->has('email')) {
                $updateData['email'] = $request->email;
            }
            
            if ($request->has('password') && $request->password) {
                $updateData['password_hash'] = \Hash::make($request->password);
            }
            
            if ($request->has('role')) {
                $updateData['role'] = $request->role;
            }
            
            if ($request->has('is_active')) {
                $updateData['is_active'] = $request->is_active;
            }

            $user->update($updateData);

            // Enregistrer dans les logs
            $this->logAction($request->user()->user_id, 'update_admin', 'user', $userId, [
                'pseudo' => $user->pseudo,
                'role' => $user->role,
                'changes' => $updateData,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Administrateur modifié avec succès',
                'data' => [
                    'user' => [
                        'user_id' => $user->user_id,
                        'pseudo' => $user->pseudo,
                        'email' => $user->email,
                        'role' => $user->role,
                        'is_active' => $user->is_active,
                    ]
                ]
            ], 200);

        } catch (\Exception $e) {
            \Log::error('Update admin error', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la modification: ' . $e->getMessage()
            ], 500);
        }
    }
}
