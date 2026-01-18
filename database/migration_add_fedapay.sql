-- =====================================================
-- MIGRATION: Ajout des colonnes FedaPay
-- Date: 2025
-- Description: Ajoute les colonnes nécessaires pour l'intégration FedaPay
-- =====================================================

-- Utiliser la base de données
USE caurisdg_cauris_db;

-- Ajouter les colonnes FedaPay à la table transactions
ALTER TABLE transactions 
ADD COLUMN fedapay_transaction_id VARCHAR(255) NULL 
COMMENT 'ID de la transaction FedaPay'
AFTER notes;

ALTER TABLE transactions 
ADD COLUMN fedapay_status VARCHAR(50) NULL 
COMMENT 'Statut de la transaction FedaPay (pending, approved, canceled)'
AFTER fedapay_transaction_id;

ALTER TABLE transactions 
ADD COLUMN payment_method VARCHAR(50) NULL 
COMMENT 'Méthode de paiement (fedapay, manual)'
DEFAULT 'manual'
AFTER fedapay_status;

-- Créer un index sur fedapay_transaction_id pour améliorer les performances
CREATE INDEX idx_fedapay_transaction_id ON transactions(fedapay_transaction_id);

-- Créer un index sur payment_method
CREATE INDEX idx_payment_method ON transactions(payment_method);

-- =====================================================
-- FIN DU FICHIER
-- =====================================================

