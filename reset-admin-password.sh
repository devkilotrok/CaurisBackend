#!/bin/bash
# Script pour réinitialiser le mot de passe du superAdmin

echo "🔐 Réinitialisation du mot de passe du superAdmin"
echo ""
read -sp "Entrez le nouveau mot de passe: " NEW_PASSWORD
echo ""
read -sp "Confirmez le mot de passe: " CONFIRM_PASSWORD
echo ""

if [ "$NEW_PASSWORD" != "$CONFIRM_PASSWORD" ]; then
    echo "❌ Les mots de passe ne correspondent pas"
    exit 1
fi

if [ -z "$NEW_PASSWORD" ]; then
    echo "❌ Le mot de passe ne peut pas être vide"
    exit 1
fi

cd "$(dirname "$0")"

# Utiliser PHP pour hasher le mot de passe et mettre à jour
php artisan tinker --execute="
use Illuminate\Support\Facades\Hash;
use App\Models\User;

\$user = User::where('pseudo', 'superAdmin')->orWhere('email', 'superadmin@cauris.com')->first();
if (\$user) {
    \$user->password_hash = Hash::make('$NEW_PASSWORD');
    \$user->is_active = true;
    \$user->save();
    echo '✅ Mot de passe mis à jour pour: ' . \$user->pseudo . PHP_EOL;
} else {
    echo '❌ Utilisateur superAdmin non trouvé' . PHP_EOL;
    exit(1);
}
"

if [ $? -eq 0 ]; then
    echo ""
    echo "✅ Mot de passe réinitialisé avec succès!"
    echo "📝 Vous pouvez maintenant vous connecter avec:"
    echo "   Pseudo/Email: superAdmin"
    echo "   Mot de passe: (celui que vous venez d'entrer)"
else
    echo ""
    echo "❌ Erreur lors de la réinitialisation"
fi

