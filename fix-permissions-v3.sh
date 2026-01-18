#!/bin/bash
# Script pour corriger les permissions Laravel (version finale)

cd /opt/lampp/htdocs/backendCauris

echo "🔧 Correction des permissions Laravel..."

# Supprimer le fichier de log s'il existe
sudo rm -f storage/logs/laravel.log

# Créer les dossiers s'ils n'existent pas
mkdir -p storage/logs
mkdir -p storage/framework/cache
mkdir -p storage/framework/sessions
mkdir -p storage/framework/views
mkdir -p bootstrap/cache

# Changer le propriétaire de TOUS les fichiers storage
sudo chown -R $USER:$USER storage
sudo chown -R $USER:$USER bootstrap/cache

# Créer le fichier de log avec l'utilisateur actuel
touch storage/logs/laravel.log

# Corriger les permissions
chmod -R 775 storage
chmod -R 775 bootstrap/cache
chmod 664 storage/logs/laravel.log

# Vérifier les permissions
echo ""
echo "📋 Vérification des permissions :"
ls -la storage/logs/ | grep laravel.log
echo ""
echo "✅ Permissions corrigées !"
echo "Vous pouvez maintenant exécuter : php artisan migrate"

