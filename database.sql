-- Structure de la base de données pour l'agenda

CREATE DATABASE IF NOT EXISTS agenda_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

USE agenda_db;

-- Table des rendez-vous
CREATE TABLE IF NOT EXISTS rendez_vous (
    id INT AUTO_INCREMENT PRIMARY KEY,
    titre VARCHAR(255) NOT NULL,
    description TEXT,
    date_rdv DATE NOT NULL,
    heure_debut TIME NOT NULL,
    heure_fin TIME NOT NULL,
    lieu VARCHAR(255),
    id_technicien INT DEFAULT NULL,
    client_id INT DEFAULT NULL,
    statut ENUM('planifie', 'en_cours', 'termine', 'annule') DEFAULT 'planifie',
    duree_reelle DECIMAL(10,2) DEFAULT NULL COMMENT 'Durée réelle de l\'intervention en heures (rempli après clôture)',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_date_rdv (date_rdv),
    INDEX idx_heure_debut (heure_debut),
    INDEX idx_id_technicien (id_technicien),
    INDEX idx_rendez_vous_client_id (client_id),
    INDEX idx_duree_reelle (duree_reelle)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table des clients
CREATE TABLE IF NOT EXISTS clients (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nom VARCHAR(100) NOT NULL,
    prenom VARCHAR(100) NOT NULL,
    email VARCHAR(100),
    adresse TEXT,
    code_postal VARCHAR(20),
    ville VARCHAR(100),
    pays VARCHAR(100),
    etage VARCHAR(20),
    code_entree VARCHAR(50),
    telephone_fixe VARCHAR(20),
    telephone_mobile VARCHAR(20),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    heure_bonus DECIMAL(10,2) DEFAULT 0.00 COMMENT 'Cumul des arrondis de temps (positif=bonus, négatif=malus) en heures',
    tarif_horaire DECIMAL(10,2) DEFAULT 50.00,
    PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table des techniciens
CREATE TABLE IF NOT EXISTS techniciens (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nom VARCHAR(100) NOT NULL,
    prenom VARCHAR(100) NOT NULL,
    email VARCHAR(100),
    adresse TEXT,
    code_postal VARCHAR(20),
    ville VARCHAR(100),
    pays VARCHAR(100),
    telephone_fixe VARCHAR(20),
    telephone_mobile VARCHAR(20),
    date_entree DATE,
    date_sortie DATE,
    actif TINYINT(1) DEFAULT 1,
    couleur VARCHAR(20),
    salaire_horaire DECIMAL(10,2),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table des types de forfait
CREATE TABLE IF NOT EXISTS type_forfait (
    id INT AUTO_INCREMENT PRIMARY KEY,
    type_forfait VARCHAR(100) NOT NULL,
    prix_forfait DECIMAL(10,2) NOT NULL,
    nbr_heure_forfait DECIMAL(10,2) NOT NULL,
    date_creation TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    actif TINYINT(1) DEFAULT 1 COMMENT 'Indique si le forfait est actif (disponible à la vente)',
    PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table des forfaits vendus
CREATE TABLE IF NOT EXISTS forfaits_vendus (
    id INT AUTO_INCREMENT PRIMARY KEY,
    client_id INT NOT NULL,
    type_forfait_id INT NOT NULL,
    heures_total DECIMAL(10,2) NOT NULL,
    heures_restantes DECIMAL(10,2) NOT NULL,
    tarif DECIMAL(10,2) NOT NULL,
    intervenant_id INT DEFAULT NULL,
    signature_client BLOB,
    date_signature DATETIME DEFAULT NULL,
    date_debut DATE DEFAULT NULL,
    date_fin DATE DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    paye TINYINT(1) DEFAULT 0,
    date_paiement DATETIME DEFAULT NULL,
    PRIMARY KEY (id),
    INDEX idx_client (client_id),
    INDEX idx_type_forfait (type_forfait_id),
    INDEX idx_intervenant (intervenant_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table de l'historique de consommation
CREATE TABLE IF NOT EXISTS historique_consommation (
    id INT AUTO_INCREMENT PRIMARY KEY,
    rendez_vous_id INT NOT NULL,
    forfait_vendu_id INT NOT NULL,
    client_id INT NOT NULL,
    temps_reel DECIMAL(10,2) NOT NULL,
    temps_arrondi DECIMAL(10,2) NOT NULL,
    difference_arrondi DECIMAL(10,2) NOT NULL,
    heures_decomptes DECIMAL(10,2) NOT NULL,
    heures_avant DECIMAL(10,2) NOT NULL,
    heures_apres DECIMAL(10,2) NOT NULL,
    date_rdv DATE NOT NULL,
    heure_debut TIME NOT NULL,
    heure_fin TIME NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    INDEX idx_forfait (forfait_vendu_id),
    INDEX idx_client (client_id),
    INDEX idx_rdv (rendez_vous_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table de facturation hors forfait
CREATE TABLE IF NOT EXISTS facturation_hors_forfait (
    id INT AUTO_INCREMENT PRIMARY KEY,
    rendez_vous_id INT NOT NULL,
    client_id INT NOT NULL,
    date_intervention DATE NOT NULL,
    heure_debut TIME NOT NULL,
    heure_fin TIME NOT NULL,
    duree_reelle DECIMAL(10,2) NOT NULL,
    quantite DECIMAL(10,2) NOT NULL COMMENT 'Quantité facturée (1h + multiples de 30min)',
    tarif_horaire DECIMAL(10,2) NOT NULL,
    montant_total DECIMAL(10,2) NOT NULL,
    paye TINYINT(1) DEFAULT 0,
    date_paiement DATETIME DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    INDEX idx_client (client_id),
    INDEX idx_rdv (rendez_vous_id),
    INDEX idx_paye (paye)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
