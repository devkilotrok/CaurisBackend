#!/bin/bash
# Script pour démarrer le serveur Laravel avec php artisan serve

echo "🚀 Démarrage du serveur Laravel sur http://0.0.0.0:8000"
echo "📝 Utilisez ngrok pour exposer: ngrok http 8000"
echo ""
echo "Arrêt: Ctrl+C ou killall php"
echo ""

cd "$(dirname "$0")"
php artisan serve --host=0.0.0.0 --port=8000

