#!/bin/bash
# Script pour corriger les permissions Laravel

cd /opt/lampp/htdocs/backendCauris

# Créer les dossiers s'ils n'existent pas
sudo mkdir -p storage/logs
sudo mkdir -p storage/framework/cache
sudo mkdir -p storage/framework/sessions
sudo mkdir -p storage/framework/views
sudo mkdir -p bootstrap/cache

# Corriger les permissions avec sudo
sudo chmod -R 775 storage
sudo chmod -R 775 bootstrap/cache

# Créer le fichier de log s'il n'existe pas
sudo touch storage/logs/laravel.log
sudo chmod 664 storage/logs/laravel.log

# Changer le propriétaire pour que l'utilisateur actuel puisse écrire
sudo chown -R $USER:$USER storage
sudo chown -R $USER:$USER bootstrap/cache

echo "✅ Permissions corrigées !"
echo "Vous pouvez maintenant exécuter : php artisan migrate"
