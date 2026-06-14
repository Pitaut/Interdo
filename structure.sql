-- ============================================================================
-- STRUCTURE DE LA BASE DE DONNÉES - Agenda Interdo
-- Date d'export: 7 décembre 2025
-- ============================================================================

CREATE DATABASE IF NOT EXISTS agenda_interdo CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

USE agenda_interdo;

-- ============================================================================
-- Table: rendez_vous
-- Description: Gestion des rendez-vous/interventions
-- ============================================================================
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
    signature_client LONGTEXT DEFAULT NULL COMMENT 'Signature client en base64 (clôture intervention)',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_date_rdv (date_rdv),
    INDEX idx_heure_debut (heure_debut),
    INDEX idx_id_technicien (id_technicien),
    INDEX idx_rendez_vous_client_id (client_id),
    INDEX idx_duree_reelle (duree_reelle)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- Table: clients
-- Description: Gestion des clients
-- Colonnes clés:
--   - heure_bonus: Cumul des arrondis de temps (bonus/malus en heures)
--   - avance_imme: Client en avance immédiate (1=oui, 0=non)
--   - mode_paiement: Mode de paiement préféré du client
-- ============================================================================
CREATE TABLE IF NOT EXISTS clients (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nom VARCHAR(100) NOT NULL,
    prenom VARCHAR(100) NOT NULL,
    email VARCHAR(100) DEFAULT NULL,
    adresse TEXT,
    code_postal VARCHAR(20) DEFAULT NULL,
    ville VARCHAR(100) DEFAULT NULL,
    pays VARCHAR(100) DEFAULT NULL,
    etage VARCHAR(20) DEFAULT NULL,
    code_entree VARCHAR(50) DEFAULT NULL,
    telephone_fixe VARCHAR(20) DEFAULT NULL,
    telephone_mobile VARCHAR(20) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    source_acquisition VARCHAR(50) DEFAULT NULL,
    mode_paiement VARCHAR(50) DEFAULT NULL,
    date_dernier_rappel DATETIME DEFAULT NULL,
    commentaire_rappel TEXT DEFAULT NULL,
    heure_bonus DECIMAL(10,2) DEFAULT 0.00 COMMENT 'Cumul des différences d arrondi (en heures décimales)',
    avance_imme TINYINT(1) DEFAULT 0 COMMENT 'Client en avance immédiate',
    tarif_horaire DECIMAL(10,2) DEFAULT 50.00
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- Table: techniciens
-- Description: Gestion des techniciens/intervenants
-- ============================================================================
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
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- Table: type_forfait
-- Description: Catalogue des forfaits disponibles
-- ============================================================================
CREATE TABLE IF NOT EXISTS type_forfait (
    id INT AUTO_INCREMENT PRIMARY KEY,
    type_forfait VARCHAR(100) NOT NULL,
    prix_forfait DECIMAL(10,2) NOT NULL,
    nbr_heure_forfait DECIMAL(10,2) NOT NULL,
    date_creation TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    actif TINYINT(1) DEFAULT 1 COMMENT 'Indique si le forfait est actif (disponible à la vente)'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- Table: forfaits_vendus
-- Description: Forfaits vendus aux clients
-- Colonnes clés:
--   - heures_total: Nombre d'heures total du forfait
--   - heures_restantes: Heures non encore consommées
--   - paye: Statut du paiement (1=payé, 0=impayé)
--   - mode_reglement: Mode de règlement utilisé
--   - date_paiement: Date du paiement
-- ============================================================================
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
    mode_reglement VARCHAR(50) DEFAULT NULL,
    date_paiement DATETIME DEFAULT NULL,
    INDEX idx_client (client_id),
    INDEX idx_type_forfait (type_forfait_id),
    INDEX idx_intervenant (intervenant_id),
    INDEX idx_paye (paye)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- Table: historique_consommation
-- Description: Historique de consommation des forfaits par intervention
-- Colonnes clés:
--   - temps_reel: Durée réelle de l'intervention
--   - temps_arrondi: Durée arrondie selon les règles métier
--   - difference_arrondi: Différence entre réel et arrondi (bonus/malus)
--   - heures_decomptes: Heures décomptées du forfait
-- ============================================================================
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
    INDEX idx_forfait (forfait_vendu_id),
    INDEX idx_client (client_id),
    INDEX idx_rdv (rendez_vous_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- Table: facturation_hors_forfait
-- Description: Facturation des interventions hors forfait
-- Colonnes clés:
--   - duree_reelle: Durée réelle de l'intervention
--   - quantite: Quantité facturée (1h minimum + multiples de 30min)
--   - montant_total: Montant total facturé
-- ============================================================================
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
    mode_reglement VARCHAR(50) DEFAULT NULL,
    date_paiement DATETIME DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_client (client_id),
    INDEX idx_rdv (rendez_vous_id),
    INDEX idx_paye (paye)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- NOTES IMPORTANTES
-- ============================================================================
-- 1. Les colonnes heure_bonus et avance_imme ont été ajoutées à la table clients
--    pour gérer le système de bonus/malus et l'avance immédiate
--
-- 2. Les colonnes mode_reglement et date_paiement ont été ajoutées aux tables
--    forfaits_vendus et facturation_hors_forfait pour le suivi des paiements
--
-- 3. Les colonnes signature_client ont été ajoutées pour capturer la signature
--    du client lors de la clôture d'intervention (rendez_vous) et lors de la
--    vente de forfaits (forfaits_vendus). Le format est base64 data URL.
--
-- 4. Tous les montants et durées sont en DECIMAL(10,2) pour assurer la précision
--
-- 5. Les index sont positionnés sur les colonnes fréquemment utilisées dans les
--    recherches et les jointures pour optimiser les performances
--
-- 6. Cette structure est compatible avec les migrations automatiques présentes
--    dans les fichiers PHP (api/clients.php, api/forfaits.php, etc.)
-- ============================================================================
