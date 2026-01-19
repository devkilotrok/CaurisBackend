<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes - CAURIS Backend
|--------------------------------------------------------------------------
*/

// =====================================================
// AUTHENTIFICATION (Sans middleware)
// =====================================================
Route::post('/login', [App\Http\Controllers\API\AuthController::class, 'login']);
Route::post('auth/register', [App\Http\Controllers\API\AuthController::class, 'register']);

// =====================================================
// CONTACT (Sans middleware - Public)
// =====================================================
Route::post('/contact', [App\Http\Controllers\API\ContactController::class, 'send']);
    
//Route::get('test',[App\Http\Controllers\API\AuthController::class,'test']);
Route::prefix('auth')->group(function () {
    Route::post('/logout', [App\Http\Controllers\API\AuthController::class, 'logout'])->middleware('auth:sanctum');
    // Vérification d'email et mot de passe oublié
    Route::post('/verify-email', [App\Http\Controllers\API\AuthController::class, 'verifyEmail']);
    Route::post('/resend-verification-code', [App\Http\Controllers\API\AuthController::class, 'resendVerificationCode']);
    Route::post('/forgot-password', [App\Http\Controllers\API\AuthController::class, 'forgotPassword']);
    Route::post('/verify-reset-code', [App\Http\Controllers\API\AuthController::class, 'verifyResetCode']);
    Route::post('/reset-password', [App\Http\Controllers\API\AuthController::class, 'resetPassword']);
});

// =====================================================
// UTILISATEURS (Requiert auth)
// =====================================================

Route::prefix('user')->middleware('auth:sanctum')->group(function () {
    Route::get('/profile', [App\Http\Controllers\API\UserController::class, 'profile']);
    Route::put('/profile/update', [App\Http\Controllers\API\UserController::class, 'updateProfile']);
    Route::get('/stats', [App\Http\Controllers\API\UserController::class, 'stats']);
    Route::prefix('search')->group(function () {
        Route::get('/', [App\Http\Controllers\API\UserController::class, 'search']);
    });
});

// =====================================================
// AMIS (Requiert auth)
// =====================================================

Route::prefix('friends')->middleware('auth:sanctum')->group(function () {
    Route::get('/', [App\Http\Controllers\API\FriendController::class, 'index']);
    Route::get('/requests', [App\Http\Controllers\API\FriendController::class, 'getRequests']);
    Route::get('/search', [App\Http\Controllers\API\FriendController::class, 'search']);
    Route::post('/request', [App\Http\Controllers\API\FriendController::class, 'sendRequest']);
    Route::post('/accept/{request_id}', [App\Http\Controllers\API\FriendController::class, 'accept']);
    Route::post('/reject/{request_id}', [App\Http\Controllers\API\FriendController::class, 'reject']);
    Route::delete('/remove/{friend_id}', [App\Http\Controllers\API\FriendController::class, 'remove']);
    Route::post('/invite-to-room', [App\Http\Controllers\API\FriendController::class, 'inviteToRoom']);
});

// =====================================================
// SALLES (Requiert auth)
// =====================================================

Route::prefix('rooms')->middleware('auth:sanctum')->group(function () {
    Route::get('/', [App\Http\Controllers\API\RoomController::class, 'index']);
    Route::post('/create', [App\Http\Controllers\API\RoomController::class, 'create']);
    Route::post('/join', [App\Http\Controllers\API\RoomController::class, 'join']);
    Route::post('/fill-bots', [App\Http\Controllers\API\RoomBotController::class, 'fill']);
    Route::get('/{room_id}', [App\Http\Controllers\API\RoomController::class, 'show']);
    Route::post('/{room_id}/start', [App\Http\Controllers\API\RoomController::class, 'start']);
    Route::post('/{room_id}/leave', [App\Http\Controllers\API\RoomController::class, 'leave']);
    Route::delete('/{room_id}', [App\Http\Controllers\API\RoomController::class, 'destroy']);
    
    // Routes pour le remplacement de joueurs par bots
    Route::post('/replace-player', [App\Http\Controllers\API\RoomController::class, 'replacePlayerWithBot']);
    Route::post('/restore-player', [App\Http\Controllers\API\RoomController::class, 'restorePlayer']);
    Route::post('/player-disconnected', [App\Http\Controllers\API\RoomController::class, 'notifyPlayerDisconnection']);
    Route::post('/player-reconnected', [App\Http\Controllers\API\RoomController::class, 'notifyPlayerReconnection']);
    Route::post('/check-exclusion', [App\Http\Controllers\API\RoomController::class, 'checkPlayerExclusion']);

    // Chat en salon (mode humain)
    Route::get('/{room_id}/chat/messages', [App\Http\Controllers\API\RoomChatController::class, 'index']);
    Route::post('/{room_id}/chat/messages', [App\Http\Controllers\API\RoomChatController::class, 'store']);

    // Compteurs de plis (synchronisation / fallback)
    Route::get('/{room_id}/trick-counters', [App\Http\Controllers\API\RoundController::class, 'getTrickCounters']);
    Route::post('/{room_id}/trick-won', [App\Http\Controllers\API\RoundController::class, 'updateTrickCounters']);
});

// =====================================================
// PARTIES (Requiert auth)
// =====================================================

Route::prefix('games')->middleware('auth:sanctum')->group(function () {
    Route::get('/history', [App\Http\Controllers\API\GameController::class, 'history']);
    Route::get('/{game_id}', [App\Http\Controllers\API\GameController::class, 'show']);
    Route::post('/{game_id}/deal-cards', [App\Http\Controllers\API\GameController::class, 'dealCards']);
    Route::post('/{game_id}/announce', [App\Http\Controllers\API\GameController::class, 'announce']);
    Route::get('/{game_id}/announcement-turn', [App\Http\Controllers\API\GameController::class, 'getAnnouncementTurn']); // ✅ NOUVEAU
    Route::post('/{game_id}/play-card', [App\Http\Controllers\API\GameController::class, 'playCard']);
    Route::get('/{game_id}/current-turn', [App\Http\Controllers\API\GameController::class, 'getCurrentTurn']); // ✅ Modifié
    Route::get('/{game_id}/scores', [App\Http\Controllers\API\GameController::class, 'scores']);
    // ✅ NOUVEAU: Endpoint pour obtenir les cartes jouables
    Route::get('/{game_id}/playable-cards', [App\Http\Controllers\API\GameController::class, 'getPlayableCards']);
    // Finalisation de partie (sauvegarde du gagnant)
    Route::post('/finalize', [App\Http\Controllers\API\GameFinalizeController::class, 'finalize']);
});

// Route pour obtenir le trick actuel (utilise room_id au lieu de game_id)
Route::post('/games/get-current-trick', [App\Http\Controllers\API\GameController::class, 'getCurrentTrick'])->middleware('auth:sanctum');

// =====================================================
// MANCHES (Requiert auth)
// =====================================================

Route::prefix('rounds')->middleware('auth:sanctum')->group(function () {
    Route::post('/distribute-cards', [App\Http\Controllers\API\RoundController::class, 'distributeCards']);
    Route::post('/record-trick-won', [App\Http\Controllers\API\RoundController::class, 'recordTrickWon']);
    Route::post('/start', [App\Http\Controllers\API\RoundController::class, 'start']);
    Route::post('/save', [App\Http\Controllers\API\RoundController::class, 'save']);
    Route::get('/{round_id}/scores', [App\Http\Controllers\API\RoundController::class, 'getRoundScores']);
    // ✅ NOUVEAU: Endpoint pour valider les annonces
    Route::post('/validate-announcements', [App\Http\Controllers\API\RoundController::class, 'validateAnnouncements']);
});

// =====================================================
// ADMIN (Requiert auth + admin)
// =====================================================

Route::prefix('admin')->middleware('auth:sanctum')->group(function () {
    Route::get('/dashboard', [App\Http\Controllers\API\AdminController::class, 'dashboard']);
    Route::get('/system/fix-sequences', [App\Http\Controllers\API\AdminController::class, 'fixDatabaseSequences']);
    
    // Messages entre admins (ancien système - gardé pour compatibilité)
    Route::prefix('messages')->group(function () {
        Route::get('/', [App\Http\Controllers\API\AdminMessageController::class, 'index']);
        Route::post('/', [App\Http\Controllers\API\AdminMessageController::class, 'store']);
        Route::post('/{messageId}/reply', [App\Http\Controllers\API\AdminMessageController::class, 'reply']);
        Route::post('/{messageId}/mark-read', [App\Http\Controllers\API\AdminMessageController::class, 'markAsRead']);
    });
    
    // Conversations continues (nouveau système style WhatsApp)
    Route::prefix('messages/conversations')->group(function () {
        Route::get('/', [App\Http\Controllers\API\AdminMessageController::class, 'getConversations']);
        Route::get('/search', [App\Http\Controllers\API\AdminMessageController::class, 'searchConversations']);
        Route::post('/', [App\Http\Controllers\API\AdminMessageController::class, 'createConversation']);
        Route::get('/{conversationId}/messages', [App\Http\Controllers\API\AdminMessageController::class, 'getConversationMessages']);
        Route::post('/{conversationId}/send', [App\Http\Controllers\API\AdminMessageController::class, 'sendMessage']);
        Route::post('/{conversationId}/mark-read', [App\Http\Controllers\API\AdminMessageController::class, 'markConversationAsRead']);
    });
    
    // Présence en ligne (heartbeat)
    Route::post('/presence/heartbeat', [App\Http\Controllers\API\AdminMessageController::class, 'updatePresence']);
    
            // Modification et suppression de messages
            Route::prefix('messages')->group(function () {
                Route::put('/{messageId}', [App\Http\Controllers\API\AdminMessageController::class, 'updateMessage']);
                Route::delete('/{messageId}', [App\Http\Controllers\API\AdminMessageController::class, 'deleteMessage']);
                // Réactions aux messages
                Route::post('/{messageId}/reaction', [App\Http\Controllers\API\AdminMessageController::class, 'addReaction']);
                Route::delete('/{messageId}/reaction', [App\Http\Controllers\API\AdminMessageController::class, 'removeReaction']);
            });
    
    Route::prefix('users')->group(function () {
        Route::get('/', [App\Http\Controllers\API\AdminController::class, 'users']);
        Route::get('/{user_id}', [App\Http\Controllers\API\AdminController::class, 'userDetails']);
        Route::post('/{user_id}/toggle-status', [App\Http\Controllers\API\AdminController::class, 'toggleUserStatus']);
        Route::delete('/{user_id}', [App\Http\Controllers\API\AdminController::class, 'deleteUser']);
    });
    
    // Routes pour la gestion des admins (Super Admin uniquement)
    Route::get('/admins', [App\Http\Controllers\API\AdminController::class, 'getAdmins']);
    Route::post('/admins', [App\Http\Controllers\API\AdminController::class, 'createAdmin']);
    Route::put('/admins/{userId}', [App\Http\Controllers\API\AdminController::class, 'updateAdmin']);
    Route::get('/benefits', [App\Http\Controllers\API\AdminController::class, 'benefits']);
    
    Route::prefix('rooms')->group(function () {
        Route::get('/', [App\Http\Controllers\API\AdminController::class, 'rooms']);
        Route::get('/{room_id}', [App\Http\Controllers\API\AdminController::class, 'roomDetails']);
    });
    
    Route::prefix('transactions')->group(function () {
        Route::get('/', [App\Http\Controllers\API\TransactionController::class, 'getAllTransactions']);
        Route::get('/stats', [App\Http\Controllers\API\TransactionController::class, 'getTransactionStats']);
        Route::get('/{transaction_id}', [App\Http\Controllers\API\TransactionController::class, 'getTransactionDetails']);
        Route::post('/{transaction_id}/validate', [App\Http\Controllers\API\TransactionController::class, 'validateTransaction']);
        Route::post('/{transaction_id}/reject', [App\Http\Controllers\API\TransactionController::class, 'rejectTransaction']);
    });
    
    Route::prefix('logs')->group(function () {
        Route::get('/', [App\Http\Controllers\API\AdminController::class, 'logs']);
    });
});

// =====================================================
// SERVICE CLIENT (Requiert auth + manageradmin)
// =====================================================

Route::prefix('admin/service-client')->middleware('auth:sanctum')->group(function () {
    Route::get('/dashboard', [App\Http\Controllers\API\ServiceClientController::class, 'dashboard']);
    Route::get('/messages', [App\Http\Controllers\API\ServiceClientController::class, 'getMessages']);
    Route::post('/messages/{messageId}/mark-read', [App\Http\Controllers\API\ServiceClientController::class, 'markAsRead']);
    Route::post('/messages/{messageId}/mark-processed', [App\Http\Controllers\API\ServiceClientController::class, 'markAsProcessed']);
    Route::post('/messages/{messageId}/reply', [App\Http\Controllers\API\ServiceClientController::class, 'sendReply']);
    Route::post('/chat/{conversationId}/assign', [App\Http\Controllers\API\ServiceClientController::class, 'assignChat']);
    Route::post('/chat/{conversationId}/reply', [App\Http\Controllers\API\ServiceClientController::class, 'replyChat']);
    Route::post('/chat/{conversationId}/mark-processed', [App\Http\Controllers\API\ServiceClientController::class, 'markChatAsProcessed']);
    Route::get('/chat/{conversationId}/messages', [App\Http\Controllers\API\ServiceClientController::class, 'getChatMessages']);
});

// Route pour changer les mots de passe (Super Admin uniquement)
Route::prefix('admin')->middleware('auth:sanctum')->group(function () {
    Route::post('/admins/{userId}/change-password', [App\Http\Controllers\API\ServiceClientController::class, 'changeAdminPassword']);
});

// =====================================================
// PAIEMENTS (Requiert auth)
// =====================================================

Route::prefix('payment')->middleware('auth:sanctum')->group(function () {
    Route::get('/balance', [App\Http\Controllers\API\PaymentController::class, 'getBalance']);
    Route::get('/check-balance', [App\Http\Controllers\API\PaymentController::class, 'checkBalance']);
    Route::post('/debit-room-bet', [App\Http\Controllers\API\PaymentController::class, 'debitRoomBet']);
    Route::post('/credit-room-bet', [App\Http\Controllers\API\PaymentController::class, 'creditRoomBet']);
    // Nouveaux endpoints dépôt / retrait
    Route::post('/deposit', [App\Http\Controllers\API\PaymentController::class, 'deposit']);
    Route::post('/withdraw', [App\Http\Controllers\API\PaymentController::class, 'withdraw']);
    Route::get('/transactions', [App\Http\Controllers\API\PaymentController::class, 'transactions']);
    // Distribution des gains
    Route::post('/distribute-winnings', [App\Http\Controllers\API\PaymentController::class, 'distributeWinnings']);
});

// Webhook FedaPay (sans authentification)
Route::post('/payment/fedapay-webhook', [App\Http\Controllers\API\PaymentController::class, 'fedapayWebhook']);

// =====================================================
// CHAT / ASSISTANCE (Requiert auth)
// =====================================================

Route::prefix('chat')->middleware('auth:sanctum')->group(function () {
    Route::get('/conversation', [App\Http\Controllers\API\ChatController::class, 'getOrCreateConversation']);
    Route::post('/message', [App\Http\Controllers\API\ChatController::class, 'sendMessage']);
    Route::post('/request-manager', [App\Http\Controllers\API\ChatController::class, 'requestManager']);
    Route::post('/close', [App\Http\Controllers\API\ChatController::class, 'closeConversation']);
});

// =====================================================
// HEALTH CHECK (Sans auth)
// =====================================================

Route::get('/health', function () {
    return response()->json([
        'status' => 'ok',
        'message' => 'CAURIS Backend API is running',
        'version' => '1.0.0',
        'timestamp' => now()->toDateTimeString()
    ]);
});
