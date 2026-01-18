# Déploiement sur Render

Ce guide vous explique comment déployer le backend BackendCauris sur Render avec PostgreSQL.

## Prérequis

- Un compte GitHub
- Un compte Render (gratuit sur https://render.com)
- Le code poussé sur GitHub

## Étape 1 : Créer un dépôt GitHub

1. Allez sur https://github.com/new
2. Nommez votre dépôt (ex: `backendCauris`)
3. Choisissez **Public** ou **Private**
4. **NE PAS** initialiser avec README, .gitignore ou licence
5. Cliquez sur **Create repository**

## Étape 2 : Pousser le code sur GitHub

Copiez l'URL de votre dépôt GitHub, puis exécutez :

```bash
cd /opt/lampp/htdocs/backendCauris
git branch -M main
git remote add origin https://github.com/VOTRE_USERNAME/VOTRE_REPO.git
git add .
git commit -m "Initial commit - Backend Cauris"
git push -u origin main
```

## Étape 3 : Déployer sur Render

### 3.1 Créer le service depuis le Blueprint

1. Connectez-vous à https://dashboard.render.com
2. Cliquez sur **New +** → **Blueprint**
3. Connectez votre dépôt GitHub
4. Render détectera automatiquement le fichier `render.yaml`
5. Cliquez sur **Apply**

Render va automatiquement créer :
- ✅ Une base de données PostgreSQL (gratuite)
- ✅ Un service web avec Docker
- ✅ Toutes les variables d'environnement nécessaires

### 3.2 Configuration manuelle (alternative)

Si vous préférez configurer manuellement :

#### A. Créer la base de données PostgreSQL

1. Cliquez sur **New +** → **PostgreSQL**
2. Nom : `backendcauris-db`
3. Database : `backendcauris`
4. Plan : **Free**
5. Cliquez sur **Create Database**

#### B. Créer le service web

1. Cliquez sur **New +** → **Web Service**
2. Connectez votre dépôt GitHub
3. Configurez :
   - **Name** : `backendcauris`
   - **Runtime** : `Docker`
   - **Plan** : `Free`
   - **Health Check Path** : `/api/health`

#### C. Variables d'environnement

Ajoutez ces variables dans l'onglet **Environment** :

| Variable | Valeur |
|----------|--------|
| `APP_NAME` | `BackendCauris` |
| `APP_ENV` | `production` |
| `APP_DEBUG` | `false` |
| `APP_KEY` | Générer avec `php artisan key:generate --show` |
| `APP_URL` | URL de votre service Render (ex: `https://backendcauris.onrender.com`) |
| `LOG_CHANNEL` | `stack` |
| `LOG_LEVEL` | `error` |
| `DB_CONNECTION` | `pgsql` |
| `DB_HOST` | Copier depuis la base de données (Internal Database URL - Host) |
| `DB_PORT` | `5432` |
| `DB_DATABASE` | `backendcauris` |
| `DB_USERNAME` | Copier depuis la base de données |
| `DB_PASSWORD` | Copier depuis la base de données |
| `CACHE_DRIVER` | `file` |
| `QUEUE_CONNECTION` | `sync` |
| `SESSION_DRIVER` | `file` |
| `SESSION_LIFETIME` | `120` |

## Étape 4 : Exécuter les migrations

Une fois le déploiement terminé :

1. Allez dans votre service web sur Render
2. Cliquez sur **Shell** dans le menu de gauche
3. Exécutez :

```bash
php artisan migrate --force
php artisan db:seed --force  # Si vous avez des seeders
```

## Étape 5 : Vérifier le déploiement

Visitez votre URL Render :
- **Health Check** : `https://votre-app.onrender.com/api/health`
- **API** : `https://votre-app.onrender.com/api/`

## Notes importantes

### ⚠️ Plan gratuit Render

- Le service s'endort après 15 minutes d'inactivité
- Premier démarrage peut prendre 30-60 secondes
- Base de données PostgreSQL gratuite : 90 jours, puis supprimée si non utilisée

### 🔒 Sécurité

- **APP_KEY** : Ne jamais le partager ou le commiter
- **APP_DEBUG** : Toujours `false` en production
- Variables sensibles : Toujours dans les variables d'environnement Render

### 🔄 Redéploiement automatique

Render redéploie automatiquement à chaque push sur la branche `main` de GitHub.

### 📝 Logs

Pour voir les logs :
1. Allez dans votre service sur Render
2. Cliquez sur **Logs** dans le menu de gauche

## Dépannage

### Le service ne démarre pas

1. Vérifiez les logs sur Render
2. Assurez-vous que `APP_KEY` est défini
3. Vérifiez que les variables de base de données sont correctes

### Erreur de connexion à la base de données

1. Utilisez l'**Internal Database URL** (pas l'External)
2. Vérifiez que `DB_CONNECTION=pgsql`
3. Assurez-vous que la base de données est créée

### Migrations échouent

```bash
# Dans le Shell Render
php artisan migrate:fresh --force
```

## Support

Pour plus d'informations :
- Documentation Render : https://render.com/docs
- Documentation Laravel : https://laravel.com/docs
