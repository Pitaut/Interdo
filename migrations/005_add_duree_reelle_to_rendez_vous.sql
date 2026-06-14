-- Migration 005: Ajouter le champ duree_reelle à la table rendez_vous
-- Ce champ stocke la durée réelle de l'intervention après clôture

USE agenda_db;

ALTER TABLE rendez_vous 
ADD COLUMN duree_reelle DECIMAL(10,2) DEFAULT NULL COMMENT 'Durée réelle de l\'intervention en heures (rempli après clôture)';

-- Index pour les requêtes de reporting
CREATE INDEX idx_duree_reelle ON rendez_vous(duree_reelle);
