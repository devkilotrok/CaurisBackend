# 🎯 Backend CAURIS - Laravel API

## 🚀 Installation Complète

### 1️⃣ Import de la Base de Données

```bash
# Importer le fichier SQL dans MySQL
mysql -u root -p < database.sql
```

OU via phpMyAdmin :
1. Ouvrir http://localhost/phpmyadmin
2. Créer une nouvelle base "cauris_db"
3. Importer le fichier `database.sql`

### 2️⃣ Configuration de l'Environnement

```bash
cd /opt/lampp/htdocs/backendCauris
cp .env.example .env
php artisan key:generate
```

Éditer le fichier `.env` :

```env
APP_NAME=CAURIS Backend
APP_URL=http://localhost/backendCauris/public

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=cauris_db
DB_USERNAME=root
DB_PASSWORD=

SANCTUM_STATEFUL_DOMAINS=localhost:8000
```

### 3️⃣ Migration des Tables

```bash
php artisan migrate
```

### 4️⃣ Installation des Dépendances (déjà fait)

```bash
composer install
```

### 5️⃣ Création des Contrôleurs API

```bash
php artisan make:controller API/AuthController
php artisan make:controller API/UserController
php artisan make:controller API/FriendController
php artisan make:controller API/RoomController
php artisan make:controller API/GameController
php artisan make:controller API/AdminController
```

## 📁 Structure du Projet

```
backendCauris/
├── app/
│   ├── Http/
│   │   ├── Controllers/
│   │   │   └── API/
│   │   │       ├── AuthController.php
│   │   │       ├── UserController.php
│   │   │       ├── FriendController.php
│   │   │       ├── RoomController.php
│   │   │       ├── GameController.php
│   │   │       └── AdminController.php
│   │   └── Middleware/
│   ├── Models/
│   │   ├── User.php
│   │   ├── Friendship.php
│   │   ├── FriendRequest.php
│   │   ├── Room.php
│   │   ├── RoomPlayer.php
│   │   ├── Game.php
│   │   ├── Announcement.php
│   │   ├── Round.php
│   │   ├── Trick.php
│   │   ├── PlayedCard.php
│   │   ├── Score.php
│   │   ├── RoomInvitation.php
│   │   ├── UserSetting.php
│   │   └── AdminLog.php
│   └── Services/
│       ├── GameService.php
│       ├── CardService.php
│       └── ScoreService.php
├── config/
│   └── cors.php
├── database/
│   ├── migrations/
│   └── seeds/
├── routes/
│   └── api.php
├── .env
└── README_SETUP.md
```

## 🔗 URLs d'Accès

- **Backend API** : http://localhost/backendCauris/public/api
- **Panel Admin** : http://localhost/backendCauris/public/admin
- **phpMyAdmin** : http://localhost/phpmyadmin

## 📚 Documentation API

Voir le fichier `API_DOCUMENTATION.md` pour la documentation complète de toutes les API.

## 🧪 Test des API

Utiliser **Postman** ou **Insomnia** pour tester les endpoints.

### Test Basique :

```bash
curl http://localhost/backendCauris/public/api/health
```

## 🛠️ Commandes Utiles

```bash
# Vider le cache
php artisan cache:clear
php artisan config:clear
php artisan route:clear

# Régénérer l'autoload
composer dump-autoload

# Lancer les tests
php artisan test
```

