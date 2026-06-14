-- Ajout de la colonne 'actif' à la table type_forfait
-- Date: 24 novembre 2025

USE agenda_interdo;

-- Ajouter la colonne actif (MySQL ne supporte pas IF NOT EXISTS dans ALTER TABLE)
-- Si la colonne existe déjà, cette commande retournera une erreur que vous pouvez ignorer
ALTER TABLE type_forfait 
ADD COLUMN actif BOOLEAN DEFAULT TRUE COMMENT 'Indique si le forfait est actif (disponible à la vente)';

-- Mettre tous les forfaits existants comme actifs par défaut
UPDATE type_forfait SET actif = TRUE WHERE actif IS NULL;

-- Ajouter un index pour améliorer les performances des requêtes filtrées par actif
ALTER TABLE type_forfait ADD INDEX idx_actif (actif);
