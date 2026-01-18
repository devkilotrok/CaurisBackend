#!/bin/bash
# Script pour arrêter le serveur Laravel

echo "🛑 Arrêt du serveur Laravel..."

# Arrêter les processus sous l'utilisateur courant
pkill -f "php artisan serve" 2>/dev/null

if [ $? -eq 0 ]; then
    echo "✅ Serveur arrêté"
else
    echo "⚠️  Aucun serveur trouvé sous votre utilisateur"
    echo "   Si un serveur tourne sous root, exécutez: sudo pkill -f 'php artisan serve'"
fi

