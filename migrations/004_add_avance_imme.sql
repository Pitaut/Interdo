-- Migration: Ajout du champ avance_imme dans la table clients
-- Date: 30 novembre 2025
-- Description: Ajoute un indicateur d'avance immédiate pour les clients

USE agenda_db;

-- Ajouter la colonne avance_imme
ALTER TABLE clients 
ADD COLUMN avance_imme TINYINT(1) DEFAULT 0 COMMENT 'Avance immédiate activée (0=non, 1=oui)';

-- Créer un index pour optimiser les requêtes
CREATE INDEX idx_avance_imme ON clients(avance_imme);
