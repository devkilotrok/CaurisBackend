#!/bin/bash

# Script pour trouver les problèmes de double animation dans Flutter
# Usage: ./find_flutter_issues.sh /path/to/flutter/project

FLUTTER_PROJECT_PATH="${1:-.}"

echo "🔍 Recherche des problèmes de double animation dans Flutter..."
echo "📁 Chemin du projet: $FLUTTER_PROJECT_PATH"
echo ""

# Couleurs
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

echo -e "${YELLOW}=== 1. Recherche de socket.on('play_card') ===${NC}"
grep -rn "socket\.on.*play_card\|socket\.on.*'play_card'\|socket\.on.*\"play_card\"" "$FLUTTER_PROJECT_PATH/lib/" 2>/dev/null || echo "✅ Aucun trouvé"

echo ""
echo -e "${YELLOW}=== 2. Recherche de socket.emit('play_card') ===${NC}"
grep -rn "socket\.emit.*play_card\|socket\.emit.*'play_card'\|socket\.emit.*\"play_card\"" "$FLUTTER_PROJECT_PATH/lib/" 2>/dev/null || echo "✅ Aucun trouvé"

echo ""
echo -e "${YELLOW}=== 3. Recherche de onPlayCard() ===${NC}"
grep -rn "onPlayCard\|on_play_card" "$FLUTTER_PROJECT_PATH/lib/" 2>/dev/null || echo "✅ Aucun trouvé"

echo ""
echo -e "${YELLOW}=== 4. Recherche de socket.on('card_played') (À GARDER) ===${NC}"
grep -rn "socket\.on.*card_played\|socket\.on.*'card_played'\|socket\.on.*\"card_played\"" "$FLUTTER_PROJECT_PATH/lib/" 2>/dev/null || echo -e "${RED}⚠️ Aucun trouvé - Il faut ajouter cet écouteur${NC}"

echo ""
echo -e "${YELLOW}=== 5. Recherche de playCard() dans les services ===${NC}"
grep -rn "Future.*playCard\|void.*playCard\|playCard.*async" "$FLUTTER_PROJECT_PATH/lib/" 2>/dev/null | head -20

echo ""
echo -e "${YELLOW}=== 6. Recherche de fichiers WebSocket ===${NC}"
find "$FLUTTER_PROJECT_PATH/lib/" -name "*websocket*.dart" -o -name "*socket*.dart" 2>/dev/null | head -10

echo ""
echo -e "${GREEN}=== Résumé ===${NC}"
echo "✅ Recherche terminée"
echo ""
echo "📝 Actions à faire :"
echo "1. Supprimer tous les socket.on('play_card', ...)"
echo "2. Supprimer tous les socket.emit('play_card', ...)"
echo "3. Garder uniquement socket.on('card_played', ...)"
echo "4. Modifier playCard() pour appeler uniquement l'API Laravel"
echo ""
echo "📖 Voir FLUTTER_CORRECTIONS_DETAILLEES.md pour les exemples de code"



