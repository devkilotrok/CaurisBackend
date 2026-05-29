const express = require('express');
const http = require('http');
const socketIo = require('socket.io');
const cors = require('cors');

const app = express();
app.use(cors());
app.use(express.json()); // Pour parser les requêtes JSON

const server = http.createServer(app);
const io = socketIo(server, {
  cors: {
    origin: "*",
    methods: ["GET", "POST"]
  }
});

// Store active rooms and players
const activeRooms = new Map();
const playerRooms = new Map();

io.on('connection', (socket) => {
  console.log('Nouvelle connexion:', socket.id);

  // Join a room
  socket.on('join_room', (data) => {
    const { roomId, playerName } = data;
    
    if (!roomId || !playerName) {
      socket.emit('error', { message: 'Room ID and player name required' });
      return;
    }

    const roomIdStr = String(roomId);

    socket.join(roomIdStr);
    playerRooms.set(socket.id, { roomId: roomIdStr, playerName });

    // Initialize room if it doesn't exist
    if (!activeRooms.has(roomIdStr)) {
      activeRooms.set(roomIdStr, {
        players: [],
        currentRound: 1,
        announcements: {},
        cardsInPlay: [],
        gamePhase: 'waiting'
      });
    }

    const room = activeRooms.get(roomIdStr);
    
    // Add player if not already present
    if (!room.players.find(p => p.name === playerName)) {
      room.players.push({ name: playerName, socketId: socket.id });
    } else {
      // Update socket ID for existing player
      const player = room.players.find(p => p.name === playerName);
      if (player) player.socketId = socket.id;
    }

    console.log(`${playerName} joined room ${roomIdStr}`);

    // Rejouer distribution / phase d'annonces si le client a raté le broadcast Laravel
    if (room.lastCardDistribution) {
      socket.emit('card_distribution', room.lastCardDistribution);
      console.log(`🔁 Replay card_distribution → ${playerName} (room ${roomIdStr})`);
    }
    if (room.lastAnnouncementPhase) {
      socket.emit('announcement_phase_started', room.lastAnnouncementPhase);
      console.log(`🔁 Replay announcement_phase_started → ${playerName} (room ${roomIdStr})`);
    }
    
    // Notify all players in the room
    io.to(roomIdStr).emit('player_joined', {
      playerName,
      roomId: roomIdStr,
      players: room.players,
      timestamp: new Date().toISOString()
    });
  });

  // Leave a room
  socket.on('leave_room', (data) => {
    const { roomId, playerName } = data;
    
    if (roomId) {
      socket.leave(roomId);
      playerRooms.delete(socket.id);
      
      const room = activeRooms.get(roomId);
      if (room) {
        room.players = room.players.filter(p => p.name !== playerName);
        
        io.to(roomId).emit('player_left', {
          playerName,
          roomId,
          players: room.players,
          timestamp: new Date().toISOString()
        });
      }
    }
  });

  // Handle card play
  // ⚠️ IMPORTANT: Ne PAS diffuser directement ici pour éviter la double animation
  // Laravel gère la diffusion via l'API playCard et l'endpoint /broadcast
  // Ce handler sert uniquement à logger et stocker l'état local si nécessaire
  socket.on('play_card', (data) => {
    const { roomId, playerName, card, trickNumber } = data;
    
    // ✅ LOG DÉTAILLÉ: Vérifier ce qui est reçu
    const cardCode = `${card?.value || '?'}${card?.suit || '?'}`;
    console.log(`[play_card] socket=${socket.id} player=${playerName} room=${roomId} card=${cardCode} trick=${trickNumber} (NOT broadcasting - Laravel handles it)`);
    
    const room = activeRooms.get(roomId);
    if (room) {
      room.cardsInPlay.push({ playerName, card, trickNumber });
    }

    // ❌ NE PAS DIFFUSER ICI - Laravel le fait via l'API playCard
    // Cela évite la double animation des cartes
    // La diffusion se fait uniquement via Laravel → /broadcast endpoint
  });

  // Handle announcement
  socket.on('make_announcement', (data) => {
    const { roomId, playerName, announcement } = data;
    
    const room = activeRooms.get(roomId);
    if (room) {
      room.announcements[playerName] = announcement;
    }

    // Broadcast to all players in the room
    io.to(roomId).emit('announcement_made', {
      playerName,
      announcement,
      roomId,
      timestamp: new Date().toISOString()
    });
  });

  // Handle game start
  socket.on('start_game', (data) => {
    const { roomId, players } = data;
    
    io.to(roomId).emit('game_started', {
      roomId,
      players,
      timestamp: new Date().toISOString()
    });
  });

  // Handle trick win (legacy - backend Laravel est la source de vérité)
  socket.on('trick_won', (data) => {
    const { roomId, winnerName, trickNumber } = data || {};
    console.log(`[trick_won] (legacy) socket=${socket.id} room=${roomId} trick=${trickNumber} winner=${winnerName}`);
    console.log('   ⚠️ Événement ignoré: la fin de pli est désormais gérée exclusivement par Laravel.');
  });

  // Handle round completion
  socket.on('round_completed', (data) => {
    const { roomId, roundNumber, scores, announcedByPlayer, obtainedByPlayer } = data;
    
    console.log(`Round ${roundNumber} completed in room ${roomId}`);
    if (announcedByPlayer) {
      console.log(`Announced:`, announcedByPlayer);
    }
    if (obtainedByPlayer) {
      console.log(`Obtained:`, obtainedByPlayer);
    }
    
    // ✅ Retransmettre avec toutes les données pour synchroniser tous les joueurs
    io.to(roomId).emit('round_completed_broadcast', {
      roomId,
      roundNumber,
      scores,
      announcedByPlayer: announcedByPlayer || null,
      obtainedByPlayer: obtainedByPlayer || null,
      timestamp: new Date().toISOString()
    });
  });

  // ✅ Handle card distribution (créateur distribue et envoie à tous)
  socket.on('card_distribution', (data) => {
    const { roomId, distribution, round_number, timestamp } = data;
    
    console.log(`Card distribution for round ${round_number} in room ${roomId}`);
    console.log(`Distribution to ${Object.keys(distribution || {}).length} players`);
    
    // ✅ Retransmettre à tous les joueurs de la room (sauf l'expéditeur)
    io.to(roomId).emit('card_distribution', {
      roomId,
      distribution,
      round_number,
      timestamp: timestamp || new Date().toISOString()
    });
  });

  // Handle room chat message
  socket.on('room_chat_message', (data) => {
    const { roomId, playerName, message, message_type, preset_code } = data;
    
    console.log(`📤 [CHAT] ${playerName} sent chat message in room ${roomId}: ${message}`);
    console.log(`   Message type: ${message_type || 'text'}`);
    console.log(`   Preset code: ${preset_code || 'none'}`);
    
    // Vérifier combien de clients sont dans la salle
    const room = activeRooms.get(roomId);
    const clientsInRoom = room ? room.players.length : 0;
    console.log(`   Clients dans la salle: ${clientsInRoom}`);
    
    // Broadcast to all players in the room (including sender for consistency)
    const broadcastData = {
      playerName,
      message,
      message_type: message_type || 'text',
      preset_code: preset_code || null,
      roomId,
      timestamp: new Date().toISOString()
    };
    
    console.log(`   📢 Broadcast du message à tous les joueurs de la salle ${roomId}`);
    io.to(roomId).emit('room_chat_message', broadcastData);
    console.log(`   ✅ Message broadcasté avec succès`);
  });

  // Handle disconnection
  socket.on('disconnect', () => {
    const playerInfo = playerRooms.get(socket.id);
    
    if (playerInfo) {
      const { roomId, playerName } = playerInfo;
      
      socket.leave(roomId);
      playerRooms.delete(socket.id);
      
      const room = activeRooms.get(roomId);
      if (room) {
        room.players = room.players.filter(p => p.name !== playerName);
        
        io.to(roomId).emit('player_left', {
          playerName,
          roomId,
          players: room.players,
          timestamp: new Date().toISOString()
        });
      }
    }
    
    console.log('Client disconnected:', socket.id);
  });
});

// =====================================================
// HTTP Endpoint pour broadcasts depuis Laravel
// =====================================================

/**
 * POST /broadcast
 * Endpoint pour que Laravel puisse diffuser des événements aux clients
 * 
 * Body JSON:
 * {
 *   "room_id": "15",
 *   "event": "player_replaced",
 *   "data": { ... }
 * }
 */
app.post('/broadcast', (req, res) => {
  try {
    const { room_id, event, data } = req.body;

    if (!room_id) {
      return res.status(400).json({
        success: false,
        message: 'room_id is required'
      });
    }

    if (!event) {
      return res.status(400).json({
        success: false,
        message: 'event is required'
      });
    }

    // Convertir room_id en string si c'est un nombre (pour compatibilité)
    const roomIdStr = String(room_id);

    const payload = {
      ...data,
      roomId: data.roomId ?? data.room_id ?? roomIdStr,
      room_id: roomIdStr,
      timestamp: new Date().toISOString(),
    };

    const room = io.sockets.adapter.rooms.get(roomIdStr);
    const connectedCount = room ? room.size : 0;

    io.to(roomIdStr).emit(event, payload);

    // Conserver le dernier état pour les clients qui rejoignent après le broadcast
    if (!activeRooms.has(roomIdStr)) {
      activeRooms.set(roomIdStr, {
        players: [],
        currentRound: 1,
        announcements: {},
        cardsInPlay: [],
        gamePhase: 'waiting',
      });
    }
    const roomState = activeRooms.get(roomIdStr);
    if (event === 'card_distribution') {
      roomState.lastCardDistribution = payload;
      roomState.gamePhase = 'announcement';
      roomState.currentRound = payload.round_number ?? roomState.currentRound;
    } else if (event === 'announcement_phase_started') {
      roomState.lastAnnouncementPhase = payload;
      roomState.gamePhase = 'announcement';
    }

    console.log(`📡 Broadcast: ${event} to room ${roomIdStr} (${connectedCount} socket(s))`);

    res.json({
      success: true,
      message: `Event ${event} broadcasted to room ${roomIdStr}`,
      room_id: roomIdStr,
      event: event
    });

  } catch (error) {
    console.error('❌ Error broadcasting:', error);
    res.status(500).json({
      success: false,
      message: 'Error broadcasting event',
      error: error.message
    });
  }
});

// Health check endpoint
app.get('/health', (req, res) => {
  res.json({
    status: 'ok',
    message: 'WebSocket server is running',
    rooms: activeRooms.size,
    timestamp: new Date().toISOString()
  });
});

const PORT = process.env.PORT || 3000;
// ✅ Écouter sur toutes les interfaces (0.0.0.0) pour permettre les connexions depuis d'autres appareils
const HOST = process.env.HOST || '0.0.0.0';

server.listen(PORT, HOST, () => {
  console.log(`WebSocket server running on port ${PORT}`);
  console.log(`Server ready to accept connections at http://${HOST === '0.0.0.0' ? 'localhost' : HOST}:${PORT}`);
  console.log(`📡 Broadcast endpoint: POST http://localhost:${PORT}/broadcast`);
  console.log(`🌐 Accessible from network at: http://<your-ip>:${PORT}`);
});

