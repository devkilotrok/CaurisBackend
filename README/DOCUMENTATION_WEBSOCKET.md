# Documentation WebSocket - Cauris

## 🎯 Vue d'ensemble

Le système WebSocket permet les communications temps réel entre les joueurs dans l'application Cauris. Il utilise :
- **Node.js/Socket.io** pour le serveur de WebSocket
- **Flutter/Web Socket Channel** pour le client
- **Laravel** pour la logique métier et les données persistantes

## 📦 Composants

### 1. Serveur WebSocket (Node.js)

**Fichiers créés :**
- `websocket-server/package.json` - Dépendances Node.js
- `websocket-server/server.js` - Serveur Socket.io principal
- `websocket-server/README.md` - Documentation du serveur

**Installation :**
```bash
cd /opt/lampp/htdocs/backendCauris/websocket-server
npm install
```

**Démarrage :**
```bash
npm start
```

Le serveur démarre sur le port **3000** par défaut.

### 2. Événements Laravel

**Fichiers créés :**
- `app/Events/RoomStarted.php` - Événement de démarrage de salon
- `app/Events/CardPlayed.php` - Événement de carte jouée
- `app/Events/PlayerAnnounced.php` - Événement d'annonce joueur
- `app/Events/RoundCompleted.php` - Événement de round terminé
- `app/Events/TrickWon.php` - Événement de pli gagné

Ces événements peuvent être diffusés via Laravel Broadcasting si nécessaire.

### 3. Client Flutter

**Fichier créé :**
- `lib/services/websocket/game_websocket_service.dart` - Service WebSocket Flutter
- `lib/services/websocket/README.md` - Documentation du service

**Configuration :**
Le package `web_socket_channel: ^2.4.0` a été ajouté à `pubspec.yaml`.

## 🚀 Démarrage rapide

### 1. Démarrer le serveur WebSocket

```bash
cd /opt/lampp/htdocs/backendCauris/websocket-server
npm start
```

### 2. Mettre à jour Flutter

```bash
cd ~/cauris_app
flutter pub get
```

### 3. Utiliser dans votre code Flutter

```dart
import 'package:cauris_app/services/websocket/game_websocket_service.dart';

// Initialiser
final wsService = GameWebSocketService();

// Se connecter
await wsService.connect(serverUrl: 'ws://192.168.1.100:3000');

// Rejoindre une salle
await wsService.joinRoom('room123', 'Lewis');

// Écouter les événements
wsService.onCardPlayed().listen((data) {
  print('${data['playerName']} a joué une carte');
});

// Jouer une carte
await wsService.playCard(
  cardSuit: 'spades',
  cardValue: 'A',
  trickNumber: 1,
);

// Se déconnecter
await wsService.disconnect();
```

## 📡 Événements disponibles

### Client → Serveur

| Méthode | Événement Socket.io | Description |
|---------|---------------------|-------------|
| `joinRoom()` | `join_room` | Rejoindre une salle |
| `leaveRoom()` | `leave_room` | Quitter une salle |
| `playCard()` | `play_card` | Jouer une carte |
| `makeAnnouncement()` | `make_announcement` | Faire une annonce |
| `startGame()` | `start_game` | Démarrer le jeu |
| `trickWon()` | `trick_won` | Annoncer un pli gagné |
| `roundCompleted()` | `round_completed` | Annoncer un round terminé |

### Serveur → Client

| Stream | Événement Socket.io | Description |
|--------|---------------------|-------------|
| `onPlayerJoined()` | `player_joined` | Un joueur a rejoint |
| `onPlayerLeft()` | `player_left` | Un joueur a quitté |
| `onCardPlayed()` | `card_played` | Une carte a été jouée |
| `onAnnouncementMade()` | `announcement_made` | Une annonce a été faite |
| `onGameStarted()` | `game_started` | Le jeu a démarré |
| `onTrickWon()` | `trick_won_broadcast` | Un pli a été gagné |
| `onRoundCompleted()` | `round_completed_broadcast` | Un round est terminé |
| `onError()` | `error` | Une erreur est survenue |
| `onDisconnect()` | `disconnect` | Déconnexion du serveur |

## 🔧 Configuration

### URL du serveur

**Développement local :**
```dart
await wsService.connect(serverUrl: 'ws://localhost:3000');
```

**Réseau local :**
```dart
await wsService.connect(serverUrl: 'ws://192.168.1.100:3000');
```

**Production :**
```dart
await wsService.connect(serverUrl: 'wss://your-server.com:3000');
```

### Statut de connexion

```dart
if (wsService.isConnected) {
  print('Connecté');
} else {
  print('Non connecté');
}
```

### Informations de la salle actuelle

```dart
print('Salle: ${wsService.currentRoomId}');
print('Joueur: ${wsService.currentPlayerName}');
```

## 🔒 Sécurité (Production)

### Ce qui doit être implémenté :

1. **Authentification JWT**
   - Valider le token JWT avant d'accepter les connexions
   - Utiliser Laravel Sanctum

2. **HTTPS/WSS**
   - Utiliser `wss://` au lieu de `ws://`
   - Configurer les certificats SSL

3. **Validation des données**
   - Valider toutes les données côté serveur Node.js
   - Sanitizer les inputs utilisateur

4. **Rate Limiting**
   - Limiter le nombre de requêtes par seconde
   - Bloquer les utilisateurs abusifs

5. **Chiffrement**
   - Chiffrer les données sensibles
   - Utiliser des clés de chiffrement fortes

## 🐛 Dépannage

### Le serveur ne démarre pas

```bash
# Vérifier que Node.js est installé
node --version

# Vérifier le port 3000
netstat -tulpn | grep 3000

# Installer les dépendances
cd /opt/lampp/htdocs/backendCauris/websocket-server
npm install
```

### Connexion impossible depuis Flutter

- Vérifier que le serveur est démarré
- Vérifier l'URL du serveur (utiliser l'IP, pas localhost sur mobile)
- Vérifier le firewall
- Vérifier le réseau

### Événements non reçus

- Vérifier que vous êtes dans une salle (`currentRoomId != null`)
- Vérifier les logs du serveur
- Vérifier que `isConnected == true`

## 📚 Documentation

Pour plus d'informations :
- [WEBSOCKET_SETUP.md](./WEBSOCKET_SETUP.md) - Configuration complète
- [websocket-server/README.md](./websocket-server/README.md) - Serveur Node.js
- [lib/services/websocket/README.md](../cauris_app/lib/services/websocket/README.md) - Client Flutter

## ✅ Prochaines étapes

1. ✅ Serveur Node.js/Socket.io créé
2. ✅ Événements Laravel créés
3. ✅ Client Flutter créé
4. ✅ Documentation complète
5. ⏳ Tester la connexion WebSocket
6. ⏳ Intégrer dans `GameRoomPage`
7. ⏳ Implémenter l'authentification JWT
8. ⏳ Configurer HTTPS/WSS pour la production
9. ⏳ Ajouter le reconnection automatique
10. ⏳ Ajouter le monitoring et les logs

## 📝 Notes

- Le serveur WebSocket fonctionne indépendamment de Laravel
- Les événements Laravel peuvent être utilisés pour la logique métier
- Flutter utilise `web_socket_channel` pour la compatibilité cross-platform
- Socket.io est l'implémentation native du serveur Node.js

