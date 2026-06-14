-- Table pour les facturations hors forfait (à l'heure)
CREATE TABLE IF NOT EXISTS facturation_hors_forfait (
    id INT AUTO_INCREMENT PRIMARY KEY,
    rendez_vous_id INT NOT NULL,
    client_id INT NOT NULL,
    date_intervention DATE NOT NULL,
    heure_debut TIME NOT NULL,
    heure_fin TIME NOT NULL,
    duree_reelle DECIMAL(10,2) NOT NULL COMMENT 'Durée réelle en heures',
    quantite DECIMAL(10,2) NOT NULL COMMENT 'Quantité facturée (1h + multiples de 0.5h)',
    tarif_horaire DECIMAL(10,2) NOT NULL COMMENT 'Prix de l\'heure',
    montant_total DECIMAL(10,2) NOT NULL COMMENT 'Montant total à facturer',
    paye BOOLEAN DEFAULT 0,
    date_paiement DATETIME NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (rendez_vous_id) REFERENCES rendez_vous(id),
    FOREIGN KEY (client_id) REFERENCES clients(id),
    INDEX idx_client (client_id),
    INDEX idx_rdv (rendez_vous_id),
    INDEX idx_paye (paye)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Ajouter une colonne pour le tarif horaire par défaut dans les clients
ALTER TABLE clients 
ADD COLUMN tarif_horaire DECIMAL(10,2) DEFAULT 50.00 
COMMENT 'Tarif horaire pour facturation hors forfait';
