<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use App\Models\User;
use App\Mail\TransactionValidatedEmail;
use App\Mail\TransactionRejectedEmail;
use FedaPay\FedaPay;
use FedaPay\Transaction;
use FedaPay\Webhook;
use FedaPay\Error\SignatureVerification;

/**
 * Contrôleur pour gérer les paiements et les soldes
 * 
 * Fonctionnalités :
 * - Vérifier les soldes avant de créer/rejoindre un salon
 * - Débiter les mises des joueurs
 * - Créditer le compte entreprise
 * - Gérer le compte entreprise
 */
class PaymentController extends Controller
{
    /**
     * Obtenir le solde de l'utilisateur connecté
     */
    public function getBalance(Request $request)
    {
        try {
            $user = $request->user();
            
            return $this->apiResponse(true, 'Solde récupéré', [
                'cauris_balance' => (int)($user->cauris_balance ?? 0),
                'balance' => (int)($user->cauris_balance ?? 0),
                'solde' => (int)($user->cauris_balance ?? 0),
                'company_balance' => (int)$this->getCompanyBalance(),
            ], 200, false);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Vérifier si l'utilisateur a assez d'argent pour un montant donné
     */
    public function checkBalance(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'required_amount' => 'required|integer|min:1',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Erreur de validation'
                ], 422);
            }

            $user = $request->user();
            $requiredAmount = $request->required_amount;
            $balance = $user->cauris_balance ?? 0;
            $hasEnough = $balance >= $requiredAmount;

            return $this->apiResponse(true, 'Vérification du solde effectuée', [
                'balance' => $balance,
                'required_amount' => $requiredAmount,
                'has_enough' => $hasEnough,
                'shortage' => max(0, $requiredAmount - $balance),
            ], 200, false);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Débiter le montant pour créer/rejoindre un salon
     */
    public function debitRoomBet(Request $request)
    {
        DB::beginTransaction();
        
        try {
            $validator = Validator::make($request->all(), [
                'amount' => 'required|integer|min:1',
                'room_id' => 'required|integer',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Erreur de validation'
                ], 422);
            }

            $user = $request->user();
            $amount = $request->amount;
            $roomId = $request->room_id;

            // Vérifier que l'utilisateur a assez d'argent
            if ($user->cauris_balance < $amount) {
                return response()->json([
                    'success' => false,
                    'message' => 'Solde insuffisant. Votre solde: ' . $user->cauris_balance . ', requis: ' . $amount
                ], 400);
            }

            // Débiter le montant du compte utilisateur
            $user->decrement('cauris_balance', $amount);

            // Créditer le compte entreprise
            $this->creditCompanyBalance($amount);

            // Enregistrer la transaction
            DB::table('transactions')->insert([
                'user_id' => $user->user_id,
                'type' => 'retrait',
                'cauris_amount' => $amount,
                'fcfa_amount' => $amount * 100, // Exemple : 10 cauris = 1000 FCFA
                'status' => 'valide',
                'notes' => "Mise salon #$roomId",
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::commit();

            return $this->apiResponse(true, 'Mise débitée avec succès', [
                'new_balance' => $user->cauris_balance,
                'amount_debited' => $amount,
            ], 200, false);

        } catch (\Exception $e) {
            DB::rollBack();
            
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors du débit: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Créditer le montant de la cagnotte au compte utilisateur (en cas d'annulation)
     */
    public function creditRoomBet(Request $request)
    {
        DB::beginTransaction();
        
        try {
            $validator = Validator::make($request->all(), [
                'amount' => 'required|integer|min:1',
                'room_id' => 'required|integer',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Erreur de validation'
                ], 422);
            }

            $user = $request->user();
            $amount = $request->amount;

            // Créditer le montant au compte utilisateur
            $user->increment('cauris_balance', $amount);

            // Débiter le compte entreprise (remboursement)
            $this->debitCompanyBalance($amount);

            DB::commit();

            return $this->apiResponse(true, 'Montant crédité avec succès', [
                'new_balance' => $user->cauris_balance,
            ], 200, false);

        } catch (\Exception $e) {
            DB::rollBack();
            
            return response()->json([
                'success' => false,
                'message' => 'Erreur: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtenir le solde de l'entreprise (super admin)
     */
    private function getCompanyBalance()
    {
        // Le solde entreprise est stocké sur le super admin uniquement
        $companyUser = User::where('role', 'superadmin')->orderBy('user_id', 'asc')->first();
        return $companyUser ? ($companyUser->company_balance ?? 0) : 0;
    }

    /**
     * Créditer le compte entreprise (super admin uniquement)
     */
    private function creditCompanyBalance($amount)
    {
        $companyUser = User::where('role', 'superadmin')->orderBy('user_id', 'asc')->first();
        if ($companyUser) {
            $companyUser->increment('company_balance', $amount);
        }
    }

    /**
     * Débiter le compte entreprise (super admin uniquement)
     */
    private function debitCompanyBalance($amount)
    {
        $companyUser = User::where('role', 'superadmin')->orderBy('user_id', 'asc')->first();
        if ($companyUser && ($companyUser->company_balance ?? 0) >= $amount) {
            $companyUser->decrement('company_balance', $amount);
        }
    }

    /**
     * Liste des transactions de l'utilisateur connecté
     * Exclut les transactions de mise de salon et de gains de partie
     * Limite aux 20 transactions les plus récentes
     */
    public function transactions(Request $request)
    {
        try {
            $user = $request->user();
            $limit = $request->get('limit', 20); // Par défaut 20 transactions récentes
            
            // Filtrer les transactions :
            // - Exclure celles avec notes contenant "Mise salon" (mises pour rejoindre un salon)
            // - Exclure celles avec notes contenant "Gain partie" (gains de partie)
            // - Ne garder que les dépôts et retraits initiés par l'utilisateur
            $items = DB::table('transactions')
                ->where('user_id', $user->user_id)
                ->where(function($query) {
                    $query->whereNull('notes')
                          ->orWhere(function($subQuery) {
                              $subQuery->where('notes', 'NOT LIKE', '%Mise salon%')
                                       ->where('notes', 'NOT LIKE', '%Gain partie%');
                          });
                })
                ->orderByDesc('created_at')
                ->limit($limit)
                ->get();
            
            return $this->apiResponse(true, 'Transactions récupérées', $items);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Créer une demande de dépôt via FedaPay (achat de cauris)
     * Champs: amount_fcfa (int), phone_number (string)
     * 
     * Cette méthode initie un paiement FedaPay et crée une transaction en attente
     */
    public function deposit(Request $request)
    {
        DB::beginTransaction();
        
        try {
            $validator = Validator::make($request->all(), [
                'amount_fcfa' => 'required|integer|min:100',
                'phone_number' => 'required|string|max:20',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Erreur de validation: ' . $validator->errors()->first()
                ], 422);
            }

            $user = $request->user();
            $amountFcfa = (int) $request->amount_fcfa;
            $phoneNumber = $request->phone_number;
            
            // Conversion: 10 cauris = 1000 FCFA
            $cauris = (int) round(($amountFcfa / 1000) * 10);

            // Configurer FedaPay avec les clés API depuis .env
            $apiKey = env('FEDAPAY_API_KEY');
            $environment = env('FEDAPAY_ENVIRONMENT', 'sandbox'); // sandbox ou live
            
            if (!$apiKey) {
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'message' => 'Configuration FedaPay manquante'
                ], 500);
            }

            // Initialiser FedaPay
            FedaPay::setApiKey($apiKey);
            FedaPay::setEnvironment($environment);

            // Créer la transaction dans notre base de données d'abord
            $transactionId = DB::table('transactions')->insertGetId([
                'user_id' => $user->user_id,
                'type' => 'depot',
                'cauris_amount' => $cauris,
                'fcfa_amount' => $amountFcfa,
                'phone_number' => $phoneNumber,
                'status' => 'en_attente',
                'payment_method' => 'fedapay',
                'fedapay_status' => 'pending',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // Créer la transaction FedaPay
            // Utiliser FEDAPAY_WEBHOOK_URL si défini, sinon construire depuis APP_URL
            $callbackUrl = env('FEDAPAY_WEBHOOK_URL') 
                ? env('FEDAPAY_WEBHOOK_URL')
                : env('APP_URL') . '/api/payment/fedapay-webhook';
            
            $fedapayTransaction = Transaction::create([
                'description' => "Achat de $cauris cauris - $amountFcfa FCFA",
                'amount' => $amountFcfa,
                'currency' => ['iso' => 'XOF'],
                'callback_url' => $callbackUrl,
                'customer' => [
                    'firstname' => explode(' ', $user->pseudo ?? 'User')[0],
                    'lastname' => explode(' ', $user->pseudo ?? 'User')[1] ?? '',
                    'email' => $user->email,
                    'phone_number' => [
                        'number' => $phoneNumber,
                        'country' => 'bj' // Bénin par défaut, peut être ajusté
                    ]
                ],
                'metadata' => [
                    'transaction_id' => $transactionId,
                    'user_id' => $user->user_id,
                    'cauris_amount' => $cauris,
                ]
            ]);

            // Mettre à jour la transaction avec l'ID FedaPay
            DB::table('transactions')
                ->where('transaction_id', $transactionId)
                ->update([
                    'fedapay_transaction_id' => $fedapayTransaction->id,
                    'updated_at' => now(),
                ]);

            // Générer le token de paiement pour obtenir l'URL
            $paymentToken = $fedapayTransaction->generateToken();
            $paymentUrl = $paymentToken->url ?? null;

            DB::commit();

            return $this->apiResponse(true, 'Paiement FedaPay initié avec succès', [
                'transaction_id' => $transactionId,
                'fedapay_transaction_id' => $fedapayTransaction->id,
                'payment_url' => $paymentUrl,
                'payment_token' => $paymentToken->token ?? null,
                'cauris' => $cauris,
                'fcfa' => $amountFcfa,
            ], 201, false);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Erreur lors de la création du dépôt FedaPay: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de l\'initiation du paiement: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Webhook FedaPay pour recevoir les notifications de paiement
     * Cette route doit être accessible sans authentification (middleware exclu)
     */
    public function fedapayWebhook(Request $request)
    {
        try {
            // Récupérer le payload brut et la signature
            $payload = $request->getContent();
            $signature = $request->header('X-FedaPay-Signature');
            $webhookSecret = env('FEDAPAY_WEBHOOK_SECRET');
            
            // Vérifier la signature du webhook si le secret est configuré
            if ($webhookSecret && $signature) {
                try {
                    Webhook::constructEvent($payload, $signature, $webhookSecret);
                    Log::info('Webhook FedaPay: Signature vérifiée avec succès');
                } catch (SignatureVerification $e) {
                    Log::error('Webhook FedaPay: Signature invalide - ' . $e->getMessage());
                    return response()->json([
                        'success' => false,
                        'message' => 'Signature invalide'
                    ], 401);
                }
            } else {
                Log::warning('Webhook FedaPay: Secret non configuré, signature non vérifiée');
            }

            $event = json_decode($payload, true);
            Log::info('Webhook FedaPay reçu: ' . json_encode($event));

            // Récupérer l'ID de la transaction FedaPay
            $fedapayTransactionId = $event['transaction']['id'] ?? null;
            
            if (!$fedapayTransactionId) {
                return response()->json([
                    'success' => false,
                    'message' => 'Transaction ID manquant'
                ], 400);
            }

            // Récupérer le statut de la transaction
            $fedapayStatus = $event['transaction']['status'] ?? null;
            
            // Trouver la transaction dans notre base de données
            $transaction = DB::table('transactions')
                ->where('fedapay_transaction_id', $fedapayTransactionId)
                ->first();

            if (!$transaction) {
                Log::warning("Transaction FedaPay non trouvée: $fedapayTransactionId");
                return response()->json([
                    'success' => false,
                    'message' => 'Transaction non trouvée'
                ], 404);
            }

            // Si la transaction est déjà validée, ne rien faire
            if ($transaction->status === 'valide') {
                return response()->json([
                    'success' => true,
                    'message' => 'Transaction déjà validée'
                ], 200);
            }

            DB::beginTransaction();

            // Mettre à jour le statut FedaPay
            DB::table('transactions')
                ->where('transaction_id', $transaction->transaction_id)
                ->update([
                    'fedapay_status' => $fedapayStatus,
                    'updated_at' => now(),
                ]);

            // Si le paiement est approuvé ou transféré, valider automatiquement la transaction
            if ($fedapayStatus === 'approved' || $fedapayStatus === 'transferred') {
                // Créditer le compte utilisateur
                $user = User::find($transaction->user_id);
                if ($user) {
                    $user->increment('cauris_balance', $transaction->cauris_amount);
                    
                    // Débiter le compte entreprise
                    $this->debitCompanyBalance($transaction->cauris_amount);
                    
                    // Mettre à jour la transaction
                    DB::table('transactions')
                        ->where('transaction_id', $transaction->transaction_id)
                        ->update([
                            'status' => 'valide',
                            'validated_at' => now(),
                            'notes' => 'Paiement validé automatiquement via FedaPay',
                            'updated_at' => now(),
                        ]);
                    
                    // Envoyer l'email de confirmation
                    try {
                        Mail::to($user->email)->send(
                            new TransactionValidatedEmail(
                                $transaction->type,
                                $transaction->cauris_amount,
                                $transaction->fcfa_amount,
                                $user->pseudo
                            )
                        );
                        Log::info("Email de confirmation envoyé à {$user->email} pour la transaction #{$transaction->transaction_id}");
                    } catch (\Exception $e) {
                        Log::error("Erreur lors de l'envoi de l'email de confirmation: " . $e->getMessage());
                        // On continue même si l'email échoue
                    }
                }
            } elseif ($fedapayStatus === 'canceled' || $fedapayStatus === 'declined') {
                // Marquer la transaction comme rejetée
                DB::table('transactions')
                    ->where('transaction_id', $transaction->transaction_id)
                    ->update([
                        'status' => 'rejete',
                        'notes' => 'Paiement échoué ou annulé via FedaPay (statut: ' . $fedapayStatus . ')',
                        'updated_at' => now(),
                    ]);
                
                // Envoyer l'email de rejet
                $user = User::find($transaction->user_id);
                if ($user) {
                    try {
                        Mail::to($user->email)->send(
                            new TransactionRejectedEmail(
                                $transaction->type,
                                $transaction->cauris_amount,
                                $transaction->fcfa_amount,
                                $user->pseudo,
                                'Paiement échoué ou annulé via FedaPay'
                            )
                        );
                        Log::info("Email de rejet envoyé à {$user->email} pour la transaction #{$transaction->transaction_id}");
                    } catch (\Exception $e) {
                        Log::error("Erreur lors de l'envoi de l'email de rejet: " . $e->getMessage());
                        // On continue même si l'email échoue
                    }
                }
            }
            // Note: transaction.created est juste pour le suivi, pas besoin d'action particulière

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Webhook traité avec succès'
            ], 200);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Erreur lors du traitement du webhook FedaPay: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Erreur: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Créer une demande de retrait
     * Champs: cauris (int), beneficiary_name (string), phone (string)
     */
    public function withdraw(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'cauris' => 'required|integer|min:1',
                'beneficiary_name' => 'required|string|max:255',
                'phone' => 'required|string|max:20',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Erreur de validation'
                ], 422);
            }

            $user = $request->user();
            $cauris = (int) $request->cauris;

            if (($user->cauris_balance ?? 0) < $cauris) {
                return response()->json([
                    'success' => false,
                    'message' => 'Solde insuffisant',
                ], 400);
            }

            // Calcul FCFA
            $fcfa = (int) round(($cauris / 10) * 1000);

            DB::table('transactions')->insert([
                'user_id' => $user->user_id,
                'type' => 'retrait',
                'cauris_amount' => $cauris,
                'fcfa_amount' => $fcfa,
                'beneficiaire_name' => $request->beneficiary_name,
                'phone_number' => $request->phone,
                'status' => 'en_attente',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return $this->apiResponse(true, 'Demande de retrait enregistrée et en attente de validation', [
                'cauris' => $cauris,
                'fcfa' => $fcfa,
            ], 201, false);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Distribuer les gains au gagnant d'une partie
     * 
     * POST /api/payment/distribute-winnings
     * 
     * Champs:
     * - room_id (int) : ID du salon
     * - winner_name (string) : Pseudo du gagnant
     * - winner_amount (int) : Montant à créditer au gagnant (90% de la cagnotte)
     * - company_amount (int) : Montant à garder pour l'entreprise (10% de la cagnotte)
     * - is_replacement_bot (bool) : Si true, le gagnant est un bot remplaçant (100% à l'entreprise)
     * - total_pot (int) : Montant total de la cagnotte (mise minimale × 4)
     */
    public function distributeWinnings(Request $request)
    {
        DB::beginTransaction();
        
        try {
            $validator = Validator::make($request->all(), [
                'room_id' => 'required|integer',
                'winner_name' => 'required|string',
                'winner_amount' => 'required|integer|min:0',
                'company_amount' => 'required|integer|min:0',
                'is_replacement_bot' => 'required|boolean',
                'total_pot' => 'required|integer|min:1',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Erreur de validation',
                    'errors' => $validator->errors()
                ], 422);
            }

            $roomId = $request->room_id;
            $winnerName = $request->winner_name;
            $winnerAmount = $request->winner_amount;
            $companyAmount = $request->company_amount;
            $isReplacementBot = $request->is_replacement_bot;
            $totalPot = $request->total_pot;

            // Vérifier que les montants sont cohérents
            if (!$isReplacementBot && ($winnerAmount + $companyAmount) != $totalPot) {
                return response()->json([
                    'success' => false,
                    'message' => 'Incohérence: winner_amount + company_amount doit égaler total_pot'
                ], 400);
            }

            if ($isReplacementBot && $companyAmount != $totalPot) {
                return response()->json([
                    'success' => false,
                    'message' => 'Incohérence: si bot remplaçant, company_amount doit égaler total_pot'
                ], 400);
            }

            // Trouver le gagnant par son pseudo
            $winnerUser = User::where('pseudo', $winnerName)->first();
            
            if (!$winnerUser) {
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'message' => 'Gagnant non trouvé: ' . $winnerName
                ], 404);
            }

            // Créditer le gagnant uniquement si ce n'est pas un bot remplaçant
            if (!$isReplacementBot && $winnerAmount > 0) {
                $winnerUser->increment('cauris_balance', $winnerAmount);
            }

            // Débiter le compte entreprise (super admin)
            // Le montant débité est le montant total de la cagnotte (car il a été crédité lors des mises)
            // Maintenant on le débite pour le redistribuer
            $companyUser = User::where('role', 'superadmin')->orderBy('user_id', 'asc')->first();
            if ($companyUser) {
                // Le super admin doit avoir assez sur son compte pour payer les gains
                if (!$isReplacementBot) {
                    // Si c'est un joueur humain qui gagne: débiter le montant total de la cagnotte
                    // et le compte entreprise garde seulement les 10% déjà crédités
                    // En fait, on doit juste débiter l'entreprise du montant gagné par le joueur
                    // Car l'entreprise a déjà reçu 100% des mises (4 × mise minimale)
                    // Donc on doit débiter winnerAmount de l'entreprise pour créditer le gagnant
                    if (($companyUser->company_balance ?? 0) >= $winnerAmount) {
                        $companyUser->decrement('company_balance', $winnerAmount);
                    } else {
                        // Si l'entreprise n'a pas assez, on loggue un warning mais on continue
                        // En production, il faudrait gérer ce cas différemment
                        \Log::warning("Entreprise n'a pas assez de solde pour payer les gains. Solde: " . ($companyUser->company_balance ?? 0) . ", Requis: " . $winnerAmount);
                    }
                }
                // Si c'est un bot remplaçant, 100% reste à l'entreprise, pas besoin de débit/crédit
            }

            // Enregistrer la transaction
            DB::table('transactions')->insert([
                'user_id' => $winnerUser->user_id,
                'type' => 'depot', // Type "depot" car on crédite le joueur
                'cauris_amount' => $isReplacementBot ? 0 : $winnerAmount, // 0 si bot remplaçant
                'fcfa_amount' => 0, // Pas de conversion FCFA pour les gains
                'status' => 'valide', // Automatiquement validé
                'notes' => $isReplacementBot 
                    ? "Gain partie salon #$roomId (bot remplaçant - 100% à l'entreprise)" 
                    : "Gain partie salon #$roomId ($winnerAmount cauris - 90% gagnant, 10% entreprise)",
                'validated_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => $isReplacementBot 
                    ? 'Gains distribués (100% à l\'entreprise car bot remplaçant)'
                    : 'Gains distribués avec succès',
                'data' => [
                    'winner_name' => $winnerName,
                    'winner_amount' => $isReplacementBot ? 0 : $winnerAmount,
                    'company_amount' => $companyAmount,
                    'total_pot' => $totalPot,
                    'winner_new_balance' => $winnerUser->cauris_balance,
                ]
            ], 200);

        } catch (\Exception $e) {
            DB::rollBack();
            
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la distribution des gains: ' . $e->getMessage()
            ], 500);
        }
    }
}
