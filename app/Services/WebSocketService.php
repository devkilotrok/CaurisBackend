<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WebSocketService
{
    protected $socketUrl;

    public function __construct()
    {
        // URL du serveur WebSocket Node.js
        $this->socketUrl = env('WEBSOCKET_SERVER_URL', 'http://localhost:3000');
    }

    /**
     * Diffuser un événement à tous les clients d'une room
     * 
     * Note: Cette méthode suppose que le serveur WebSocket Node.js
     * expose un endpoint HTTP pour recevoir les messages de Laravel.
     * Si ce n'est pas le cas, il faudra modifier le serveur Node.js
     * pour ajouter cette fonctionnalité, ou utiliser un package PHP
     * pour communiquer directement via Socket.io.
     */
    public function broadcastToRoom($roomId, $data)
    {
        try {
            // Option 1: Si le serveur WebSocket a un endpoint HTTP pour broadcaster
            // (nécessite de modifier server.js pour ajouter cet endpoint)
            $response = Http::post("{$this->socketUrl}/broadcast", [
                'room_id' => $roomId,
                'event' => $data['event'] ?? 'message',
                'data' => $data['data'] ?? $data,
            ]);

            if ($response->successful()) {
                return true;
            }

            Log::warning('WebSocket broadcast failed', [
                'room_id' => $roomId,
                'response' => $response->body(),
            ]);

            // Option 2: Si le serveur WebSocket n'a pas d'endpoint HTTP,
            // on log simplement l'événement et on suppose que le frontend
            // gérera la synchronisation via les appels API
            Log::info('WebSocket event (would be broadcasted)', [
                'room_id' => $roomId,
                'event' => $data['event'] ?? 'message',
                'data' => $data['data'] ?? $data,
            ]);

            return false;

        } catch (\Exception $e) {
            Log::error('WebSocket service error', [
                'room_id' => $roomId,
                'error' => $e->getMessage(),
            ]);

            // En cas d'erreur, on ne bloque pas le processus
            // Le frontend recevra la mise à jour via la réponse API
            return false;
        }
    }

    /**
     * Alternative: Utiliser un service externe ou Redis Pub/Sub
     * pour diffuser les événements au serveur WebSocket
     */
    public function broadcastViaRedis($roomId, $data)
    {
        // TODO: Implémenter si Redis est configuré
        // Cette méthode utiliserait Redis Pub/Sub pour communiquer
        // avec le serveur WebSocket Node.js
    }
}

