-- Migration 008 : Attribution des véhicules aux techniciens
-- Chaque véhicule est attribué à un technicien spécifique

-- Ajout de la colonne id_technicien dans vehicules
ALTER TABLE vehicules 
ADD COLUMN id_technicien INT DEFAULT NULL AFTER actif,
ADD CONSTRAINT fk_vehicule_technicien FOREIGN KEY (id_technicien) REFERENCES techniciens(id) ON DELETE SET NULL;

-- Attribution des véhicules existants aux techniciens
-- (exemple : les 3 premiers véhicules aux 3 premiers techniciens actifs)
UPDATE vehicules v
JOIN (
    SELECT id, ROW_NUMBER() OVER (ORDER BY id) as rn
    FROM techniciens
    WHERE actif = 1
    LIMIT 3
) t ON v.id = t.rn
SET v.id_technicien = t.id;
