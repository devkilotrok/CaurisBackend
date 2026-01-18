# BackendCauris

Backend API pour l'application CAURIS - Jeu de cartes traditionnel africain.

## 🚀 Technologies

- **Framework**: Laravel 10
- **Base de données**: PostgreSQL
- **Authentification**: Laravel Sanctum
- **Paiements**: FedaPay
- **Déploiement**: Docker (Render)

## 📋 Prérequis

- PHP 8.1+
- PostgreSQL 13+
- Composer
- Docker (pour déploiement)

## 🛠️ Installation locale

```bash
# Cloner le projet
git clone https://github.com/VOTRE_USERNAME/backendCauris.git
cd backendCauris

# Installer les dépendances
composer install

# Copier le fichier d'environnement
cp .env.example .env

# Générer la clé d'application
php artisan key:generate

# Configurer la base de données dans .env
# DB_CONNECTION=pgsql
# DB_HOST=127.0.0.1
# DB_PORT=5432
# DB_DATABASE=backendcauris
# DB_USERNAME=postgres
# DB_PASSWORD=votre_mot_de_passe

# Exécuter les migrations
php artisan migrate

# Démarrer le serveur
php artisan serve
```

## 🌐 Déploiement sur Render

Consultez le fichier [DEPLOYMENT.md](DEPLOYMENT.md) pour les instructions détaillées de déploiement sur Render.

### Déploiement rapide

1. Créez un dépôt GitHub et poussez le code
2. Connectez-vous à [Render](https://render.com)
3. Créez un nouveau Blueprint depuis votre dépôt
4. Render détectera automatiquement `render.yaml` et créera :
   - Une base de données PostgreSQL
   - Un service web avec Docker
   - Toutes les variables d'environnement

## 📚 API Endpoints

### Authentification
- `POST /api/login` - Connexion
- `POST /api/auth/register` - Inscription
- `POST /api/auth/logout` - Déconnexion

### Utilisateurs
- `GET /api/user/profile` - Profil utilisateur
- `PUT /api/user/profile/update` - Mise à jour du profil
- `GET /api/user/stats` - Statistiques

### Jeu
- `GET /api/rooms` - Liste des salles
- `POST /api/rooms/create` - Créer une salle
- `POST /api/rooms/join` - Rejoindre une salle
- `POST /api/games/{game_id}/play-card` - Jouer une carte

### Paiements
- `GET /api/payment/balance` - Solde du compte
- `POST /api/payment/deposit` - Dépôt
- `POST /api/payment/withdraw` - Retrait

### Health Check
- `GET /api/health` - Vérifier l'état de l'API

## 🔐 Sécurité

- Toutes les routes API (sauf `/login`, `/register`, `/contact`, `/health`) nécessitent une authentification via Laravel Sanctum
- Les mots de passe sont hashés avec bcrypt
- Les tokens d'API sont stockés de manière sécurisée
- CORS configuré pour les domaines autorisés

## 📝 Variables d'environnement

Variables essentielles à configurer :

```env
APP_NAME=BackendCauris
APP_ENV=production
APP_KEY=base64:...
APP_DEBUG=false
APP_URL=https://votre-app.onrender.com

DB_CONNECTION=pgsql
DB_HOST=...
DB_PORT=5432
DB_DATABASE=backendcauris
DB_USERNAME=...
DB_PASSWORD=...

# FedaPay (Paiements)
FEDAPAY_API_KEY=...
FEDAPAY_ENVIRONMENT=live
```

## 🧪 Tests

```bash
# Exécuter les tests
php artisan test
```

## 📄 Licence

Ce projet est sous licence MIT.

## 👥 Support

Pour toute question ou problème, contactez l'équipe de développement.
