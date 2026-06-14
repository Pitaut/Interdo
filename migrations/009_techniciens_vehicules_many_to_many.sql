-- Migration 009: Relation many-to-many entre techniciens et véhicules
-- Un véhicule peut être utilisé par plusieurs techniciens

-- Créer la table de liaison
CREATE TABLE IF NOT EXISTS techniciens_vehicules (
    id INT AUTO_INCREMENT PRIMARY KEY,
    id_technicien INT NOT NULL,
    id_vehicule INT NOT NULL,
    date_debut DATE NOT NULL COMMENT 'Date de début d''attribution',
    date_fin DATE NULL COMMENT 'Date de fin d''attribution (NULL si actuel)',
    principal BOOLEAN DEFAULT FALSE COMMENT 'Véhicule principal du technicien',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (id_technicien) REFERENCES techniciens(id) ON DELETE CASCADE,
    FOREIGN KEY (id_vehicule) REFERENCES vehicules(id) ON DELETE CASCADE,
    UNIQUE KEY unique_tech_veh_actif (id_technicien, id_vehicule, date_fin),
    INDEX idx_technicien (id_technicien),
    INDEX idx_vehicule (id_vehicule),
    INDEX idx_actif (date_fin)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Attribution des véhicules aux techniciens';

-- Migrer les données existantes de vehicules.id_technicien vers techniciens_vehicules
INSERT INTO techniciens_vehicules (id_technicien, id_vehicule, date_debut, principal)
SELECT id_technicien, id, CURRENT_DATE, TRUE
FROM vehicules
WHERE id_technicien IS NOT NULL;

-- Supprimer la colonne id_technicien de vehicules (devenue obsolète)
ALTER TABLE vehicules DROP FOREIGN KEY IF EXISTS vehicules_ibfk_1;
ALTER TABLE vehicules DROP COLUMN IF EXISTS id_technicien;
