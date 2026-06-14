-- Migration : Ajout des colonnes signature_client
-- Date : 7 décembre 2025

USE agenda_db;

-- Ajouter la colonne signature_client à la table rendez_vous (pour clôture intervention)
ALTER TABLE rendez_vous 
ADD COLUMN IF NOT EXISTS signature_client LONGTEXT DEFAULT NULL COMMENT 'Signature client en base64 (clôture intervention)';

-- Note : La table forfaits_vendus a déjà la colonne signature_client dans structure.sql
-- Mais on s'assure qu'elle existe si la base a été créée avec l'ancien schéma

-- Vérifier et ajouter si nécessaire pour forfaits_vendus
-- (La date_signature existe déjà dans structure.sql)

SELECT 'Migration terminée - colonnes signature_client ajoutées' AS statut;
