<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\AdminMessage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;

class AdminMessageController extends Controller
{
    /**
     * Récupérer les messages (pour admin/manager ou super admin)
     */
    public function index(Request $request)
    {
        try {
            $user = $request->user();
            
            // Vérifier que c'est un admin, manager ou super admin
            $role = $user->role ?? (
                (($user->pseudo === 'superAdmin' || $user->email === 'superadmin@cauris.com') ? 'superadmin' :
                (($user->pseudo === 'manageradmin' || $user->email === 'manageradmin@cauris.com') ? 'manager' : 'user'))
            );
            
            if (!in_array($role, ['superadmin', 'admin', 'manager'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Accès réservé aux administrateurs'
                ], 403);
            }

            $isSuperAdmin = $role === 'superadmin';

            if ($isSuperAdmin) {
                // Super admin voit tous les messages qui lui sont adressés (ou sans destinataire)
                $messages = AdminMessage::with(['sender', 'recipient', 'replies.sender'])
                    ->whereNull('parent_id') // Seulement les messages principaux
                    ->where(function($query) use ($user) {
                        $query->where('recipient_id', $user->user_id)
                              ->orWhereNull('recipient_id'); // Messages généraux au super admin
                    })
                    ->orderBy('created_at', 'desc')
                    ->get()
                    ->map(function($msg) {
                        $replies = $msg->replies->map(function($reply) {
                            return [
                                'id' => $reply->id,
                                'sender_id' => $reply->sender_id,
                                'sender_pseudo' => $reply->sender->pseudo ?? 'Super Admin',
                                'message' => $reply->message,
                                'created_at' => $reply->created_at->toISOString(),
                            ];
                        });
                        
                        return [
                            'id' => $msg->id,
                            'sender_id' => $msg->sender_id,
                            'sender_pseudo' => $msg->sender->pseudo ?? 'Admin',
                            'recipient_id' => $msg->recipient_id,
                            'subject' => $msg->subject,
                            'message' => $msg->message,
                            'status' => $msg->status,
                            'parent_id' => $msg->parent_id,
                            'created_at' => $msg->created_at->toISOString(),
                            'read_at' => $msg->read_at ? $msg->read_at->toISOString() : null,
                            'replies' => $replies,
                        ];
                    });
            } else {
                // Admin/Manager voit ses propres messages envoyés au super admin avec les réponses
                $messages = AdminMessage::with(['sender', 'recipient', 'replies.sender'])
                    ->whereNull('parent_id')
                    ->where('sender_id', $user->user_id)
                    ->orderBy('created_at', 'desc')
                    ->get()
                    ->map(function($msg) {
                        $replies = $msg->replies->map(function($reply) {
                            return [
                                'id' => $reply->id,
                                'sender_id' => $reply->sender_id,
                                'sender_pseudo' => $reply->sender->pseudo ?? 'Super Admin',
                                'message' => $reply->message,
                                'created_at' => $reply->created_at->toISOString(),
                            ];
                        });
                        
                        return [
                            'id' => $msg->id,
                            'sender_id' => $msg->sender_id,
                            'sender_pseudo' => $msg->sender->pseudo ?? 'Admin',
                            'recipient_id' => $msg->recipient_id,
                            'subject' => $msg->subject,
                            'message' => $msg->message,
                            'status' => $msg->status,
                            'parent_id' => $msg->parent_id,
                            'created_at' => $msg->created_at->toISOString(),
                            'read_at' => $msg->read_at ? $msg->read_at->toISOString() : null,
                            'replies' => $replies,
                        ];
                    });
            }

            return response()->json([
                'success' => true,
                'data' => $messages
            ], 200);

        } catch (\Exception $e) {
            \Log::error('Get admin messages error', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération des messages'
            ], 500);
        }
    }

    /**
     * Envoyer un message au super admin (admin/manager uniquement)
     */
    public function store(Request $request)
    {
        try {
            $user = $request->user();
            
            // Vérifier que c'est un admin ou manager (pas super admin)
            $role = $user->role ?? (
                (($user->pseudo === 'superAdmin' || $user->email === 'superadmin@cauris.com') ? 'superadmin' :
                (($user->pseudo === 'manageradmin' || $user->email === 'manageradmin@cauris.com') ? 'manager' : 'user'))
            );
            
            if ($role === 'superadmin') {
                return response()->json([
                    'success' => false,
                    'message' => 'Le super admin ne peut pas envoyer de messages'
                ], 403);
            }

            if (!in_array($role, ['admin', 'manager'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Accès réservé aux administrateurs'
                ], 403);
            }

            // Validation
            $validator = Validator::make($request->all(), [
                'subject' => 'required|string|max:255',
                'message' => 'required|string',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Erreur de validation',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Trouver le super admin
            $superAdmin = \App\Models\User::where(function($query) {
                $query->where('pseudo', 'superAdmin')
                      ->orWhere('email', 'superadmin@cauris.com')
                      ->orWhere('role', 'superadmin');
            })->first();

            if (!$superAdmin) {
                return response()->json([
                    'success' => false,
                    'message' => 'Super administrateur introuvable'
                ], 404);
            }

            // Créer le message
            $message = AdminMessage::create([
                'sender_id' => $user->user_id,
                'recipient_id' => $superAdmin->user_id,
                'subject' => $request->subject,
                'message' => $request->message,
                'status' => 'unread',
            ]);

            \Log::info('Admin message sent', [
                'sender_id' => $user->user_id,
                'recipient_id' => $superAdmin->user_id,
                'message_id' => $message->id,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Message envoyé avec succès',
                'data' => [
                    'id' => $message->id,
                    'subject' => $message->subject,
                    'created_at' => $message->created_at->toISOString(),
                ]
            ], 201);

        } catch (\Exception $e) {
            \Log::error('Send admin message error', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de l\'envoi du message'
            ], 500);
        }
    }

    /**
     * Répondre à un message (super admin uniquement)
     */
    public function reply(Request $request, $messageId)
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
                    'message' => 'Seul le super administrateur peut répondre'
                ], 403);
            }

            // Validation
            $validator = Validator::make($request->all(), [
                'message' => 'required|string',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Erreur de validation',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Récupérer le message original
            $originalMessage = AdminMessage::find($messageId);
            
            if (!$originalMessage) {
                return response()->json([
                    'success' => false,
                    'message' => 'Message non trouvé'
                ], 404);
            }

            // Créer la réponse
            $reply = AdminMessage::create([
                'sender_id' => $user->user_id,
                'recipient_id' => $originalMessage->sender_id,
                'subject' => 'Re: ' . $originalMessage->subject,
                'message' => $request->message,
                'status' => 'unread',
                'parent_id' => $messageId,
            ]);

            // Marquer le message original comme répondu
            $originalMessage->update([
                'status' => 'replied',
            ]);

            \Log::info('Admin message replied', [
                'original_message_id' => $messageId,
                'reply_id' => $reply->id,
                'super_admin_id' => $user->user_id,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Réponse envoyée avec succès',
                'data' => [
                    'id' => $reply->id,
                    'created_at' => $reply->created_at->toISOString(),
                ]
            ], 201);

        } catch (\Exception $e) {
            \Log::error('Reply admin message error', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de l\'envoi de la réponse'
            ], 500);
        }
    }

    /**
     * Marquer un message comme lu (super admin uniquement)
     */
    public function markAsRead(Request $request, $messageId)
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

            $message = AdminMessage::find($messageId);
            
            if (!$message) {
                return response()->json([
                    'success' => false,
                    'message' => 'Message non trouvé'
                ], 404);
            }

            if ($message->status === 'unread') {
                $message->update([
                    'status' => 'read',
                    'read_at' => now(),
                ]);
            }

            return response()->json([
                'success' => true,
                'message' => 'Message marqué comme lu'
            ], 200);

        } catch (\Exception $e) {
            \Log::error('Mark admin message as read error', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la mise à jour'
            ], 500);
        }
    }

    /**
     * Récupérer les conversations (groupées par utilisateur)
     */
    public function getConversations(Request $request)
    {
        try {
            $user = $request->user();
            
            $role = $user->role ?? (
                (($user->pseudo === 'superAdmin' || $user->email === 'superadmin@cauris.com') ? 'superadmin' :
                (($user->pseudo === 'manageradmin' || $user->email === 'manageradmin@cauris.com') ? 'manager' : 'user'))
            );
            
            if (!in_array($role, ['superadmin', 'admin', 'manager'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Accès réservé aux administrateurs'
                ], 403);
            }

            $isSuperAdmin = $role === 'superadmin';

            if ($isSuperAdmin) {
                // Super admin : conversations avec tous les admins/managers
                // Récupérer tous les IDs d'utilisateurs uniques avec qui le super admin a une conversation
                // (soit il a reçu des messages, soit il en a envoyé)
                $receivedSenderIds = AdminMessage::where('recipient_id', $user->user_id)
                    ->whereNull('parent_id')
                    ->distinct()
                    ->pluck('sender_id')
                    ->filter()
                    ->unique();
                
                $sentRecipientIds = AdminMessage::where('sender_id', $user->user_id)
                    ->whereNotNull('recipient_id')
                    ->whereNull('parent_id')
                    ->distinct()
                    ->pluck('recipient_id')
                    ->filter()
                    ->unique();
                
                // Combiner les deux listes pour avoir tous les utilisateurs avec qui il y a une conversation
                $allUserIds = $receivedSenderIds->merge($sentRecipientIds)->unique();
                
                $conversations = collect();
                
                foreach ($allUserIds as $otherUserId) {
                    // Ne pas inclure le super admin lui-même
                    if ($otherUserId == $user->user_id) continue;
                    
                    $otherUser = \App\Models\User::find($otherUserId);
                    if (!$otherUser) continue;
                    
                    // Récupérer le dernier message non supprimé de la conversation
                    $lastMessage = AdminMessage::where(function($q) use ($user, $otherUserId) {
                        $q->where(function($q2) use ($user, $otherUserId) {
                            $q2->where('sender_id', $otherUserId)
                               ->where('recipient_id', $user->user_id);
                        })->orWhere(function($q2) use ($user, $otherUserId) {
                            $q2->where('sender_id', $user->user_id)
                               ->where('recipient_id', $otherUserId);
                        });
                    })
                    ->whereNull('parent_id')
                    ->where('is_deleted', false) // Exclure les messages supprimés
                    ->orderBy('created_at', 'desc')
                    ->first();
                    
                    // Compter UNIQUEMENT les messages non lus REÇUS par le super admin
                    // (pas ceux qu'il a envoyés)
                    $unreadCount = AdminMessage::where('sender_id', $otherUserId)
                        ->where('recipient_id', $user->user_id) // Seulement les messages reçus
                        ->where('status', 'unread')
                        ->whereNull('parent_id')
                        ->where('is_deleted', false)
                        ->count();
                    
                    // Créer un ID de conversation unique
                    $conversationId = min($user->user_id, $otherUserId) . '_' . max($user->user_id, $otherUserId);
                    
                    // Vérifier si l'utilisateur est en ligne (actif dans les 2 dernières minutes)
                    $isOnline = $otherUser->last_seen_at && 
                                $otherUser->last_seen_at->diffInMinutes(now()) < 2;
                    
                    $conversations->push([
                        'id' => $conversationId,
                        'other_user_id' => $otherUserId,
                        'other_user_pseudo' => $otherUser->pseudo ?? 'Admin',
                        'last_message' => $lastMessage ? substr($lastMessage->message, 0, 50) : null,
                        'last_message_at' => $lastMessage ? $lastMessage->created_at->toISOString() : null,
                        'unread_count' => $unreadCount,
                        'is_online' => $isOnline,
                        'last_seen_at' => $otherUser->last_seen_at ? $otherUser->last_seen_at->toISOString() : null,
                    ]);
                }
                
                // Trier par date du dernier message
                $conversations = $conversations->sortByDesc(function($conv) {
                    return $conv['last_message_at'] ? strtotime($conv['last_message_at']) : 0;
                })->values();
            } else {
                // Admin/Manager : conversations avec le super admin ET avec les autres admins/managers
                $userRole = $role; // 'admin' ou 'manager'
                
                // Récupérer tous les utilisateurs avec qui l'utilisateur peut discuter
                // 1. Le super admin
                $superAdmin = \App\Models\User::where(function($query) {
                    $query->where('pseudo', 'superAdmin')
                          ->orWhere('email', 'superadmin@cauris.com')
                          ->orWhere('role', 'superadmin');
                })->first();
                
                // 2. Les autres admins/managers (selon le rôle) - EXCLURE le super admin car déjà traité
                $otherUsers = \App\Models\User::where('user_id', '!=', $user->user_id)
                    ->where(function($query) use ($userRole, $superAdmin) {
                        // Exclure le super admin de cette liste
                        if ($superAdmin) {
                            $query->where('user_id', '!=', $superAdmin->user_id);
                        }
                        
                        if ($userRole === 'admin') {
                            // Admins peuvent discuter avec autres admins et managers (superadmin exclu)
                            $query->where(function($q) {
                                $q->where('role', 'admin')
                                  ->orWhere('role', 'manager');
                            });
                        } else if ($userRole === 'manager') {
                            // Managers peuvent discuter avec autres managers (superadmin exclu)
                            $query->where('role', 'manager');
                        }
                    })
                    ->get();
                
                $conversations = collect();
                
                // Ajouter conversation avec super admin
                if ($superAdmin) {
                    $lastMessage = AdminMessage::where(function($q) use ($user, $superAdmin) {
                        $q->where(function($q2) use ($user, $superAdmin) {
                            $q2->where('sender_id', $user->user_id)
                               ->where('recipient_id', $superAdmin->user_id);
                        })->orWhere(function($q2) use ($user, $superAdmin) {
                            $q2->where('sender_id', $superAdmin->user_id)
                               ->where('recipient_id', $user->user_id);
                        });
                    })
                    ->whereNull('parent_id')
                    ->where('is_deleted', false)
                    ->orderBy('created_at', 'desc')
                    ->first();

                    $unreadCount = AdminMessage::where('sender_id', $superAdmin->user_id)
                        ->where('recipient_id', $user->user_id)
                        ->where('status', 'unread')
                        ->whereNull('parent_id')
                        ->where('is_deleted', false)
                        ->count();

                    $conversationId = min($user->user_id, $superAdmin->user_id) . '_' . max($user->user_id, $superAdmin->user_id);

                    // Vérifier si le super admin est en ligne
                    $isSuperAdminOnline = $superAdmin->last_seen_at && 
                                         $superAdmin->last_seen_at->diffInMinutes(now()) < 2;
                    
                    $conversations->push([
                        'id' => $conversationId,
                        'other_user_id' => $superAdmin->user_id,
                        'other_user_pseudo' => 'Super Admin',
                        'last_message' => $lastMessage ? substr($lastMessage->message, 0, 50) : null,
                        'last_message_at' => $lastMessage ? $lastMessage->created_at->toISOString() : null,
                        'is_online' => $isSuperAdminOnline,
                        'last_seen_at' => $superAdmin->last_seen_at ? $superAdmin->last_seen_at->toISOString() : null,
                        'unread_count' => $unreadCount,
                    ]);
                }
                
                // Ajouter conversations avec autres admins/managers
                $seenConversationIds = [$conversations->pluck('id')->toArray()]; // Éviter les doublons
                
                foreach ($otherUsers as $otherUser) {
                    $otherUserId = $otherUser->user_id;
                    
                    // Vérifier s'il y a déjà des messages entre ces deux utilisateurs
                    $hasMessages = AdminMessage::where(function($q) use ($user, $otherUserId) {
                        $q->where(function($q2) use ($user, $otherUserId) {
                            $q2->where('sender_id', $user->user_id)
                               ->where('recipient_id', $otherUserId);
                        })->orWhere(function($q2) use ($user, $otherUserId) {
                            $q2->where('sender_id', $otherUserId)
                               ->where('recipient_id', $user->user_id);
                        });
                    })
                    ->whereNull('parent_id')
                    ->where('is_deleted', false)
                    ->exists();
                    
                    if (!$hasMessages) continue; // Ne pas afficher si aucune conversation
                    
                    $conversationId = min($user->user_id, $otherUserId) . '_' . max($user->user_id, $otherUserId);
                    
                    // Vérifier si cette conversation n'a pas déjà été ajoutée
                    if ($conversations->contains('id', $conversationId)) {
                        continue; // Skip si déjà présente
                    }
                    
                    $lastMessage = AdminMessage::where(function($q) use ($user, $otherUserId) {
                        $q->where(function($q2) use ($user, $otherUserId) {
                            $q2->where('sender_id', $user->user_id)
                               ->where('recipient_id', $otherUserId);
                        })->orWhere(function($q2) use ($user, $otherUserId) {
                            $q2->where('sender_id', $otherUserId)
                               ->where('recipient_id', $user->user_id);
                        });
                    })
                    ->whereNull('parent_id')
                    ->where('is_deleted', false)
                    ->orderBy('created_at', 'desc')
                    ->first();

                    $unreadCount = AdminMessage::where('sender_id', $otherUserId)
                        ->where('recipient_id', $user->user_id)
                        ->where('status', 'unread')
                        ->whereNull('parent_id')
                        ->where('is_deleted', false)
                        ->count();
                    
                    // Vérifier si l'utilisateur est en ligne (actif dans les 2 dernières minutes)
                    $isOnline = $otherUser->last_seen_at && 
                                $otherUser->last_seen_at->diffInMinutes(now()) < 2;
                    
                    $conversations->push([
                        'id' => $conversationId,
                        'other_user_id' => $otherUserId,
                        'other_user_pseudo' => $otherUser->pseudo ?? 'Admin',
                        'last_message' => $lastMessage ? ($lastMessage->is_deleted ? '[Message supprimé]' : substr($lastMessage->message, 0, 50)) : null,
                        'last_message_at' => $lastMessage ? $lastMessage->created_at->toISOString() : null,
                        'unread_count' => $unreadCount,
                        'is_online' => $isOnline,
                        'last_seen_at' => $otherUser->last_seen_at ? $otherUser->last_seen_at->toISOString() : null,
                    ]);
                }
                
                // Supprimer les doublons basés sur l'ID de conversation
                $conversations = $conversations->unique('id')->values();
                
                // Trier par date du dernier message
                $conversations = $conversations->sortByDesc(function($conv) {
                    return $conv['last_message_at'] ? strtotime($conv['last_message_at']) : 0;
                })->values();
            }

            return response()->json([
                'success' => true,
                'data' => $conversations
            ], 200);

        } catch (\Exception $e) {
            \Log::error('Get conversations error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération des conversations'
            ], 500);
        }
    }

    /**
     * Rechercher des conversations
     */
    public function searchConversations(Request $request)
    {
        try {
            $user = $request->user();
            
            $role = $user->role ?? (
                (($user->pseudo === 'superAdmin' || $user->email === 'superadmin@cauris.com') ? 'superadmin' :
                (($user->pseudo === 'manageradmin' || $user->email === 'manageradmin@cauris.com') ? 'manager' : 'user'))
            );
            
            if (!in_array($role, ['superadmin', 'admin', 'manager'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Accès réservé aux administrateurs'
                ], 403);
            }

            $searchTerm = $request->query('q', '');
            
            if (empty($searchTerm)) {
                return response()->json([
                    'success' => true,
                    'data' => []
                ], 200);
            }

            // Utiliser la même logique que getConversations mais filtrer par terme de recherche
            $conversations = $this->getConversations($request);
            
            if ($conversations->getStatusCode() !== 200) {
                return $conversations;
            }
            
            $conversationsData = json_decode($conversations->getContent(), true)['data'] ?? [];
            
            // Filtrer par terme de recherche
            $filtered = collect($conversationsData)->filter(function($conv) use ($searchTerm) {
                $search = strtolower($searchTerm);
                return str_contains(strtolower($conv['other_user_pseudo'] ?? ''), $search) ||
                       str_contains(strtolower($conv['last_message'] ?? ''), $search);
            })->values();

            return response()->json([
                'success' => true,
                'data' => $filtered
            ], 200);

        } catch (\Exception $e) {
            \Log::error('Search conversations error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la recherche des conversations'
            ], 500);
        }
    }

    /**
     * Récupérer les messages d'une conversation
     */
    public function getConversationMessages(Request $request, $conversationId)
    {
        try {
            $user = $request->user();
            
            $role = $user->role ?? (
                (($user->pseudo === 'superAdmin' || $user->email === 'superadmin@cauris.com') ? 'superadmin' :
                (($user->pseudo === 'manageradmin' || $user->email === 'manageradmin@cauris.com') ? 'manager' : 'user'))
            );
            
            if (!in_array($role, ['superadmin', 'admin', 'manager'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Accès réservé aux administrateurs'
                ], 403);
            }

            // Extraire les IDs des utilisateurs depuis l'ID de conversation
            $parts = explode('_', $conversationId);
            if (count($parts) !== 2) {
                return response()->json([
                    'success' => false,
                    'message' => 'ID de conversation invalide'
                ], 400);
            }

            $user1Id = (int)$parts[0];
            $user2Id = (int)$parts[1];

            // Vérifier que l'utilisateur fait partie de cette conversation
            if ($user->user_id !== $user1Id && $user->user_id !== $user2Id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Accès non autorisé à cette conversation'
                ], 403);
            }

            // Récupérer tous les messages de la conversation (sans parent_id pour les messages principaux)
            $messages = AdminMessage::with(['sender', 'replyTo.sender'])
                ->where(function($q) use ($user1Id, $user2Id) {
                    $q->where(function($q2) use ($user1Id, $user2Id) {
                        $q2->where('sender_id', $user1Id)
                           ->where('recipient_id', $user2Id);
                    })->orWhere(function($q2) use ($user1Id, $user2Id) {
                        $q2->where('sender_id', $user2Id)
                           ->where('recipient_id', $user1Id);
                    });
                })
                ->whereNull('parent_id')
                // Afficher tous les messages, y compris les supprimés (comme WhatsApp)
                ->orderBy('created_at', 'asc')
                ->get()
                ->map(function($msg) use ($user) {
                    $messageData = [
                        'id' => $msg->id,
                        'sender_id' => $msg->sender_id,
                        'sender_pseudo' => $msg->sender->pseudo ?? 'Admin',
                        'message' => $msg->message,
                        'media_type' => $msg->media_type ?? 'text',
                        'media_url' => $msg->media_url,
                        'reply_to_message_id' => $msg->reply_to_message_id,
                        'reply_to' => $msg->replyTo ? [
                            'id' => $msg->replyTo->id,
                            'message' => $msg->replyTo->message,
                            'sender_pseudo' => $msg->replyTo->sender->pseudo ?? 'Admin',
                            'media_type' => $msg->replyTo->media_type ?? 'text',
                            'media_url' => $msg->replyTo->media_url,
                        ] : null,
                        'status' => $msg->status,
                        'message_status' => $msg->message_status ?? 'sent',
                        'is_edited' => $msg->is_edited ?? false,
                        'is_deleted' => $msg->is_deleted ?? false,
                        'error_message' => $msg->error_message,
                        'reactions' => $msg->reactions ?? [],
                        'created_at' => $msg->created_at->toISOString(),
                        'read_at' => $msg->read_at ? $msg->read_at->toISOString() : null,
                        'delivered_at' => $msg->delivered_at ? $msg->delivered_at->toISOString() : null,
                        'edited_at' => $msg->edited_at ? $msg->edited_at->toISOString() : null,
                    ];
                    
                    // Inclure les données média temporaires uniquement si :
                    // 1. Le message a un média
                    // 2. L'utilisateur actuel est le destinataire (pas l'expéditeur)
                    // 3. Les données temporaires existent encore
                    if ($msg->media_type && $msg->media_type !== 'text' && 
                        $msg->sender_id !== $user->user_id && 
                        $msg->media_data_temp) {
                        $messageData['media_data'] = $msg->media_data_temp;
                        
                        // Supprimer les données temporaires après récupération
                        $msg->update(['media_data_temp' => null]);
                    }
                    
                    return $messageData;
                });

            return response()->json([
                'success' => true,
                'data' => $messages
            ], 200);

        } catch (\Exception $e) {
            \Log::error('Get conversation messages error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération des messages'
            ], 500);
        }
    }

    /**
     * Envoyer un message dans une conversation
     */
    public function sendMessage(Request $request, $conversationId)
    {
        try {
            $user = $request->user();
            
            $role = $user->role ?? (
                (($user->pseudo === 'superAdmin' || $user->email === 'superadmin@cauris.com') ? 'superadmin' :
                (($user->pseudo === 'manageradmin' || $user->email === 'manageradmin@cauris.com') ? 'manager' : 'user'))
            );
            
            if (!in_array($role, ['superadmin', 'admin', 'manager'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Accès réservé aux administrateurs'
                ], 403);
            }

            $validator = Validator::make($request->all(), [
                'message' => 'nullable|string|max:5000',
                'media_type' => 'nullable|in:text,audio,image',
                'media_filename' => 'nullable|string|max:255',
                'media_size' => 'nullable|integer',
                'media_data' => 'nullable|string', // Base64 encoded media data
                'reply_to_message_id' => 'nullable|exists:admin_messages,id',
            ]);

            // Vérifier qu'au moins un contenu est fourni
            if (!$request->has('message') && !$request->has('media_type')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Vous devez fournir un message ou un média'
                ], 422);
            }

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Erreur de validation',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Extraire les IDs des utilisateurs
            $parts = explode('_', $conversationId);
            if (count($parts) !== 2) {
                return response()->json([
                    'success' => false,
                    'message' => 'ID de conversation invalide'
                ], 400);
            }

            $user1Id = (int)$parts[0];
            $user2Id = (int)$parts[1];

            // Déterminer le destinataire
            $recipientId = ($user->user_id === $user1Id) ? $user2Id : $user1Id;

            // Vérifier que l'utilisateur fait partie de cette conversation
            if ($user->user_id !== $user1Id && $user->user_id !== $user2Id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Accès non autorisé à cette conversation'
                ], 403);
            }

            // Créer le message avec statut initial
            try {
                // Les médias sont stockés localement côté client (IndexedDB)
                // Le serveur transmet les données une seule fois, puis les supprime
                $mediaType = $request->media_type ?? 'text';
                $mediaFilename = $request->media_filename ?? null;
                $mediaSize = $request->media_size ?? null;
                $mediaData = $request->media_data ?? null; // Base64 encoded

                $message = AdminMessage::create([
                    'sender_id' => $user->user_id,
                    'recipient_id' => $recipientId,
                    'subject' => 'Message', // Sujet par défaut pour les conversations continues
                    'message' => $request->message ?? '',
                    'media_type' => $mediaType,
                    'media_url' => $mediaFilename, // Stocker le nom du fichier comme identifiant
                    'media_data_temp' => $mediaData, // Stocker temporairement les données base64
                    'status' => 'unread',
                    'message_status' => 'sent',
                    'is_edited' => false,
                    'is_deleted' => false,
                ]);
                
                // Préparer la réponse (sans les données média pour l'expéditeur)
                $responseData = [
                    'id' => $message->id,
                    'sender_id' => $message->sender_id,
                    'sender_pseudo' => $user->pseudo,
                    'message' => $message->message,
                    'media_type' => $message->media_type,
                    'media_url' => $message->media_url,
                    'reply_to_message_id' => $message->reply_to_message_id,
                    'reply_to' => $message->replyTo ? [
                        'id' => $message->replyTo->id,
                        'message' => $message->replyTo->message,
                        'sender_pseudo' => $message->replyTo->sender->pseudo ?? 'Admin',
                        'media_type' => $message->replyTo->media_type,
                    ] : null,
                    'status' => $message->status,
                    'message_status' => $message->message_status,
                    'is_edited' => false,
                    'is_deleted' => false,
                    'error_message' => null,
                    'created_at' => $message->created_at->toISOString(),
                    'delivered_at' => null, // Sera mis à jour après
                ];
                
                // Marquer comme livré immédiatement
                $message->update([
                    'message_status' => 'delivered',
                    'delivered_at' => now(),
                ]);
                
                // Mettre à jour delivered_at dans la réponse
                $responseData['delivered_at'] = $message->delivered_at->toISOString();

                return response()->json([
                    'success' => true,
                    'message' => 'Message envoyé',
                    'data' => [
                        'id' => $message->id,
                        'sender_id' => $message->sender_id,
                        'sender_pseudo' => $user->pseudo,
                        'message' => $message->message,
                        'media_type' => $message->media_type,
                        'media_url' => $message->media_url,
                        'reply_to_message_id' => $message->reply_to_message_id,
                        'reply_to' => $message->replyTo ? [
                            'id' => $message->replyTo->id,
                            'message' => $message->replyTo->message,
                            'sender_pseudo' => $message->replyTo->sender->pseudo ?? 'Admin',
                            'media_type' => $message->replyTo->media_type,
                        ] : null,
                        'status' => $message->status,
                        'message_status' => $message->message_status,
                        'is_edited' => false,
                        'is_deleted' => false,
                        'error_message' => null,
                        'created_at' => $message->created_at->toISOString(),
                        'delivered_at' => $message->delivered_at ? $message->delivered_at->toISOString() : null,
                    ]
                ], 201);
            } catch (\Exception $e) {
                \Log::error('Send message error', [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
                
                return response()->json([
                    'success' => false,
                    'message' => 'Erreur lors de l\'envoi du message',
                    'error' => $e->getMessage()
                ], 500);
            }

        } catch (\Exception $e) {
            \Log::error('Send message error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de l\'envoi du message'
            ], 500);
        }
    }

    /**
     * Créer une nouvelle conversation
     */
    public function createConversation(Request $request)
    {
        try {
            $user = $request->user();
            
            $role = $user->role ?? (
                (($user->pseudo === 'superAdmin' || $user->email === 'superadmin@cauris.com') ? 'superadmin' :
                (($user->pseudo === 'manageradmin' || $user->email === 'manageradmin@cauris.com') ? 'manager' : 'user'))
            );
            
            if (!in_array($role, ['admin', 'manager'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Seuls les admins et managers peuvent créer des conversations'
                ], 403);
            }

            // Trouver le super admin
            $superAdmin = \App\Models\User::where(function($query) {
                $query->where('pseudo', 'superAdmin')
                      ->orWhere('email', 'superadmin@cauris.com')
                      ->orWhere('role', 'superadmin');
            })->first();

            if (!$superAdmin) {
                return response()->json([
                    'success' => false,
                    'message' => 'Super administrateur introuvable'
                ], 404);
            }

            $conversationId = min($user->user_id, $superAdmin->user_id) . '_' . max($user->user_id, $superAdmin->user_id);

            return response()->json([
                'success' => true,
                'message' => 'Conversation créée',
                'data' => [
                    'id' => $conversationId,
                    'other_user_id' => $superAdmin->user_id,
                    'other_user_pseudo' => 'Super Admin',
                    'last_message' => null,
                    'last_message_at' => null,
                    'unread_count' => 0,
                ]
            ], 201);

        } catch (\Exception $e) {
            \Log::error('Create conversation error', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la création de la conversation'
            ], 500);
        }
    }

    /**
     * Marquer une conversation comme lue
     */
    public function markConversationAsRead(Request $request, $conversationId)
    {
        try {
            $user = $request->user();
            
            $role = $user->role ?? (
                (($user->pseudo === 'superAdmin' || $user->email === 'superadmin@cauris.com') ? 'superadmin' :
                (($user->pseudo === 'manageradmin' || $user->email === 'manageradmin@cauris.com') ? 'manager' : 'user'))
            );
            
            if (!in_array($role, ['superadmin', 'admin', 'manager'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Accès réservé aux administrateurs'
                ], 403);
            }

            $parts = explode('_', $conversationId);
            if (count($parts) !== 2) {
                return response()->json([
                    'success' => false,
                    'message' => 'ID de conversation invalide'
                ], 400);
            }

            $user1Id = (int)$parts[0];
            $user2Id = (int)$parts[1];
            $otherUserId = ($user->user_id === $user1Id) ? $user2Id : $user1Id;

            // Marquer tous les messages non lus comme lus (livrés ou non lus)
            AdminMessage::where('sender_id', $otherUserId)
                ->where(function($q) use ($user) {
                    $q->where('recipient_id', $user->user_id)
                      ->orWhereNull('recipient_id');
                })
                ->where(function($q) {
                    $q->where('status', 'unread')
                      ->orWhere('message_status', 'delivered');
                })
                ->whereNull('parent_id')
                ->where('is_deleted', false)
                ->update([
                    'status' => 'read',
                    'message_status' => 'read',
                    'read_at' => now(),
                ]);

            return response()->json([
                'success' => true,
                'message' => 'Conversation marquée comme lue'
            ], 200);
        } catch (\Exception $e) {
            \Log::error('Mark conversation as read error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors du marquage de la conversation'
            ], 500);
        }
    }

    /**
     * Mettre à jour la présence de l'utilisateur (heartbeat)
     */
    public function updatePresence(Request $request)
    {
        try {
            $user = $request->user();
            
            // Vérifier que c'est un admin, manager ou super admin
            $role = $user->role ?? (
                (($user->pseudo === 'superAdmin' || $user->email === 'superadmin@cauris.com') ? 'superadmin' :
                (($user->pseudo === 'manageradmin' || $user->email === 'manageradmin@cauris.com') ? 'manager' : 'user'))
            );
            
            if (!in_array($role, ['superadmin', 'admin', 'manager'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Accès réservé aux administrateurs'
                ], 403);
            }
            
            // Mettre à jour last_seen_at
            $user->update(['last_seen_at' => now()]);
            
            return response()->json([
                'success' => true,
                'message' => 'Présence mise à jour',
                'last_seen_at' => $user->last_seen_at->toISOString()
            ], 200);
        } catch (\Exception $e) {
            \Log::error('Update presence error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la mise à jour de la présence'
            ], 500);
        }
    }

    /**
     * Modifier un message
     */
    public function updateMessage(Request $request, $messageId)
    {
        try {
            $user = $request->user();
            
            $role = $user->role ?? (
                (($user->pseudo === 'superAdmin' || $user->email === 'superadmin@cauris.com') ? 'superadmin' :
                (($user->pseudo === 'manageradmin' || $user->email === 'manageradmin@cauris.com') ? 'manager' : 'user'))
            );
            
            if (!in_array($role, ['superadmin', 'admin', 'manager'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Accès réservé aux administrateurs'
                ], 403);
            }

            $validator = Validator::make($request->all(), [
                'message' => 'required|string|max:5000',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Erreur de validation',
                    'errors' => $validator->errors()
                ], 422);
            }

            $message = AdminMessage::find($messageId);
            
            if (!$message) {
                return response()->json([
                    'success' => false,
                    'message' => 'Message non trouvé'
                ], 404);
            }

            // Vérifier que l'utilisateur est l'expéditeur du message
            if ($message->sender_id !== $user->user_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Vous ne pouvez modifier que vos propres messages'
                ], 403);
            }

            // Vérifier que le message n'est pas supprimé
            if ($message->is_deleted) {
                return response()->json([
                    'success' => false,
                    'message' => 'Impossible de modifier un message supprimé'
                ], 400);
            }

            // Mettre à jour le message
            $message->update([
                'message' => $request->message,
                'is_edited' => true,
                'edited_at' => now(),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Message modifié',
                'data' => [
                    'id' => $message->id,
                    'sender_id' => $message->sender_id,
                    'sender_pseudo' => $user->pseudo,
                    'message' => $message->message,
                    'media_type' => $message->media_type ?? 'text',
                    'media_url' => $message->media_url,
                    'status' => $message->status,
                    'message_status' => $message->message_status,
                    'is_edited' => true,
                    'is_deleted' => false,
                    'created_at' => $message->created_at->toISOString(),
                    'edited_at' => $message->edited_at->toISOString(),
                    'read_at' => $message->read_at ? $message->read_at->toISOString() : null,
                    'delivered_at' => $message->delivered_at ? $message->delivered_at->toISOString() : null,
                ]
            ], 200);

        } catch (\Exception $e) {
            \Log::error('Update message error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la modification du message'
            ], 500);
        }
    }

    /**
     * Supprimer un message (soft delete)
     */
    public function deleteMessage(Request $request, $messageId)
    {
        try {
            $user = $request->user();
            
            $role = $user->role ?? (
                (($user->pseudo === 'superAdmin' || $user->email === 'superadmin@cauris.com') ? 'superadmin' :
                (($user->pseudo === 'manageradmin' || $user->email === 'manageradmin@cauris.com') ? 'manager' : 'user'))
            );
            
            if (!in_array($role, ['superadmin', 'admin', 'manager'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Accès réservé aux administrateurs'
                ], 403);
            }

            $message = AdminMessage::find($messageId);
            
            if (!$message) {
                return response()->json([
                    'success' => false,
                    'message' => 'Message non trouvé'
                ], 404);
            }

            // Vérifier que l'utilisateur est l'expéditeur du message
            if ($message->sender_id !== $user->user_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Vous ne pouvez supprimer que vos propres messages'
                ], 403);
            }

            // Soft delete
            $message->update([
                'is_deleted' => true,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Message supprimé'
            ], 200);

        } catch (\Exception $e) {
            \Log::error('Delete message error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la suppression du message'
            ], 500);
        }
    }

    /**
     * Ajouter une réaction à un message
     */
    public function addReaction(Request $request, $messageId)
    {
        try {
            $user = $request->user();
            
            $role = $user->role ?? (
                (($user->pseudo === 'superAdmin' || $user->email === 'superadmin@cauris.com') ? 'superadmin' :
                (($user->pseudo === 'manageradmin' || $user->email === 'manageradmin@cauris.com') ? 'manager' : 'user'))
            );
            
            if (!in_array($role, ['superadmin', 'admin', 'manager'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Accès réservé aux administrateurs'
                ], 403);
            }

            $validator = Validator::make($request->all(), [
                'emoji' => 'required|string|max:10',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Erreur de validation',
                    'errors' => $validator->errors()
                ], 422);
            }

            $message = AdminMessage::find($messageId);
            
            if (!$message) {
                return response()->json([
                    'success' => false,
                    'message' => 'Message non trouvé'
                ], 404);
            }

            // Récupérer les réactions actuelles
            $reactions = $message->reactions ?? [];
            $emoji = $request->emoji;

            // Initialiser la réaction si elle n'existe pas
            if (!isset($reactions[$emoji])) {
                $reactions[$emoji] = [];
            }

            // Vérifier si l'utilisateur a déjà réagi avec cet emoji
            $userReacted = collect($reactions[$emoji])->contains('user_id', $user->user_id);

            if (!$userReacted) {
                // Ajouter la réaction de l'utilisateur
                $reactions[$emoji][] = [
                    'user_id' => $user->user_id,
                    'pseudo' => $user->pseudo ?? 'Admin',
                    'reacted_at' => now()->toISOString(),
                ];
            }

            // Mettre à jour le message
            $message->update(['reactions' => $reactions]);

            return response()->json([
                'success' => true,
                'message' => 'Réaction ajoutée',
                'data' => [
                    'reactions' => $reactions
                ]
            ], 200);

        } catch (\Exception $e) {
            \Log::error('Add reaction error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de l\'ajout de la réaction'
            ], 500);
        }
    }

    /**
     * Supprimer une réaction d'un message
     */
    public function removeReaction(Request $request, $messageId)
    {
        try {
            $user = $request->user();
            
            $role = $user->role ?? (
                (($user->pseudo === 'superAdmin' || $user->email === 'superadmin@cauris.com') ? 'superadmin' :
                (($user->pseudo === 'manageradmin' || $user->email === 'manageradmin@cauris.com') ? 'manager' : 'user'))
            );
            
            if (!in_array($role, ['superadmin', 'admin', 'manager'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Accès réservé aux administrateurs'
                ], 403);
            }

            $validator = Validator::make($request->all(), [
                'emoji' => 'required|string|max:10',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Erreur de validation',
                    'errors' => $validator->errors()
                ], 422);
            }

            $message = AdminMessage::find($messageId);
            
            if (!$message) {
                return response()->json([
                    'success' => false,
                    'message' => 'Message non trouvé'
                ], 404);
            }

            // Récupérer les réactions actuelles
            $reactions = $message->reactions ?? [];
            $emoji = $request->emoji;

            // Supprimer la réaction de l'utilisateur
            if (isset($reactions[$emoji])) {
                $reactions[$emoji] = collect($reactions[$emoji])
                    ->reject(function ($reaction) use ($user) {
                        return $reaction['user_id'] === $user->user_id;
                    })
                    ->values()
                    ->toArray();

                // Supprimer l'emoji s'il n'y a plus de réactions
                if (empty($reactions[$emoji])) {
                    unset($reactions[$emoji]);
                }
            }

            // Mettre à jour le message
            $message->update(['reactions' => $reactions]);

            return response()->json([
                'success' => true,
                'message' => 'Réaction supprimée',
                'data' => [
                    'reactions' => $reactions
                ]
            ], 200);

        } catch (\Exception $e) {
            \Log::error('Remove reaction error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la suppression de la réaction'
            ], 500);
        }
    }
}

