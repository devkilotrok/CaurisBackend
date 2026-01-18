# Intégration FedaPay - Guide Complet

## 📋 Vue d'ensemble

Ce document explique comment configurer et utiliser l'intégration FedaPay pour les dépôts d'utilisateurs dans l'application Cauris.

## 🔧 Configuration Backend

### 1. Installation du package FedaPay

Le package a été ajouté à `composer.json`. Pour l'installer :

```bash
cd /opt/lampp/htdocs/backendCauris
composer install
```

### 2. Configuration des variables d'environnement

Ajoutez les variables suivantes dans votre fichier `.env` :

```env
# FedaPay Configuration
FEDAPAY_API_KEY=votre_cle_api_secrete
FEDAPAY_ENVIRONMENT=sandbox  # ou 'live' pour la production
FEDAPAY_WEBHOOK_SECRET=votre_secret_webhook  # Optionnel mais recommandé
```

**Où obtenir vos clés API :**
1. Connectez-vous à votre tableau de bord FedaPay
2. Allez dans "Paramètres" > "API"
3. Copiez votre clé API secrète
4. Pour le webhook secret, configurez-le dans les paramètres de webhook

### 3. Migration de la base de données

Exécutez la migration pour ajouter les colonnes FedaPay :

```bash
mysql -u votre_user -p votre_database < database/migration_add_fedapay.sql
```

Ou via MySQL :

```sql
SOURCE /opt/lampp/htdocs/backendCauris/database/migration_add_fedapay.sql;
```

### 4. Configuration du webhook

Dans votre tableau de bord FedaPay :
1. Allez dans "Paramètres" > "Webhooks"
2. Ajoutez une nouvelle URL de webhook : `https://backend.caurisdg-callbreak.com/api/payment/fedapay-webhook`
3. Sélectionnez les événements : `transaction.approved`, `transaction.paid`, `transaction.canceled`, `transaction.failed`
4. Copiez le secret du webhook et ajoutez-le dans `.env` comme `FEDAPAY_WEBHOOK_SECRET`

## 📱 Configuration Frontend

### 1. Installation des dépendances

```bash
cd /home/adolphe/cauris_app
flutter pub get
```

Le package `url_launcher` a été ajouté pour ouvrir l'URL de paiement FedaPay.

## 🔄 Fonctionnement

### Flux de dépôt avec FedaPay

1. **L'utilisateur saisit le montant et son numéro de téléphone**
   - Montant minimum : 100 FCFA
   - Conversion : 10 cauris = 1000 FCFA

2. **Le backend crée une transaction FedaPay**
   - Une transaction est créée dans la base de données avec le statut `en_attente`
   - Une transaction FedaPay est créée via l'API
   - L'URL de paiement est retournée au frontend

3. **L'utilisateur est redirigé vers FedaPay**
   - Le frontend ouvre l'URL de paiement dans le navigateur
   - L'utilisateur complète le paiement sur la plateforme FedaPay

4. **FedaPay envoie un webhook au backend**
   - Lorsque le paiement est approuvé, FedaPay envoie une notification
   - Le backend valide automatiquement la transaction
   - Le compte utilisateur est crédité
   - Le compte entreprise est débité

### Statuts des transactions

- `pending` : Transaction créée, en attente de paiement
- `approved` / `paid` : Paiement confirmé, transaction validée automatiquement
- `canceled` : Paiement annulé par l'utilisateur
- `failed` : Paiement échoué

## 🔐 Sécurité

### Vérification des webhooks

Il est recommandé de vérifier la signature des webhooks FedaPay. La méthode `fedapayWebhook` dans `PaymentController` inclut un placeholder pour cette vérification.

Pour une sécurité maximale :
1. Utilisez le secret de webhook fourni par FedaPay
2. Vérifiez la signature dans l'en-tête `X-FedaPay-Signature`
3. Implémentez une validation stricte avant de traiter le webhook

## 📊 Structure de la base de données

Les nouvelles colonnes ajoutées à la table `transactions` :

- `fedapay_transaction_id` : ID de la transaction FedaPay
- `fedapay_status` : Statut de la transaction FedaPay
- `payment_method` : Méthode de paiement (`fedapay` ou `manual`)

## 🧪 Tests

### Mode Sandbox

Pour tester en mode sandbox :
1. Utilisez `FEDAPAY_ENVIRONMENT=sandbox` dans `.env`
2. Utilisez les numéros de test fournis par FedaPay
3. Les transactions ne seront pas réellement débitées

### Mode Production

Pour passer en production :
1. Changez `FEDAPAY_ENVIRONMENT=live` dans `.env`
2. Utilisez votre clé API de production
3. Configurez le webhook avec l'URL de production

## 🐛 Dépannage

### Le webhook n'est pas reçu

1. Vérifiez que l'URL du webhook est accessible publiquement
2. Vérifiez les logs Laravel : `storage/logs/laravel.log`
3. Vérifiez la configuration du webhook dans le tableau de bord FedaPay

### La transaction n'est pas validée automatiquement

1. Vérifiez les logs pour voir si le webhook a été reçu
2. Vérifiez que le statut FedaPay est bien `approved` ou `paid`
3. Vérifiez que la transaction existe dans la base de données avec le bon `fedapay_transaction_id`

### Erreur "Configuration FedaPay manquante"

1. Vérifiez que `FEDAPAY_API_KEY` est bien défini dans `.env`
2. Exécutez `php artisan config:clear` pour vider le cache de configuration

## 📝 Notes importantes

- Les transactions créées via FedaPay ont automatiquement `payment_method = 'fedapay'`
- Les anciennes transactions avec upload de photo ont `payment_method = 'manual'`
- Le système continue de supporter les deux méthodes, mais FedaPay est maintenant la méthode par défaut pour les nouveaux dépôts

## 🔗 Ressources

- Documentation FedaPay : https://docs.fedapay.com
- SDK PHP FedaPay : https://github.com/fedapay/fedapay-php
- Tableau de bord FedaPay : https://dashboard.fedapay.com

