# Configuration WebSocket pour Cauris

## 📋 Vue d'ensemble

Ce document explique comment configurer et utiliser le système WebSocket pour les communications temps réel dans l'application Cauris.

## 🏗️ Architecture

Le système utilise :
- **Serveur Node.js/Socket.io** : Pour les communications temps réel
- **Laravel Backend** : Pour la logique métier et les données persistantes
- **Flutter Frontend** : Pour l'interface utilisateur mobile

## 📦 Installation

### 1. Serveur WebSocket (Node.js)

```bash
cd /opt/lampp/htdocs/backendCauris/websocket-server
npm install
```

### 2. Serveur Laravel

Les packages Laravel sont déjà installés via Composer.

### 3. Application Flutter

```bash
cd ~/cauris_app
flutter pub get
```

## 🚀 Démarrage

### Démarrer le serveur WebSocket

```bash
cd /opt/lampp/htdocs/backendCauris/websocket-server
npm start
```

Le serveur démarre sur le port **3000** par défaut.

### Démarrer Laravel (si nécessaire)

```bash
cd /opt/lampp/htdocs/backendCauris
php artisan serve --host=127.0.0.1 --port=8000
```

### Démarrer Flutter

```bash
cd ~/cauris_app
flutter run
```

## 📡 Événements WebSocket

### Événements émis par le client (Flutter → Serveur)

| Événement | Description | Données |
|-----------|-------------|---------|
| `join_room` | Rejoindre une salle | `{roomId, playerName}` |
| `leave_room` | Quitter une salle | `{roomId, playerName}` |
| `play_card` | Jouer une carte | `{roomId, playerName, card, trickNumber}` |
| `make_announcement` | Faire une annonce | `{roomId, playerName, announcement}` |
| `start_game` | Démarrer le jeu | `{roomId, players}` |
| `trick_won` | Annoncer qu'un pli est gagné | `{roomId, winnerName, trickNumber}` |
| `round_completed` | Annoncer qu'un round est terminé | `{roomId, roundNumber, scores}` |

### Événements reçus par le client (Serveur → Flutter)

| Événement | Description | Données |
|-----------|-------------|---------|
| `player_joined` | Un joueur a rejoint | `{playerName, roomId, players[]}` |
| `player_left` | Un joueur a quitté | `{playerName, roomId, players[]}` |
| `card_played` | Une carte a été jouée | `{playerName, card, trickNumber}` |
| `announcement_made` | Une annonce a été faite | `{playerName, announcement}` |
| `game_started` | Le jeu a commencé | `{roomId, players[]}` |
| `trick_won_broadcast` | Un pli a été gagné | `{winnerName, trickNumber}` |
| `round_completed_broadcast` | Un round est terminé | `{roundNumber, scores{}}` |
| `error` | Une erreur est survenue | `{message}` |
| `disconnect` | Déconnexion du serveur | - |

## 💻 Utilisation dans Flutter

### Exemple d'utilisation basique

```dart
import 'package:cauris_app/services/websocket/game_websocket_service.dart';

// Créer une instance du service
final wsService = GameWebSocketService();

// Se connecter
await wsService.connect(serverUrl: 'ws://192.168.1.100:3000');

// Rejoindre une salle
await wsService.joinRoom('room123', 'Lewis');

// Écouter les événements
wsService.onPlayerJoined().listen((data) {
  print('Nouveau joueur: ${data['playerName']}');
});

wsService.onCardPlayed().listen((data) {
  print('${data['playerName']} a joué ${data['card']}');
});

// Jouer une carte
await wsService.playCard(
  cardSuit: 'spades',
  cardValue: 'A',
  trickNumber: 1,
);

// Faire une annonce
await wsService.makeAnnouncement(3);

// Se déconnecter
await wsService.disconnect();
```

### Intégration dans GameRoomPage

```dart
class _GameRoomPageState extends State<GameRoomPage> {
  final _wsService = GameWebSocketService();
  
  @override
  void initState() {
    super.initState();
    _initializeWebSocket();
  }
  
  Future<void> _initializeWebSocket() async {
    // Se connecter
    await _wsService.connect();
    
    // Rejoindre la salle
    await _wsService.joinRoom(
      widget.roomId,
      UserManager.instance.getUserPseudo(),
    );
    
    // Écouter les événements
    _wsService.onCardPlayed().listen(_handleCardPlayed);
    _wsService.onAnnouncementMade().listen(_handleAnnouncement);
    _wsService.onGameStarted().listen(_handleGameStart);
  }
  
  void _handleCardPlayed(Map<String, dynamic> data) {
    // Mettre à jour l'UI quand une carte est jouée
    setState(() {
      // ...
    });
  }
  
  void _handleAnnouncement(Map<String, dynamic> data) {
    // Mettre à jour les annonces
    setState(() {
      // ...
    });
  }
  
  void _handleGameStart(Map<String, dynamic> data) {
    // Démarrer le jeu
    setState(() {
      // ...
    });
  }
  
  @override
  void dispose() {
    _wsService.disconnect();
    super.dispose();
  }
}
```

## 🔒 Sécurité (Production)

### À implémenter avant le déploiement :

1. **Authentification JWT**
   - Valider le token JWT avant d'accepter les connexions
   - Utiliser Laravel Sanctum pour générer les tokens

2. **HTTPS/WSS**
   - Utiliser `wss://` au lieu de `ws://`
   - Configurer les certificats SSL

3. **Validation des données**
   - Valider toutes les données côté serveur
   - Sanitizer les inputs utilisateur

4. **Rate Limiting**
   - Limiter le nombre de requêtes par seconde
   - Bloquer les utilisateurs abusifs

5. **Chiffrement**
   - Chiffrer les données sensibles
   - Utiliser des clés de chiffrement fortes

## 🐛 Dépannage

### Le serveur WebSocket ne démarre pas

```bash
# Vérifier que Node.js est installé
node --version

# Vérifier le port
netstat -tulpn | grep 3000
```

### Connexion impossible depuis Flutter

- Vérifier l'URL du serveur (utiliser l'IP de la machine, pas localhost)
- Vérifier le firewall
- Vérifier que le serveur est démarré

### Événements non reçus

- Vérifier que vous êtes bien dans la même salle (`roomId`)
- Vérifier les logs du serveur
- Vérifier que le service est bien connecté

## 📚 Ressources

- [Socket.io Documentation](https://socket.io/docs/)
- [Web Socket Channel (Flutter)](https://pub.dev/packages/web_socket_channel)
- [Laravel Broadcasting](https://laravel.com/docs/broadcasting)

## 🎯 Prochaines étapes

1. Implémenter l'authentification JWT
2. Ajouter les tests unitaires
3. Configurer HTTPS/WSS pour la production
4. Implémenter le reconnection automatique
5. Ajouter le monitoring et les logs

