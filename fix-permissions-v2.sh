#!/bin/bash
# Script pour corriger les permissions Laravel (version améliorée)

cd /opt/lampp/htdocs/backendCauris

echo "🔧 Correction des permissions Laravel..."

# Supprimer le fichier de log s'il existe (pour le recréer avec les bonnes permissions)
sudo rm -f storage/logs/laravel.log

# Créer les dossiers s'ils n'existent pas
sudo mkdir -p storage/logs
sudo mkdir -p storage/framework/cache
sudo mkdir -p storage/framework/sessions
sudo mkdir -p storage/framework/views
sudo mkdir -p bootstrap/cache

# Changer le propriétaire AVANT de créer le fichier
sudo chown -R $USER:$USER storage
sudo chown -R $USER:$USER bootstrap/cache

# Créer le fichier de log avec l'utilisateur actuel (pas root)
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

