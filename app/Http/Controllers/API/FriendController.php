<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\FriendRequest;
use App\Models\Friendship;
use App\Models\User;

class FriendController extends Controller
{
    /**
     * Liste des amis
     */
    public function index(Request $request)
    {
        try {
            $user = $request->user();

            $friends = Friendship::where(function($query) use ($user) {
                    $query->where('user_id', $user->user_id)
                          ->orWhere('friend_id', $user->user_id);
                })
                ->where('status', 'accepted')
                ->with(['user', 'friend'])
                ->get()
                ->map(function($friendship) use ($user) {
                    $friend = $friendship->user_id == $user->user_id 
                        ? $friendship->friend 
                        : $friendship->user;
                    
                    return [
                        'friend_id' => $friend->user_id,
                        'pseudo' => $friend->pseudo,
                        'avatar' => $friend->avatar,
                        'status' => 'online' // TODO: Implémenter le système de statut
                    ];
                });

            return $this->apiResponse(true, 'Amis récupérés', $friends);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Rechercher des amis
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

            $users = User::where(function($q) use ($query) {
                    $q->where('pseudo', 'LIKE', "%{$query}%")
                      ->orWhere('email', 'LIKE', "%{$query}%");
                })
                ->where('user_id', '!=', $request->user()->user_id)
                ->where('is_active', true)
                ->select('user_id', 'pseudo', 'email', 'avatar')
                ->limit(20)
                ->get();

            return $this->apiResponse(true, 'Utilisateurs trouvés', $users);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Envoyer une demande d'amitié
     */
    public function sendRequest(Request $request)
    {
        try {
            $validator = \Illuminate\Support\Facades\Validator::make($request->all(), [
                'friend_id' => 'required|exists:users,user_id',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Erreur de validation',
                    'errors' => $validator->errors()
                ], 422);
            }

            $user = $request->user();
            $friendId = $request->friend_id;

            // Vérifier si une demande existe déjà
            $existingRequest = FriendRequest::where(function($q) use ($user, $friendId) {
                $q->where('sender_id', $user->user_id)
                  ->where('receiver_id', $friendId);
            })->orWhere(function($q) use ($user, $friendId) {
                $q->where('sender_id', $friendId)
                  ->where('receiver_id', $user->user_id);
            })->first();

            if ($existingRequest) {
                return response()->json([
                    'success' => false,
                    'message' => 'Une demande existe déjà'
                ], 400);
            }

            $requestSent = FriendRequest::create([
                'sender_id' => $user->user_id,
                'receiver_id' => $friendId,
                'status' => 'pending'
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Demande d\'amitié envoyée',
                'data' => $requestSent
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtenir les demandes d'amitié en attente
     */
    public function getRequests(Request $request)
    {
        try {
            $user = $request->user();

            // Demandes reçues (pending)
            $receivedRequests = FriendRequest::where('receiver_id', $user->user_id)
                ->where('status', 'pending')
                ->with('sender')
                ->get()
                ->map(function($request) {
                    return [
                        'request_id' => $request->request_id,
                        'from_user_id' => $request->sender_id,
                        'from_user_pseudo' => $request->sender->pseudo ?? 'Unknown',
                        'from_user_avatar' => $request->sender->avatar ?? '👤',
                        'status' => $request->status,
                        'created_at' => $request->created_at,
                    ];
                });

            return $this->apiResponse(true, 'Demandes d\'amitié récupérées', [
                'requests' => $receivedRequests
            ], 200, false);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Accepter une demande d'amitié
     */
    public function accept($requestId)
    {
        try {
            $user = $request()->user();
            
            $friendRequest = FriendRequest::where('receiver_id', $user->user_id)
                ->where('request_id', $requestId)
                ->first();

            if (!$friendRequest) {
                return response()->json([
                    'success' => false,
                    'message' => 'Demande non trouvée'
                ], 404);
            }

            $friendRequest->update(['status' => 'accepted']);

            // Créer l'amitié
            Friendship::create([
                'user_id' => $friendRequest->sender_id,
                'friend_id' => $friendRequest->receiver_id,
                'status' => 'accepted'
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Demande acceptée'
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Refuser une demande d'amitié
     */
    public function reject($requestId)
    {
        try {
            $user = request()->user();
            
            $friendRequest = FriendRequest::where('receiver_id', $user->user_id)
                ->where('request_id', $requestId)
                ->first();

            if (!$friendRequest) {
                return response()->json([
                    'success' => false,
                    'message' => 'Demande non trouvée'
                ], 404);
            }

            $friendRequest->update(['status' => 'rejected']);

            return response()->json([
                'success' => true,
                'message' => 'Demande refusée'
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Inviter un ami à rejoindre une salle
     */
    public function inviteToRoom(Request $request)
    {
        try {
            $validator = \Illuminate\Support\Facades\Validator::make($request->all(), [
                'friend_id' => 'required|exists:users,user_id',
                'room_id' => 'required|exists:rooms,room_id',
                'message' => 'nullable|string|max:255',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Erreur de validation',
                    'errors' => $validator->errors()
                ], 422);
            }

            // TODO: Implémenter la logique d'invitation à une salle

            return response()->json([
                'success' => true,
                'message' => 'Invitation envoyée'
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Supprimer un ami
     */
    public function remove($friendId)
    {
        try {
            $user = request()->user();

            Friendship::where(function($q) use ($user, $friendId) {
                $q->where('user_id', $user->user_id)
                  ->where('friend_id', $friendId);
            })->orWhere(function($q) use ($user, $friendId) {
                $q->where('user_id', $friendId)
                  ->where('friend_id', $user->user_id);
            })->delete();

            return response()->json([
                'success' => true,
                'message' => 'Ami supprimé'
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur: ' . $e->getMessage()
            ], 500);
        }
    }
}
