-- Table pour tracer l'historique de consommation des heures de forfait
CREATE TABLE IF NOT EXISTS historique_consommation (
    id INT AUTO_INCREMENT PRIMARY KEY,
    rendez_vous_id INT NOT NULL COMMENT 'ID du rendez-vous clôturé',
    forfait_vendu_id INT NOT NULL COMMENT 'ID du forfait utilisé',
    client_id INT NOT NULL COMMENT 'ID du client',
    temps_reel DECIMAL(10,2) NOT NULL COMMENT 'Temps réel passé en heures (heure_fin - heure_debut)',
    temps_arrondi DECIMAL(10,2) NOT NULL COMMENT 'Temps arrondi au 30min supérieur en heures',
    difference_arrondi DECIMAL(10,2) NOT NULL COMMENT 'Différence entre temps arrondi et temps réel (ajouté au heure_bonus)',
    heures_decomptes DECIMAL(10,2) NOT NULL COMMENT 'Heures décomptées du forfait',
    heures_avant DECIMAL(10,2) NOT NULL COMMENT 'Heures restantes avant décompte',
    heures_apres DECIMAL(10,2) NOT NULL COMMENT 'Heures restantes après décompte',
    date_rdv DATE NOT NULL COMMENT 'Date du rendez-vous',
    heure_debut TIME NOT NULL COMMENT 'Heure de début',
    heure_fin TIME NOT NULL COMMENT 'Heure de fin',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_forfait (forfait_vendu_id),
    INDEX idx_client (client_id),
    INDEX idx_rdv (rendez_vous_id),
    FOREIGN KEY (rendez_vous_id) REFERENCES rendez_vous(id) ON DELETE CASCADE,
    FOREIGN KEY (forfait_vendu_id) REFERENCES forfaits_vendus(id) ON DELETE CASCADE,
    FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
