# 📡 Documentation API - CAURIS Backend

## Base URL
```
http://localhost/backendCauris/public/api
```

## 🔐 Authentification

Toutes les requêtes nécessitent un token JWT (sauf inscription/connexion).

### Headers Requis
```
Authorization: Bearer {token}
Content-Type: application/json
Accept: application/json
```

---

## 📍 Endpoints API

### 👤 Authentification

#### 1. Inscription
```http
POST /api/register
```

**Body:**
```json
{
  "pseudo": "Lewis",
  "email": "lewis@cauris.com",
  "password": "password123",
  "avatar": "👤"
}
```

**Response:**
```json
{
  "success": true,
  "data": {
    "user": {
      "user_id": 1,
      "pseudo": "Lewis",
      "email": "lewis@cauris.com",
      "avatar": "👤"
    },
    "token": "eyJ0eXAiOiJKV1QiLCJhbGc..."
  },
  "message": "Inscription réussie"
}
```

#### 2. Connexion
```http
POST /api/login
```

**Body:**
```json
{
  "email": "lewis@cauris.com",
  "password": "password123"
}
```

**Response:**
```json
{
  "success": true,
  "data": {
    "user": {
      "user_id": 1,
      "pseudo": "Lewis",
      "email": "lewis@cauris.com"
    },
    "token": "eyJ0eXAiOiJKV1QiLCJhbGc..."
  }
}
```

#### 3. Déconnexion
```http
POST /api/logout
```
(Requiert token)

---

### 👥 Gestion des Utilisateurs

#### 1. Profil Utilisateur
```http
GET /api/user/profile
```
(Requiert token)

**Response:**
```json
{
  "success": true,
  "data": {
    "user_id": 1,
    "pseudo": "Lewis",
    "email": "lewis@cauris.com",
    "avatar": "👤",
    "theme_preference": "light",
    "stats": {
      "total_games": 42,
      "games_won": 25,
      "avg_score": 87.5,
      "best_score": 150
    }
  }
}
```

#### 2. Mettre à jour le profil
```http
PUT /api/user/profile
```

**Body:**
```json
{
  "pseudo": "Lewis2",
  "avatar": "👑",
  "theme_preference": "dark"
}
```

#### 3. Liste des utilisateurs (pour recherche)
```http
GET /api/users/search?query=lewis
```

---

### 👫 Gestion des Amis

#### 1. Envoyer une demande d'amitié
```http
POST /api/friends/request
```

**Body:**
```json
{
  "friend_id": 3
}
```

#### 2. Accepter une demande d'amitié
```http
POST /api/friends/accept/{request_id}
```

#### 3. Refuser une demande d'amitié
```http
POST /api/friends/reject/{request_id}
```

#### 4. Liste des amis
```http
GET /api/friends
```

**Response:**
```json
{
  "success": true,
  "data": [
    {
      "friend_id": 2,
      "pseudo": "Bil",
      "avatar": "🤖",
      "status": "online"
    }
  ]
}
```

#### 5. Rechercher des amis
```http
GET /api/friends/search?query=bil
```

#### 6. Inviter un ami à rejoindre une salle
```http
POST /api/friends/invite-to-room
```

**Body:**
```json
{
  "friend_id": 2,
  "room_id": 1,
  "message": "Viens jouer avec moi !"
}
```

---

### 🎮 Gestion des Salles

#### 1. Créer une salle
```http
POST /api/rooms/create
```

**Body:**
```json
{
  "room_name": "Room 1",
  "minimum_bet": 50
}
```

**Response:**
```json
{
  "success": true,
  "data": {
    "room_id": 1,
    "room_name": "Room 1",
    "room_code": "ABC123",
    "minimum_bet": 50,
    "status": "waiting"
  }
}
```

#### 2. Rejoindre une salle
```http
POST /api/rooms/join
```

**Body:**
```json
{
  "room_code": "ABC123"
}
```

#### 3. Liste des salles disponibles
```http
GET /api/rooms
```

#### 4. Détails d'une salle
```http
GET /api/rooms/{room_id}
```

**Response:**
```json
{
  "success": true,
  "data": {
    "room_id": 1,
    "room_name": "Room 1",
    "room_code": "ABC123",
    "players": [
      {"user_id": 1, "pseudo": "Lewis", "position": 1},
      {"user_id": 2, "pseudo": "Bil", "position": 2}
    ],
    "status": "waiting"
  }
}
```

#### 5. Quitter une salle
```http
POST /api/rooms/{room_id}/leave
```

#### 6. Démarrer une partie
```http
POST /api/rooms/{room_id}/start
```
(Démarre automatiquement quand 4 joueurs sont prêts)

---

### 🎲 Gestion des Parties

#### 1. Mélanger et distribuer les cartes
```http
POST /api/games/{game_id}/deal-cards
```

**Response:**
```json
{
  "success": true,
  "data": {
    "deck_id": "xyz123",
    "cards": {
      "player_1": [...],
      "player_2": [...],
      "player_3": [...],
      "player_4": [...]
    }
  }
}
```

#### 2. Faire une annonce
```http
POST /api/games/{game_id}/announce
```

**Body:**
```json
{
  "round_number": 1,
  "announcement_value": 5
}
```

#### 3. Jouer une carte
```http
POST /api/games/{game_id}/play-card
```

**Body:**
```json
{
  "round_id": 1,
  "trick_id": 1,
  "card_code": "AS"
}
```

#### 4. Obtenir le tour actuel
```http
GET /api/games/{game_id}/current-turn
```

**Response:**
```json
{
  "success": true,
  "data": {
    "current_player_id": 1,
    "current_player_pseudo": "Lewis",
    "phase": "announcements",
    "round_number": 1
  }
}
```

#### 5. Tableau des scores
```http
GET /api/games/{game_id}/scores
```

**Response:**
```json
{
  "success": true,
  "data": {
    "rounds": [
      {
        "round": 1,
        "scores": [10, 20, 15, 5]
      }
    ],
    "global_scores": [10, 20, 15, 5]
  }
}
```

#### 6. Historique des parties
```http
GET /api/games/history
```

---

### 🔧 Panel Admin (Web Interface)

#### 1. Tableau de bord
```http
GET /api/admin/dashboard
```

**Response:**
```json
{
  "success": true,
  "data": {
    "total_users": 150,
    "total_rooms": 25,
    "total_games": 180,
    "active_users": 42
  }
}
```

#### 2. Liste des utilisateurs
```http
GET /api/admin/users
```

#### 3. Détails d'un utilisateur
```http
GET /api/admin/users/{user_id}
```

#### 4. Bloquer/Débloquer un utilisateur
```http
POST /api/admin/users/{user_id}/toggle-status
```

#### 5. Liste des salles
```http
GET /api/admin/rooms
```

#### 6. Logs d'administration
```http
GET /api/admin/logs
```

---

## 📝 Codes de Réponse HTTP

- `200` - Succès
- `201` - Créé avec succès
- `400` - Requête invalide
- `401` - Non authentifié
- `403` - Accès refusé
- `404` - Ressource non trouvée
- `500` - Erreur serveur

---

## 🔒 Sécurité

- Tous les endpoints API nécessitent une authentification JWT
- Les mots de passe sont hashés avec bcrypt
- CORS configuré pour les domaines autorisés
- Rate limiting appliqué sur les endpoints sensibles

---

## 🧪 Exemples d'Utilisation

### Flutter App Mobile

```dart
// Exemple de connexion
final response = await http.post(
  Uri.parse('http://localhost/backendCauris/public/api/login'),
  headers: {
    'Content-Type': 'application/json',
    'Accept': 'application/json',
  },
  body: jsonEncode({
    'email': 'lewis@cauris.com',
    'password': 'password123',
  }),
);

final data = jsonDecode(response.body);
final token = data['data']['token'];
```

### Panel Admin (Vue.js)

```javascript
// Exemple de récupération des statistiques
axios.get('http://localhost/backendCauris/public/api/admin/dashboard', {
  headers: {
    'Authorization': `Bearer ${token}`,
    'Accept': 'application/json',
  }
})
.then(response => {
  console.log(response.data);
});
```

---

## 📞 Support

Pour toute question ou problème, consultez le fichier `README_SETUP.md` ou contactez l'équipe de développement.

