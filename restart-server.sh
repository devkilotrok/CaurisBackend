#!/bin/bash
# Script pour arrêter et redémarrer le serveur Laravel proprement

echo "🛑 Arrêt des serveurs Laravel existants..."

# Trouver et arrêter tous les processus php artisan serve
pkill -f "php artisan serve" 2>/dev/null
sleep 1

# Vérifier s'il reste des processus
if pgrep -f "php artisan serve" > /dev/null; then
    echo "⚠️  Certains processus tournent toujours sous root. Exécutez:"
    echo "   sudo pkill -f 'php artisan serve'"
    exit 1
fi

echo "✅ Serveurs arrêtés"
echo ""
echo "🚀 Démarrage du serveur Laravel sur http://0.0.0.0:8000"
echo "📝 Utilisez ngrok pour exposer: ngrok http 8000"
echo ""
echo "Arrêt: Ctrl+C ou pkill -f 'php artisan serve'"
echo ""

cd "$(dirname "$0")"
php artisan serve --host=0.0.0.0 --port=8000

