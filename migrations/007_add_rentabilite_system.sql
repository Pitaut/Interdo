-- ============================================================================
-- Migration 007 : Système de calcul de rentabilité
-- Date : 21 décembre 2025
-- Description : Ajout des tables et colonnes nécessaires pour calculer
--               la rentabilité des interventions (coûts techniciens + véhicules)
-- ============================================================================

USE agenda_db;

-- ============================================================================
-- Table: vehicules
-- Description: Gestion des véhicules de l'entreprise
-- ============================================================================
CREATE TABLE IF NOT EXISTS vehicules (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nom VARCHAR(100) NOT NULL COMMENT 'Nom/désignation du véhicule (ex: Renault Kangoo)',
    immatriculation VARCHAR(20) NOT NULL UNIQUE COMMENT 'Plaque d immatriculation',
    type_vehicule ENUM('utilitaire', 'voiture', 'camionnette', 'moto') DEFAULT 'utilitaire',
    
    -- Coûts fixes mensuels
    cout_mensuel_assurance DECIMAL(10,2) DEFAULT 0.00 COMMENT 'Assurance mensuelle en euros',
    cout_mensuel_entretien DECIMAL(10,2) DEFAULT 0.00 COMMENT 'Entretien moyen mensuel en euros',
    cout_mensuel_autre DECIMAL(10,2) DEFAULT 0.00 COMMENT 'Autres frais mensuels (parking, péage, etc.)',
    
    -- Coûts variables au kilomètre
    cout_carburant_km DECIMAL(10,4) DEFAULT 0.15 COMMENT 'Coût carburant par km en euros',
    cout_usure_km DECIMAL(10,4) DEFAULT 0.05 COMMENT 'Coût usure par km en euros (pneus, freins, etc.)',
    
    -- Informations
    date_acquisition DATE COMMENT 'Date d achat ou de mise en service',
    kilometrage_actuel INT DEFAULT 0 COMMENT 'Kilométrage actuel',
    actif TINYINT(1) DEFAULT 1 COMMENT '1=en service, 0=hors service',
    
    -- Attribution technicien (ajouté depuis migration 008)
    id_technicien INT DEFAULT NULL COMMENT 'Technicien auquel le véhicule est attribué',
    
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    INDEX idx_actif (actif),
    INDEX idx_immatriculation (immatriculation),
    INDEX idx_technicien (id_technicien)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Gestion des véhicules et leurs coûts pour calcul de rentabilité';

-- Ajout de la clé étrangère vers techniciens (après création de la table)
-- Supprimer la contrainte si elle existe déjà
SET @constraint_exists = (SELECT COUNT(*) 
    FROM information_schema.TABLE_CONSTRAINTS 
    WHERE CONSTRAINT_SCHEMA = 'agenda_db' 
    AND TABLE_NAME = 'vehicules' 
    AND CONSTRAINT_NAME = 'fk_vehicule_technicien');

SET @sql = IF(@constraint_exists > 0, 
    'ALTER TABLE vehicules DROP FOREIGN KEY fk_vehicule_technicien', 
    'SELECT "Contrainte n\'existe pas encore"');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Ajouter la contrainte
ALTER TABLE vehicules 
ADD CONSTRAINT fk_vehicule_technicien 
FOREIGN KEY (id_technicien) REFERENCES techniciens(id) 
ON DELETE SET NULL ON UPDATE CASCADE;

-- ============================================================================
-- Modifications table techniciens
-- Description: Ajout des coûts employeur
-- ============================================================================

-- Coût horaire brut (salaire brut)
ALTER TABLE techniciens 
ADD COLUMN cout_horaire_brut DECIMAL(10,2) DEFAULT 0.00 
COMMENT 'Salaire horaire brut du technicien en euros'
AFTER salaire_horaire;

-- Charges patronales (%)
ALTER TABLE techniciens 
ADD COLUMN taux_charges_patronales DECIMAL(5,2) DEFAULT 45.00 
COMMENT 'Taux de charges patronales en % (défaut: 45%)'
AFTER cout_horaire_brut;

-- Coût horaire total employeur (calculé automatiquement)
ALTER TABLE techniciens 
ADD COLUMN cout_horaire_total DECIMAL(10,2) GENERATED ALWAYS AS 
(cout_horaire_brut * (1 + taux_charges_patronales / 100)) STORED
COMMENT 'Coût horaire total pour l employeur (brut + charges) - calculé automatiquement'
AFTER taux_charges_patronales;

-- ============================================================================
-- Modifications table rendez_vous
-- Description: Ajout des informations de trajet et véhicule
-- ============================================================================

-- Véhicule utilisé
ALTER TABLE rendez_vous 
ADD COLUMN vehicule_id INT DEFAULT NULL 
COMMENT 'Véhicule utilisé pour l intervention'
AFTER id_technicien;

-- Distance aller-retour
ALTER TABLE rendez_vous 
ADD COLUMN distance_km DECIMAL(10,2) DEFAULT 0.00 
COMMENT 'Distance aller-retour en kilomètres'
AFTER vehicule_id;

-- Temps de trajet total
ALTER TABLE rendez_vous 
ADD COLUMN temps_trajet_minutes INT DEFAULT 0 
COMMENT 'Temps de trajet total aller-retour en minutes'
AFTER distance_km;

-- Coûts calculés (remplis après clôture)
ALTER TABLE rendez_vous 
ADD COLUMN cout_technicien DECIMAL(10,2) DEFAULT NULL 
COMMENT 'Coût technicien = duree_reelle * cout_horaire_total (rempli après clôture)'
AFTER temps_trajet_minutes;

ALTER TABLE rendez_vous 
ADD COLUMN cout_vehicule DECIMAL(10,2) DEFAULT NULL 
COMMENT 'Coût véhicule = distance_km * (cout_carburant_km + cout_usure_km) (rempli après clôture)'
AFTER cout_technicien;

ALTER TABLE rendez_vous 
ADD COLUMN cout_total DECIMAL(10,2) GENERATED ALWAYS AS 
(COALESCE(cout_technicien, 0) + COALESCE(cout_vehicule, 0)) STORED
COMMENT 'Coût total de l intervention - calculé automatiquement'
AFTER cout_vehicule;

-- Foreign key vers vehicules
ALTER TABLE rendez_vous 
ADD CONSTRAINT fk_rendez_vous_vehicule 
FOREIGN KEY (vehicule_id) REFERENCES vehicules(id) 
ON DELETE SET NULL 
ON UPDATE CASCADE;

-- Index pour les requêtes de rentabilité
CREATE INDEX idx_cout_total ON rendez_vous(cout_total);
CREATE INDEX idx_vehicule_id ON rendez_vous(vehicule_id);

-- ============================================================================
-- Vue: v_rentabilite_interventions
-- Description: Vue consolidée de la rentabilité de chaque intervention
-- ============================================================================
CREATE OR REPLACE VIEW v_rentabilite_interventions AS
SELECT 
    r.id as intervention_id,
    r.titre,
    r.date_rdv,
    r.statut,
    
    -- Client
    c.nom as client_nom,
    c.prenom as client_prenom,
    
    -- Technicien
    t.nom as technicien_nom,
    t.prenom as technicien_prenom,
    t.cout_horaire_brut,
    t.taux_charges_patronales,
    t.cout_horaire_total as cout_horaire_technicien,
    
    -- Véhicule
    v.nom as vehicule_nom,
    v.immatriculation as vehicule_immatriculation,
    r.distance_km,
    
    -- Durées
    r.duree_reelle as heures_intervention,
    r.temps_trajet_minutes / 60.0 as heures_trajet,
    r.duree_reelle + (r.temps_trajet_minutes / 60.0) as heures_totales,
    
    -- Coûts
    r.cout_technicien,
    r.cout_vehicule,
    r.cout_total,
    
    -- Revenus
    CASE 
        WHEN h.forfait_vendu_id IS NOT NULL THEN 
            -- Intervention forfait : calculer revenu proportionnel
            (h.temps_arrondi * (SELECT tf.prix_forfait / tf.nbr_heure_forfait 
                                 FROM forfaits_vendus fv 
                                 JOIN type_forfait tf ON fv.forfait_id = tf.id 
                                 WHERE fv.id = h.forfait_vendu_id))
        WHEN fhf.id IS NOT NULL THEN 
            -- Intervention hors forfait
            fhf.montant_total
        ELSE 0
    END as revenu,
    
    -- Rentabilité
    CASE 
        WHEN h.forfait_vendu_id IS NOT NULL THEN 
            (h.temps_arrondi * (SELECT tf.prix_forfait / tf.nbr_heure_forfait 
                                 FROM forfaits_vendus fv 
                                 JOIN type_forfait tf ON fv.forfait_id = tf.id 
                                 WHERE fv.id = h.forfait_vendu_id)) - COALESCE(r.cout_total, 0)
        WHEN fhf.id IS NOT NULL THEN 
            fhf.montant_total - COALESCE(r.cout_total, 0)
        ELSE 0 - COALESCE(r.cout_total, 0)
    END as marge_brute,
    
    CASE 
        WHEN r.cout_total > 0 THEN
            ROUND(((CASE 
                WHEN h.forfait_vendu_id IS NOT NULL THEN 
                    (h.temps_arrondi * (SELECT tf.prix_forfait / tf.nbr_heure_forfait 
                                         FROM forfaits_vendus fv 
                                         JOIN type_forfait tf ON fv.forfait_id = tf.id 
                                         WHERE fv.id = h.forfait_vendu_id))
                WHEN fhf.id IS NOT NULL THEN 
                    fhf.montant_total
                ELSE 0
            END - r.cout_total) / r.cout_total * 100), 2)
        ELSE NULL
    END as taux_marge_pct,
    
    -- Type facturation
    CASE 
        WHEN h.forfait_vendu_id IS NOT NULL THEN 'Forfait'
        WHEN fhf.id IS NOT NULL THEN 'Hors forfait'
        ELSE 'Non facturé'
    END as type_facturation

FROM rendez_vous r
LEFT JOIN clients c ON r.client_id = c.id
LEFT JOIN techniciens t ON r.id_technicien = t.id
LEFT JOIN vehicules v ON r.vehicule_id = v.id
LEFT JOIN historique_consommation h ON r.id = h.rendez_vous_id
LEFT JOIN facturation_hors_forfait fhf ON r.id = fhf.rendez_vous_id
WHERE r.statut = 'termine';

-- ============================================================================
-- Données exemple : Véhicules
-- ============================================================================
INSERT INTO vehicules (nom, immatriculation, type_vehicule, cout_mensuel_assurance, cout_mensuel_entretien, cout_mensuel_autre, cout_carburant_km, cout_usure_km, actif) 
VALUES 
('Renault Kangoo 1', 'AB-123-CD', 'utilitaire', 80.00, 50.00, 20.00, 0.12, 0.05, 1),
('Peugeot Partner', 'EF-456-GH', 'utilitaire', 85.00, 45.00, 15.00, 0.13, 0.05, 1),
('Citroën Berlingo', 'IJ-789-KL', 'utilitaire', 75.00, 55.00, 25.00, 0.11, 0.04, 1);

-- ============================================================================
-- Mise à jour des techniciens existants avec coûts par défaut
-- ============================================================================
UPDATE techniciens 
SET 
    cout_horaire_brut = COALESCE(salaire_horaire, 15.00),
    taux_charges_patronales = 45.00
WHERE cout_horaire_brut = 0 OR cout_horaire_brut IS NULL;

-- ============================================================================
-- Attribution des véhicules aux techniciens (intégré depuis migration 008)
-- ============================================================================
-- Attribution automatique des 3 véhicules aux 3 premiers techniciens actifs
UPDATE vehicules v
JOIN (
    SELECT id, ROW_NUMBER() OVER (ORDER BY id) as rn
    FROM techniciens
    WHERE actif = 1
    LIMIT 3
) t ON v.id = t.rn
SET v.id_technicien = t.id;

-- ============================================================================
-- FIN DE LA MIGRATION 007 (incluant migration 008)
-- ============================================================================
