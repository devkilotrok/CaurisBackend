<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Mail\ContactReplyEmail;
use App\Models\ChatConversation;
use App\Models\ChatMessage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;

class ServiceClientController extends Controller
{
    /**
     * Dashboard pour le service client
     */
    public function dashboard(Request $request)
    {
        try {
            $user = $request->user();
            
            // Vérifier que c'est un manager ou super admin
            $role = $user->role ?? (
                (($user->pseudo === 'superAdmin' || $user->email === 'superadmin@cauris.com') ? 'superadmin' :
                (($user->pseudo === 'manageradmin' || $user->email === 'manageradmin@cauris.com') ? 'manager' : 'user'))
            );
            
            if (!in_array($role, ['manager', 'superadmin'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Accès réservé au service client'
                ], 403);
            }

            // Statistiques des messages de contact
            $totalContactMessages = DB::table('contact_messages')->count();
            $unreadContactMessages = DB::table('contact_messages')
                ->where('status', 'unread')
                ->count();
            $pendingContactMessages = DB::table('contact_messages')
                ->where('status', 'read')
                ->count();
            $processedContactMessages = DB::table('contact_messages')
                ->where('status', 'processed')
                ->count();

            // Statistiques des conversations de chat
            $totalChatConversations = ChatConversation::whereIn('status', ['waiting_manager', 'with_manager', 'closed'])->count();
            $waitingChatConversations = ChatConversation::where('status', 'waiting_manager')->count();
            $activeChatConversations = ChatConversation::where('status', 'with_manager')->count();
            $closedChatConversations = ChatConversation::where('status', 'closed')->count();

            // Total combiné
            $totalMessages = $totalContactMessages + $totalChatConversations;
            $unreadMessages = $unreadContactMessages + $waitingChatConversations;
            $pendingMessages = $pendingContactMessages + $activeChatConversations;
            $processedMessages = $processedContactMessages + $closedChatConversations;

            // Messages récents (contact + chat)
            $recentContactMessages = DB::table('contact_messages')
                ->orderBy('created_at', 'desc')
                ->limit(3)
                ->get()
                ->map(function($msg) {
                    return [
                        'id' => 'contact_' . $msg->id,
                        'type' => 'contact',
                        'name' => $msg->name,
                        'email' => $msg->email,
                        'message' => $msg->message,
                        'status' => $msg->status,
                        'created_at' => $msg->created_at,
                    ];
                });

            $recentChatConversations = ChatConversation::with('user')
                ->whereIn('status', ['waiting_manager', 'with_manager', 'closed'])
                ->orderBy('created_at', 'desc')
                ->limit(3)
                ->get()
                ->map(function($conv) {
                    $lastMessage = $conv->messages()->latest()->first();
                    $messageStatus = 'unread';
                    if ($conv->status === 'closed') {
                        $messageStatus = 'processed';
                    } elseif ($conv->status === 'with_manager') {
                        $messageStatus = 'read';
                    }
                    return [
                        'id' => 'chat_' . $conv->id,
                        'type' => 'chat',
                        'name' => $conv->user->pseudo ?? 'Utilisateur',
                        'email' => $conv->user->email ?? '',
                        'message' => $lastMessage ? $lastMessage->message : 'Nouvelle conversation',
                        'status' => $messageStatus,
                        'created_at' => $conv->created_at,
                    ];
                });

            $recentMessages = $recentContactMessages->concat($recentChatConversations)
                ->sortByDesc('created_at')
                ->take(5)
                ->values();

            return response()->json([
                'success' => true,
                'data' => [
                    'total_messages' => $totalMessages,
                    'unread_messages' => $unreadMessages,
                    'pending_messages' => $pendingMessages,
                    'processed_messages' => $processedMessages,
                    'recent_messages' => $recentMessages,
                ]
            ], 200);

        } catch (\Exception $e) {
            \Log::error('Service client dashboard error', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors du chargement du dashboard'
            ], 500);
        }
    }

    /**
     * Liste des messages de contact (formulaire web + conversations chat)
     */
    public function getMessages(Request $request)
    {
        try {
            $user = $request->user();
            
            // Vérifier que c'est un manager ou super admin
            $role = $user->role ?? (
                (($user->pseudo === 'superAdmin' || $user->email === 'superadmin@cauris.com') ? 'superadmin' :
                (($user->pseudo === 'manageradmin' || $user->email === 'manageradmin@cauris.com') ? 'manager' : 'user'))
            );
            
            if (!in_array($role, ['manager', 'superadmin'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Accès réservé au service client'
                ], 403);
            }

            $perPage = $request->get('per_page', 20);
            $status = $request->get('status', 'all');
            $search = $request->get('search', '');
            $type = $request->get('type', 'all'); // 'all', 'contact', 'chat'

            // Récupérer les messages de contact (formulaire web)
            $contactMessagesQuery = DB::table('contact_messages');
            if ($status !== 'all') {
                $contactMessagesQuery->where('status', $status);
            }
            if ($search) {
                $contactMessagesQuery->where(function($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                      ->orWhere('email', 'like', "%{$search}%")
                      ->orWhere('message', 'like', "%{$search}%");
                });
            }
            $contactMessages = $contactMessagesQuery->get()->map(function($msg) {
                return [
                    'id' => 'contact_' . $msg->id,
                    'type' => 'contact',
                    'name' => $msg->name,
                    'email' => $msg->email,
                    'message' => $msg->message,
                    'status' => $msg->status,
                    'created_at' => $msg->created_at,
                    'user_id' => null,
                    'pseudo' => null,
                ];
            });

            // Récupérer les conversations de chat en attente de manager ou avec manager
            $chatConversationsQuery = ChatConversation::with(['user', 'messages'])
                ->whereIn('status', ['waiting_manager', 'with_manager', 'closed']);
            
            if ($search) {
                $chatConversationsQuery->whereHas('user', function($q) use ($search) {
                    $q->where('pseudo', 'like', "%{$search}%")
                      ->orWhere('email', 'like', "%{$search}%");
                });
            }
            
            $chatConversations = $chatConversationsQuery->get()->map(function($conv) {
                $lastUserMessage = $conv->messages()
                    ->where('sender_type', 'user')
                    ->latest()
                    ->first();
                
                $firstMessage = $conv->messages()->first();
                
                // Mapper le statut de la conversation au statut du message
                $messageStatus = 'unread';
                if ($conv->status === 'closed') {
                    $messageStatus = 'processed';
                } elseif ($conv->status === 'with_manager') {
                    $messageStatus = 'read';
                } elseif ($conv->status === 'waiting_manager') {
                    $messageStatus = 'unread';
                }
                
                return [
                    'id' => 'chat_' . $conv->id,
                    'type' => 'chat',
                    'name' => $conv->user->pseudo ?? 'Utilisateur',
                    'email' => $conv->user->email ?? '',
                    'message' => $lastUserMessage ? $lastUserMessage->message : ($firstMessage ? $firstMessage->message : 'Nouvelle conversation'),
                    'status' => $messageStatus,
                    'created_at' => $conv->created_at,
                    'user_id' => $conv->user_id,
                    'pseudo' => $conv->user->pseudo ?? null,
                    'conversation_id' => $conv->id,
                ];
            });

            // Combiner les deux types de messages
            $allMessages = $contactMessages->concat($chatConversations);
            
            // Filtrer par type si demandé
            if ($type !== 'all') {
                $allMessages = $allMessages->filter(function($msg) use ($type) {
                    return $msg['type'] === $type;
                });
            }

            // Filtrer par statut si demandé (après avoir combiné les messages)
            if ($status !== 'all') {
                $allMessages = $allMessages->filter(function($msg) use ($status) {
                    return $msg['status'] === $status;
                });
            }

            // Trier par date de création (plus récent en premier)
            $allMessages = $allMessages->sortByDesc('created_at')->values();

            // Pagination manuelle
            $total = $allMessages->count();
            $page = $request->get('page', 1);
            $offset = ($page - 1) * $perPage;
            $paginatedMessages = $allMessages->slice($offset, $perPage)->values();

            return response()->json([
                'success' => true,
                'data' => [
                    'data' => $paginatedMessages,
                    'current_page' => (int)$page,
                    'last_page' => (int)ceil($total / $perPage),
                    'total' => $total,
                    'per_page' => $perPage,
                ]
            ], 200);

        } catch (\Exception $e) {
            \Log::error('Get messages error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors du chargement des messages: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Marquer un message comme lu
     */
    public function markAsRead(Request $request, $messageId)
    {
        try {
            $user = $request->user();
            
            // Vérifier que c'est un manager ou super admin
            $role = $user->role ?? (
                (($user->pseudo === 'superAdmin' || $user->email === 'superadmin@cauris.com') ? 'superadmin' :
                (($user->pseudo === 'manageradmin' || $user->email === 'manageradmin@cauris.com') ? 'manager' : 'user'))
            );
            
            if (!in_array($role, ['manager', 'superadmin'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Accès réservé au service client'
                ], 403);
            }

            $updated = DB::table('contact_messages')
                ->where('id', $messageId)
                ->update([
                    'status' => 'read',
                    'read_at' => now(),
                    'read_by' => $user->user_id,
                    'updated_at' => now()
                ]);

            if ($updated) {
                return response()->json([
                    'success' => true,
                    'message' => 'Message marqué comme lu'
                ], 200);
            }

            return response()->json([
                'success' => false,
                'message' => 'Message non trouvé'
            ], 404);

        } catch (\Exception $e) {
            \Log::error('Mark as read error', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la mise à jour'
            ], 500);
        }
    }

    /**
     * Marquer un message comme traité
     */
    public function markAsProcessed(Request $request, $messageId)
    {
        try {
            $user = $request->user();
            
            // Vérifier que c'est un manager ou super admin
            $role = $user->role ?? (
                (($user->pseudo === 'superAdmin' || $user->email === 'superadmin@cauris.com') ? 'superadmin' :
                (($user->pseudo === 'manageradmin' || $user->email === 'manageradmin@cauris.com') ? 'manager' : 'user'))
            );
            
            if (!in_array($role, ['manager', 'superadmin'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Accès réservé au service client'
                ], 403);
            }

            $updated = DB::table('contact_messages')
                ->where('id', $messageId)
                ->update([
                    'status' => 'processed',
                    'processed_at' => now(),
                    'processed_by' => $user->user_id,
                    'updated_at' => now()
                ]);

            if ($updated) {
                return response()->json([
                    'success' => true,
                    'message' => 'Message marqué comme traité'
                ], 200);
            }

            return response()->json([
                'success' => false,
                'message' => 'Message non trouvé'
            ], 404);

        } catch (\Exception $e) {
            \Log::error('Mark as processed error', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la mise à jour'
            ], 500);
        }
    }

    /**
     * Envoyer une réponse à un message de contact
     */
    public function sendReply(Request $request, $messageId)
    {
        try {
            $user = $request->user();
            
            // Vérifier que c'est un manager ou super admin
            $role = $user->role ?? (
                (($user->pseudo === 'superAdmin' || $user->email === 'superadmin@cauris.com') ? 'superadmin' :
                (($user->pseudo === 'manageradmin' || $user->email === 'manageradmin@cauris.com') ? 'manager' : 'user'))
            );
            
            if (!in_array($role, ['manager', 'superadmin'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Accès réservé au service client'
                ], 403);
            }

            // Validation
            $validator = Validator::make($request->all(), [
                'reply_message' => 'required|string|min:10|max:5000',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Erreur de validation',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Récupérer le message original
            $message = DB::table('contact_messages')
                ->where('id', $messageId)
                ->first();

            if (!$message) {
                return response()->json([
                    'success' => false,
                    'message' => 'Message non trouvé'
                ], 404);
            }

            // Envoyer l'email de réponse
            try {
                Mail::to($message->email)->send(
                    new ContactReplyEmail(
                        $message->name,
                        $message->email,
                        $message->message,
                        $request->reply_message,
                        $user->pseudo ?? 'Service Client'
                    )
                );

                \Log::info('Contact reply email sent successfully', [
                    'message_id' => $messageId,
                    'client_email' => $message->email,
                    'manager_id' => $user->user_id,
                ]);

                // Marquer le message comme traité
                DB::table('contact_messages')
                    ->where('id', $messageId)
                    ->update([
                        'status' => 'processed',
                        'processed_at' => now(),
                        'processed_by' => $user->user_id,
                        'updated_at' => now()
                    ]);

                return response()->json([
                    'success' => true,
                    'message' => 'Réponse envoyée avec succès au client'
                ], 200);

            } catch (\Exception $mailError) {
                \Log::error('Contact reply email failed', [
                    'error' => $mailError->getMessage(),
                    'trace' => $mailError->getTraceAsString(),
                    'message_id' => $messageId,
                    'client_email' => $message->email,
                ]);

                // Même si l'email échoue, on peut quand même marquer comme traité
                // ou retourner une erreur selon votre préférence
                return response()->json([
                    'success' => false,
                    'message' => 'Erreur lors de l\'envoi de l\'email. Veuillez vérifier la configuration email ou réessayer plus tard. Détails: ' . $mailError->getMessage()
                ], 500);
            }

        } catch (\Exception $e) {
            \Log::error('Send reply error', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de l\'envoi de la réponse'
            ], 500);
        }
    }

    /**
     * Assigner un manager à une conversation de chat
     */
    public function assignChat(Request $request, $conversationId)
    {
        try {
            $user = $request->user();
            
            // Vérifier que c'est un manager ou super admin
            $role = $user->role ?? (
                (($user->pseudo === 'superAdmin' || $user->email === 'superadmin@cauris.com') ? 'superadmin' :
                (($user->pseudo === 'manageradmin' || $user->email === 'manageradmin@cauris.com') ? 'manager' : 'user'))
            );
            
            if (!in_array($role, ['manager', 'superadmin'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Accès réservé au service client'
                ], 403);
            }

            $conversation = ChatConversation::find($conversationId);
            
            if (!$conversation) {
                return response()->json([
                    'success' => false,
                    'message' => 'Conversation non trouvée'
                ], 404);
            }

            // Assigner le manager et changer le statut
            $conversation->update([
                'assigned_manager_id' => $user->user_id,
                'status' => 'with_manager',
                'assistant_type' => 'manager',
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Conversation assignée avec succès'
            ], 200);

        } catch (\Exception $e) {
            \Log::error('Assign chat error', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de l\'assignation'
            ], 500);
        }
    }

    /**
     * Répondre à une conversation de chat
     */
    public function replyChat(Request $request, $conversationId)
    {
        try {
            $user = $request->user();
            
            // Vérifier que c'est un manager ou super admin
            $role = $user->role ?? (
                (($user->pseudo === 'superAdmin' || $user->email === 'superadmin@cauris.com') ? 'superadmin' :
                (($user->pseudo === 'manageradmin' || $user->email === 'manageradmin@cauris.com') ? 'manager' : 'user'))
            );
            
            if (!in_array($role, ['manager', 'superadmin'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Accès réservé au service client'
                ], 403);
            }

            // Validation
            $validator = Validator::make($request->all(), [
                'message' => 'required|string|min:1|max:5000',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Erreur de validation',
                    'errors' => $validator->errors()
                ], 422);
            }

            $conversation = ChatConversation::with('user')->find($conversationId);
            
            if (!$conversation) {
                return response()->json([
                    'success' => false,
                    'message' => 'Conversation non trouvée'
                ], 404);
            }

            // S'assurer que le manager est assigné
            if (!$conversation->assigned_manager_id) {
                $conversation->update([
                    'assigned_manager_id' => $user->user_id,
                    'status' => 'with_manager',
                    'assistant_type' => 'manager',
                ]);
            }

            // Créer le message du manager
            ChatMessage::create([
                'conversation_id' => $conversation->id,
                'sender_type' => 'manager',
                'sender_id' => $user->user_id,
                'message' => $request->message,
                'is_read' => false,
            ]);

            // Mettre à jour la date du dernier message
            $conversation->update([
                'last_message_at' => now(),
            ]);

            // Envoyer un email de notification au client
            try {
                $clientUser = $conversation->user;
                if ($clientUser && $clientUser->email) {
                    // Récupérer le premier message de l'utilisateur comme message original
                    $firstUserMessage = $conversation->messages()
                        ->where('sender_type', 'user')
                        ->first();
                    
                    $originalMessage = $firstUserMessage 
                        ? $firstUserMessage->message 
                        : 'Vous avez une nouvelle réponse dans votre conversation de chat.';
                    
                    Mail::to($clientUser->email)->send(
                        new ContactReplyEmail(
                            $clientUser->pseudo ?? 'Utilisateur',
                            $clientUser->email,
                            $originalMessage,
                            $request->message,
                            $user->pseudo ?? 'Service Client'
                        )
                    );
                    
                    \Log::info('Chat reply email sent successfully', [
                        'conversation_id' => $conversationId,
                        'client_email' => $clientUser->email,
                        'manager_id' => $user->user_id,
                    ]);
                } else {
                    \Log::warning('Chat reply email not sent - no client email', [
                        'conversation_id' => $conversationId,
                        'user_id' => $conversation->user_id,
                    ]);
                }
            } catch (\Exception $mailError) {
                // Ne pas bloquer si l'email échoue, juste logger
                \Log::error('Chat reply email notification failed', [
                    'error' => $mailError->getMessage(),
                    'trace' => $mailError->getTraceAsString(),
                    'conversation_id' => $conversationId,
                ]);
            }

            \Log::info('Chat reply sent', [
                'conversation_id' => $conversationId,
                'manager_id' => $user->user_id,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Réponse envoyée avec succès'
            ], 200);

        } catch (\Exception $e) {
            \Log::error('Reply chat error', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de l\'envoi de la réponse'
            ], 500);
        }
    }

    /**
     * Récupérer tous les messages d'une conversation de chat
     */
    public function getChatMessages(Request $request, $conversationId)
    {
        try {
            $user = $request->user();
            
            // Vérifier que c'est un manager ou super admin
            $role = $user->role ?? (
                (($user->pseudo === 'superAdmin' || $user->email === 'superadmin@cauris.com') ? 'superadmin' :
                (($user->pseudo === 'manageradmin' || $user->email === 'manageradmin@cauris.com') ? 'manager' : 'user'))
            );
            
            if (!in_array($role, ['manager', 'superadmin'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Accès réservé au service client'
                ], 403);
            }

            $conversation = ChatConversation::with(['user', 'messages' => function($query) {
                $query->orderBy('created_at', 'asc');
            }])->find($conversationId);
            
            if (!$conversation) {
                return response()->json([
                    'success' => false,
                    'message' => 'Conversation non trouvée'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'conversation' => [
                        'id' => $conversation->id,
                        'user_id' => $conversation->user_id,
                        'user_name' => $conversation->user->pseudo ?? 'Utilisateur',
                        'user_email' => $conversation->user->email ?? '',
                        'status' => $conversation->status,
                        'created_at' => $conversation->created_at,
                    ],
                    'messages' => $conversation->messages->map(function($msg) {
                        return [
                            'id' => $msg->id,
                            'sender_type' => $msg->sender_type,
                            'sender_id' => $msg->sender_id,
                            'message' => $msg->message,
                            'created_at' => $msg->created_at,
                            'is_read' => $msg->is_read,
                        ];
                    }),
                ]
            ], 200);

        } catch (\Exception $e) {
            \Log::error('Get chat messages error', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors du chargement des messages'
            ], 500);
        }
    }

    /**
     * Marquer une conversation de chat comme traitée (fermée)
     */
    public function markChatAsProcessed(Request $request, $conversationId)
    {
        try {
            $user = $request->user();
            
            // Vérifier que c'est un manager ou super admin
            $role = $user->role ?? (
                (($user->pseudo === 'superAdmin' || $user->email === 'superadmin@cauris.com') ? 'superadmin' :
                (($user->pseudo === 'manageradmin' || $user->email === 'manageradmin@cauris.com') ? 'manager' : 'user'))
            );
            
            if (!in_array($role, ['manager', 'superadmin'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Accès réservé au service client'
                ], 403);
            }

            $conversation = ChatConversation::find($conversationId);
            
            if (!$conversation) {
                return response()->json([
                    'success' => false,
                    'message' => 'Conversation non trouvée'
                ], 404);
            }

            // Fermer la conversation
            $conversation->update([
                'status' => 'closed',
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Conversation marquée comme traitée'
            ], 200);

        } catch (\Exception $e) {
            \Log::error('Mark chat as processed error', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la mise à jour'
            ], 500);
        }
    }

    /**
     * Modifier le mot de passe d'un admin ou manager (Super Admin uniquement)
     */
    public function changeAdminPassword(Request $request, $userId)
    {
        try {
            $user = $request->user();
            
            // Vérifier que c'est un super admin
            $role = $user->role ?? (
                (($user->pseudo === 'superAdmin' || $user->email === 'superadmin@cauris.com') ? 'superadmin' :
                (($user->pseudo === 'manageradmin' || $user->email === 'manageradmin@cauris.com') ? 'manager' : 'user'))
            );
            
            if ($role !== 'superadmin') {
                return response()->json([
                    'success' => false,
                    'message' => 'Accès réservé au super administrateur'
                ], 403);
            }

            // Validation
            $validator = Validator::make($request->all(), [
                'new_password' => 'required|string|min:8',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Erreur de validation',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Récupérer l'utilisateur cible
            $targetUser = \App\Models\User::find($userId);
            
            if (!$targetUser) {
                return response()->json([
                    'success' => false,
                    'message' => 'Utilisateur non trouvé'
                ], 404);
            }

            // Vérifier que c'est un admin ou manager (pas un user normal)
            $targetRole = $targetUser->role ?? (
                (($targetUser->pseudo === 'superAdmin' || $targetUser->email === 'superadmin@cauris.com') ? 'superadmin' :
                (($targetUser->pseudo === 'manageradmin' || $targetUser->email === 'manageradmin@cauris.com') ? 'manager' : 'user'))
            );
            
            if (!in_array($targetRole, ['superadmin', 'admin', 'manager'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Vous ne pouvez modifier que les mots de passe des administrateurs et managers'
                ], 403);
            }

            // Ne pas permettre de modifier le mot de passe du superadmin
            if ($targetRole === 'superadmin') {
                return response()->json([
                    'success' => false,
                    'message' => 'Impossible de modifier le mot de passe du super administrateur'
                ], 403);
            }

            // Mettre à jour le mot de passe
            $targetUser->update([
                'password_hash' => \Hash::make($request->new_password),
            ]);

            \Log::info('Admin password changed by manager', [
                'manager_id' => $user->user_id,
                'target_user_id' => $userId,
                'target_pseudo' => $targetUser->pseudo,
                'target_role' => $targetRole,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Mot de passe modifié avec succès'
            ], 200);

        } catch (\Exception $e) {
            \Log::error('Change admin password error', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la modification du mot de passe'
            ], 500);
        }
    }
}

