#!/bin/bash

# Configuration
APP_DIR="/var/www/html/app" # Chemin sur le VPS si différent, ou . si exécuté dans le dossier
DOCKER_BIN=$(which docker)
COMPOSE_BIN=$(which docker-compose)

echo "🚀 Démarrage du déploiement..."

# 1. Vérifications préliminaires
if [ ! -f .env ]; then
    echo "❌ Erreur: Fichier .env manquant !"
    exit 1
fi

# 2. Préparation des dossiers et SSL
echo "🔧 Préparation des dossiers..."
mkdir -p docker/ssl storage/framework/{cache,sessions,views} bootstrap/cache

if [ ! -f docker/ssl/nginx.crt ]; then
    echo "🔐 Génération certificat SSL auto-signé temporaire..."
    openssl req -x509 -nodes -days 365 -newkey rsa:2048 \
        -keyout docker/ssl/nginx.key \
        -out docker/ssl/nginx.crt \
        -subj "/C=FR/ST=Paris/L=Paris/O=Cauris/CN=localhost"
fi

# 3. Build & Lancement des conteneurs
echo "🐳 Construction et démarrage des conteneurs..."
$COMPOSE_BIN down --remove-orphans
$COMPOSE_BIN up -d --build

# 4. Attente de la base de données
echo "⏳ Attente de MySQL..."
sleep 15

# 5. Opérations Laravel
echo "🛠 Exécution des tâches Laravel..."
$COMPOSE_BIN exec -T app php artisan migrate --force
$COMPOSE_BIN exec -T app php artisan config:cache
$COMPOSE_BIN exec -T app php artisan route:cache
$COMPOSE_BIN exec -T app php artisan view:cache
$COMPOSE_BIN exec -T app php artisan event:cache

# 6. Permissions
echo "🔑 Correction des permissions..."
$COMPOSE_BIN exec -T app chown -R www-data:www-data storage bootstrap/cache

echo "✅ Déploiement terminé avec succès !"
echo "👉 Site accessible sur: https://votre-ip-ou-domaine"
