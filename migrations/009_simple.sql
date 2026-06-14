CREATE TABLE IF NOT EXISTS techniciens_vehicules (
    id INT AUTO_INCREMENT PRIMARY KEY,
    id_technicien INT NOT NULL,
    id_vehicule INT NOT NULL,
    date_debut DATE NOT NULL,
    date_fin DATE NULL,
    principal BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (id_technicien) REFERENCES techniciens(id) ON DELETE CASCADE,
    FOREIGN KEY (id_vehicule) REFERENCES vehicules(id) ON DELETE CASCADE,
    UNIQUE KEY unique_tech_veh_actif (id_technicien, id_vehicule, date_fin),
    INDEX idx_technicien (id_technicien),
    INDEX idx_vehicule (id_vehicule),
    INDEX idx_actif (date_fin)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO techniciens_vehicules (id_technicien, id_vehicule, date_debut, principal)
SELECT id_technicien, id, CURRENT_DATE, TRUE
FROM vehicules
WHERE id_technicien IS NOT NULL;

ALTER TABLE vehicules DROP FOREIGN KEY vehicules_ibfk_1;
ALTER TABLE vehicules DROP COLUMN id_technicien;
