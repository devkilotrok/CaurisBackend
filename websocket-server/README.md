# WebSocket Server pour Cauris

Serveur WebSocket utilisant Socket.io pour gérer les communications en temps réel du jeu Cauris.

## Installation

```bash
cd websocket-server
npm install
```

## Utilisation

### Mode Production
```bash
npm start
```

### Mode Développement (avec auto-reload)
```bash
npm run dev
```

## Configuration

Le serveur écoute sur le port 3000 par défaut. Vous pouvez changer le port avec la variable d'environnement PORT :

```bash
PORT=8080 npm start
```

## Événements Socket.io

### Côté Client → Serveur

#### `join_room`
Rejoindre une salle de jeu.
```javascript
socket.emit('join_room', {
  roomId: 'room123',
  playerName: 'Lewis'
});
```

#### `leave_room`
Quitter une salle de jeu.
```javascript
socket.emit('leave_room', {
  roomId: 'room123',
  playerName: 'Lewis'
});
```

#### `play_card`
Jouer une carte.
```javascript
socket.emit('play_card', {
  roomId: 'room123',
  playerName: 'Lewis',
  card: { suit: 'spades', value: 'A' },
  trickNumber: 1
});
```

#### `make_announcement`
Faire une annonce.
```javascript
socket.emit('make_announcement', {
  roomId: 'room123',
  playerName: 'Lewis',
  announcement: 3
});
```

#### `start_game`
Démarrer le jeu.
```javascript
socket.emit('start_game', {
  roomId: 'room123',
  players: ['Lewis', 'Bil', 'Vous', 'John']
});
```

#### `trick_won`
Ann oncer qu'un pli a été gagné.
```javascript
socket.emit('trick_won', {
  roomId: 'room123',
  winnerName: 'Lewis',
  trickNumber: 1
});
```

#### `round_completed`
Ann oncer qu'un round est terminé.
```javascript
socket.emit('round_completed', {
  roomId: 'room123',
  roundNumber: 1,
  scores: { 'Lewis': 30, 'Bil': 20, 'Vous': 40, 'John': 10 }
});
```

### Côté Serveur → Client

#### `player_joined`
Un joueur a rejoint la salle.
```javascript
socket.on('player_joined', (data) => {
  // data.roomId
  // data.playerName
  // data.players[]
});
```

#### `player_left`
Un joueur a quitté la salle.
```javascript
socket.on('player_left', (data) => {
  // data.roomId
  // data.playerName
  // data.players[]
});
```

#### `card_played`
Une carte a été jouée.
```javascript
socket.on('card_played', (data) => {
  // data.playerName
  // data.card
  // data.trickNumber
});
```

#### `announcement_made`
Une annonce a été faite.
```javascript
socket.on('announcement_made', (data) => {
  // data.playerName
  // data.announcement
});
```

#### `game_started`
Le jeu a commencé.
```javascript
socket.on('game_started', (data) => {
  // data.roomId
  // data.players[]
});
```

#### `trick_won_broadcast`
Un pli a été gagné.
```javascript
socket.on('trick_won_broadcast', (data) => {
  // data.winnerName
  // data.trickNumber
});
```

#### `round_completed_broadcast`
Un round est terminé.
```javascript
socket.on('round_completed_broadcast', (data) => {
  // data.roundNumber
  // data.scores{}
});
```

## Intégration avec Laravel

Le serveur WebSocket fonctionne indépendamment de Laravel mais peut être intégré via les Events Laravel.

Pour déclencher des événements depuis Laravel :

```php
// Dans votre controller Laravel
event(new CardPlayed($roomId, $playerName, $card, $trickNumber));

// Le serveur Node.js écoute les événements via Redis ou Pub/Sub
```

## Sécurité

⚠️ **Important** : En production, vous devez implémenter :
- Authentification JWT pour valider les connexions
- Validation des données côté serveur
- Rate limiting
- Encryption (wss://)

