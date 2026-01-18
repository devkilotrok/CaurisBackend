# 🚀 Guide d'Installation Complète - Backend CAURIS

## ✅ Ce qui est déjà fait

### 1. Structure Laravel Créée
- ✅ Projet Laravel 10 installé
- ✅ 13 modèles créés
- ✅ 6 contrôleurs créés
- ✅ Routes API définies
- ✅ Sanctum configuré
- ✅ AuthController implémenté
- ✅ Modèle User configuré avec Sanctum

### 2. Fichiers Créés
```
backendCauris/
├── app/
│   ├── Http/Controllers/API/
│   │   ├── AuthController.php    ✅ Implémenté
│   │   ├── UserController.php    ⏳ À implémenter
│   │   ├── FriendController    ⏳ À implémenter
│   │   ├── RoomController.php    ⏳ À implémenter
│   │   ├── GameController.php    ⏳ À implémenter
│   │   └── AdminController.php    ⏳ À implémenter
│   └── Models/
│       ├── User.php               ✅ Configuré avec Sanctum
│       ├── Room.php               ✅ Configuré
│       └── ...                    (11 autres modèles)
├── routes/
│   └── api.php                    ✅ Routes définies
├── database/
│   └── sql/database.sql           ✅ Base SQL complète
├── README_SETUP.md                ✅ Guide d'installation
├── API_DOCUMENTATION.md           ✅ Documentation API complète
└── GUIDE_INSTALLATION_COMPLETE.md ✅ Ce fichier
```

## 📝 Étapes d'Installation

### 1. Importer la Base de Données

**Option A : Via phpMyAdmin**
1. Ouvrir http://localhost/phpmyadmin
2. Cliquer sur "Importer"
3. Sélectionner le fichier `database.sql`
4. Cliquer sur "Exécuter"

**Option B : Via ligne de commande**
```bash
# Si mysql est dans le PATH
mysql -u root -p < /opt/lampp/htdocs/backendCauris/database.sql

# OU via XAMPP
/opt/lampp/bin/mysql -u root -p < /opt/lampp/htdocs/backendCauris/database.sql
```

### 2. Configurer .env

Modifier `/opt/lampp/htdocs/backendCauris/.env` :

```env
APP_NAME="CAURIS Backend"
APP_ENV=local
APP_KEY=base64:ieJBZb3nsvfqr8fT5HDq0/6WC0jDasJ7HzlRz9quDLc=
APP_DEBUG=true
APP_URL=http://localhost/backendCauris/public

# Base de données
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=cauris_db
DB_USERNAME=root
DB_PASSWORD=

# Sanctum
SANCTUM_STATEFUL_DOMAINS=localhost:8000
```

### 3. Lancer les Migrations

```bash
cd /opt/lampp/htdocs/backendCauris
php artisan migrate
```

### 4. Importer les Données de Test

```bash
php artisan db:seed
```

### 5. Créer un Compte Admin

```bash
php artisan tinker
```

Puis dans tinker :
```php
use App\Models\User;
$admin = User::create([
    'pseudo' => 'Admin',
    'email' => 'admin@cauris.com',
    'password_hash' => Hash::make('admin123'),
    'avatar' => '👑',
    'is_admin' => true,
    'is_active' => true,
]);
exit;
```

### 6. Tester l'API

```bash
# Health check
curl http://localhost/backendCauris/public/api/health

# Test de connexion
curl -X POST http://localhost/backendCauris/public/api/auth/login \
  -H "Content-Type: application/json" \
  -d '{"email":"lewis@cauris.com","password":"password123"}'
```

## 🔧 Configuration CORS

Ajouter dans `/opt/lampp/htdocs/backendCauris/config/cors.php` :

```php
return [
    'paths' => ['api/*', 'sanctum/csrf-cookie'],
    'allowed_methods' => ['*'],
    'allowed_origins' => ['*'],
    'allowed_origins_patterns' => [],
    'allowed_headers' => ['*'],
    'exposed_headers' => [],
    'max_age' => 0,
    'supports_credentials' => false,
];
```

## 🎯 URL d'Accès

- **API Backend** : http://localhost/backendCauris/public/api
- **Health Check** : http://localhost/backendCauris/public/api/health
- **Auth** : http://localhost/backendCauris/public/api/auth/login

## 📚 Prochaines Étapes

### À Faire Manuellement :

1. **Implémenter les Contrôleurs**
   - UserController.php
   - FriendController.php
   - RoomController.php
   - GameController.php
   - AdminController.php

2. **Configurer Sanctum**
   - Déjà fait ! Sanctum est configuré

3. **Créer les Relations Eloquent**
   - Déjà fait pour User et Room
   - À faire pour les autres modèles

4. **Ajouter les Tests**
   ```bash
   php artisan make:test AuthTest
   php artisan make:test RoomTest
   php artisan make:test GameTest
   ```

## 🚨 Important

⚠️ **Les autres contrôleurs ne sont pas encore implémentés**. Vous devrez compléter :
- UserController
- FriendController
- RoomController
- GameController
- AdminController

Consultez `API_DOCUMENTATION.md` pour voir ce que chaque endpoint doit faire.

## ✅ Vérification

Pour vérifier que tout fonctionne :

```bash
# 1. Vérifier que Laravel démarre
php artisan serve
# Puis ouvrir http://localhost:8000

# 2. Vérifier les routes
php artisan route:list

# 3. Tester l'API
curl http://localhost:8000/api/health
```

## 📞 Support

- Consultez `API_DOCUMENTATION.md` pour la documentation complète
- Consultez `README_SETUP.md` pour le guide de base
- Regardez les logs : `/opt/lampp/htdocs/backendCauris/storage/logs/laravel.log`

