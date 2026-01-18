<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;

class TransactionController extends Controller
{
    /**
     * Lister toutes les transactions avec détails complets
     */
    public function getAllTransactions(Request $request)
    {
        try {
            $status = $request->get('status');
            $type = $request->get('type');
            $perPage = $request->get('per_page', 20);
            $pseudo = $request->get('pseudo');
            $dateFrom = $request->get('date_from');
            $dateTo = $request->get('date_to');

                $query = DB::table('transactions')
                    ->join('users', 'transactions.user_id', '=', 'users.user_id')
                    ->leftJoin('users as validator', 'transactions.validated_by', '=', 'validator.user_id')
                    ->select(
                        'transactions.*',
                        'users.pseudo as user_pseudo',  // ✅ Pseudo en premier
                        'users.email as user_email',
                        'users.cauris_balance as user_current_balance',
                        'validator.pseudo as validator_pseudo',
                        DB::raw('CONCAT(transactions.cauris_amount, " cauris (", transactions.fcfa_amount, " FCFA)") as formatted_amount')
                    );

            // Filtrer par statut
            if ($status && $status !== 'all') {
                $query->where('transactions.status', $status);
            }

            // Filtrer par type
            if ($type && $type !== 'all') {
                if ($type === 'mise') {
                    // Les mises sont identifiées par les notes contenant "Mise salon"
                    $query->where('transactions.notes', 'LIKE', '%Mise salon%');
                } else {
                    $query->where('transactions.type', $type);
                }
            }

            // Rechercher par pseudo
            if ($pseudo) {
                $query->where('users.pseudo', 'LIKE', "%{$pseudo}%");
            }

            // Filtrer par date de début
            if ($dateFrom) {
                $query->whereDate('transactions.created_at', '>=', $dateFrom);
            }

            // Filtrer par date de fin
            if ($dateTo) {
                $query->whereDate('transactions.created_at', '<=', $dateTo);
            }

            $transactions = $query->orderBy('transactions.created_at', 'desc')->paginate($perPage);

            return response()->json([
                'success' => true,
                'data' => $transactions
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Détails d'une transaction spécifique
     */
    public function getTransactionDetails($transactionId)
    {
        try {
            $transaction = DB::table('transactions')
                ->join('users', 'transactions.user_id', '=', 'users.user_id')
                ->leftJoin('users as validator', 'transactions.validated_by', '=', 'validator.user_id')
                ->select(
                    'transactions.*',
                    'users.pseudo as user_pseudo',
                    'users.email as user_email',
                    'users.cauris_balance as user_current_balance',
                    'validator.pseudo as validator_pseudo',
                    DB::raw('CONCAT(transactions.cauris_amount, " cauris (", transactions.fcfa_amount, " FCFA)") as formatted_amount')
                )
                ->where('transactions.transaction_id', $transactionId)
                ->first();

            if (!$transaction) {
                return response()->json([
                    'success' => false,
                    'message' => 'Transaction non trouvée'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => $transaction
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Valider une transaction (depot ou retrait)
     */
    public function validateTransaction(Request $request, $transactionId)
    {
        try {
            $adminId = $request->user()->user_id;
            
            $transaction = DB::table('transactions')
                ->join('users', 'transactions.user_id', '=', 'users.user_id')
                ->select('transactions.*', 'users.pseudo', 'users.email')
                ->where('transactions.transaction_id', $transactionId)
                ->first();

            if (!$transaction) {
                return response()->json([
                    'success' => false,
                    'message' => 'Transaction non trouvée'
                ], 404);
            }

            if ($transaction->status !== 'en_attente') {
                return response()->json([
                    'success' => false,
                    'message' => 'Transaction déjà traitée'
                ], 400);
            }

            DB::beginTransaction();

            // Mettre à jour la transaction
            DB::table('transactions')
                ->where('transaction_id', $transactionId)
                ->update([
                    'status' => 'valide',
                    'validated_by' => $adminId,
                    'validated_at' => now(),
                ]);

            // Mettre à jour le solde de l'utilisateur et de l'entreprise
            if ($transaction->type === 'depot') {
                // Pour un dépôt validé :
                // 1. Débiter le compte entreprise (super admin uniquement)
                $companyUser = User::where('role', 'superadmin')->orderBy('user_id', 'asc')->first();
                if ($companyUser) {
                    $companyUser->decrement('company_balance', $transaction->cauris_amount);
                }
                
                // 2. Créditer le compte utilisateur
                DB::table('users')
                    ->where('user_id', $transaction->user_id)
                    ->increment('cauris_balance', $transaction->cauris_amount);
            } elseif ($transaction->type === 'retrait') {
                // Pour un retrait validé :
                // 1. Débiter le compte utilisateur
                DB::table('users')
                    ->where('user_id', $transaction->user_id)
                    ->decrement('cauris_balance', $transaction->cauris_amount);
                
                // 2. Créditer le compte entreprise (super admin uniquement)
                $companyUser = User::where('role', 'superadmin')->orderBy('user_id', 'asc')->first();
                if ($companyUser) {
                    $companyUser->increment('company_balance', $transaction->cauris_amount);
                }
            }

            DB::commit();

            // Envoyer email de confirmation
            try {
                Mail::to($transaction->email)->send(
                    new \App\Mail\TransactionValidatedEmail(
                        $transaction->type,
                        $transaction->cauris_amount,
                        $transaction->fcfa_amount,
                        $transaction->pseudo
                    )
                );
            } catch (\Exception $mailException) {
                Log::error('Erreur envoi email validation: ' . $mailException->getMessage());
            }

            return response()->json([
                'success' => true,
                'message' => 'Transaction validée avec succès'
            ], 200);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Erreur: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Rejeter une transaction
     */
    public function rejectTransaction(Request $request, $transactionId)
    {
        try {
            $adminId = $request->user()->user_id;
            $notes = $request->get('notes', 'Transaction rejetée');

            $transaction = DB::table('transactions')
                ->join('users', 'transactions.user_id', '=', 'users.user_id')
                ->select('transactions.*', 'users.pseudo', 'users.email')
                ->where('transactions.transaction_id', $transactionId)
                ->first();

            if (!$transaction) {
                return response()->json([
                    'success' => false,
                    'message' => 'Transaction non trouvée'
                ], 404);
            }

            if ($transaction->status !== 'en_attente') {
                return response()->json([
                    'success' => false,
                    'message' => 'Transaction déjà traitée'
                ], 400);
            }

            // Mettre à jour la transaction
            DB::table('transactions')
                ->where('transaction_id', $transactionId)
                ->update([
                    'status' => 'rejete',
                    'validated_by' => $adminId,
                    'validated_at' => now(),
                    'notes' => $notes,
                ]);

            // Envoyer email de rejet
            try {
                Mail::to($transaction->email)->send(
                    new \App\Mail\TransactionRejectedEmail(
                        $transaction->type,
                        $transaction->cauris_amount,
                        $transaction->fcfa_amount,
                        $transaction->pseudo,
                        $notes
                    )
                );
            } catch (\Exception $mailException) {
                Log::error('Erreur envoi email rejet: ' . $mailException->getMessage());
            }

            return response()->json([
                'success' => true,
                'message' => 'Transaction rejetée'
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Statistiques des transactions
     */
    public function getTransactionStats()
    {
        try {
            $stats = [
                'total' => DB::table('transactions')->count(),
                'pending' => DB::table('transactions')->where('status', 'en_attente')->count(),
                'validated' => DB::table('transactions')->where('status', 'valide')->count(),
                'rejected' => DB::table('transactions')->where('status', 'rejete')->count(),
                'total_depot' => DB::table('transactions')->where('type', 'depot')->where('status', 'valide')->sum('fcfa_amount'),
                'total_retrait' => DB::table('transactions')->where('type', 'retrait')->where('status', 'valide')->sum('fcfa_amount'),
            ];

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
}

