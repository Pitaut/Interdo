-- Migration 010 : Barème kilométrique fiscal
-- Permet d'utiliser les barèmes officiels de l'administration fiscale

-- Table pour stocker les barèmes fiscaux par année
CREATE TABLE IF NOT EXISTS bareme_kilometrique (
    id INT AUTO_INCREMENT PRIMARY KEY,
    annee_fiscale INT NOT NULL,
    type_vehicule ENUM('voiture', 'moto', 'scooter', 'cyclomoteur') NOT NULL,
    puissance_min INT NOT NULL COMMENT 'Chevaux fiscaux minimum',
    puissance_max INT NOT NULL COMMENT 'Chevaux fiscaux maximum',
    distance_min INT NOT NULL DEFAULT 0 COMMENT 'Kilomètrage annuel minimum',
    distance_max INT NOT NULL DEFAULT 999999 COMMENT 'Kilomètrage annuel maximum',
    formule_calcul VARCHAR(255) NOT NULL COMMENT 'Formule: ex: "d * 0.523" ou "d * 0.294 + 1082"',
    cout_fixe DECIMAL(10,2) DEFAULT 0 COMMENT 'Partie fixe du barème',
    cout_variable DECIMAL(10,4) NOT NULL COMMENT 'Coût par km',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_bareme (annee_fiscale, type_vehicule, puissance_min, puissance_max, distance_min, distance_max)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Barèmes kilométriques fiscaux';

-- Ajouter les colonnes nécessaires aux véhicules (ignorer si existe déjà)
ALTER TABLE vehicules 
ADD COLUMN puissance_fiscale INT DEFAULT NULL COMMENT 'Chevaux fiscaux (CV)' AFTER type_vehicule;

ALTER TABLE vehicules 
ADD COLUMN kilometrage_annuel_estime INT DEFAULT 15000 COMMENT 'Km annuel estimé pour le barème' AFTER kilometrage_actuel;

ALTER TABLE vehicules 
ADD COLUMN mode_calcul_cout ENUM('bareme_fiscal', 'cout_reel') DEFAULT 'bareme_fiscal' COMMENT 'Mode de calcul du coût km' AFTER actif;

-- Ajouter une colonne pour tracer le barème utilisé
ALTER TABLE rendez_vous
ADD COLUMN bareme_km_utilise_id INT DEFAULT NULL COMMENT 'ID du barème fiscal utilisé pour le calcul' AFTER cout_total;

ALTER TABLE rendez_vous
ADD CONSTRAINT fk_rendez_vous_bareme FOREIGN KEY (bareme_km_utilise_id) REFERENCES bareme_kilometrique(id);

-- Insertion des barèmes 2024 (source: impots.gouv.fr)
-- Voitures (jusqu'à 5000 km)
INSERT INTO bareme_kilometrique (annee_fiscale, type_vehicule, puissance_min, puissance_max, distance_min, distance_max, formule_calcul, cout_fixe, cout_variable) VALUES
(2024, 'voiture', 3, 3, 0, 5000, 'd * 0.529', 0, 0.529),
(2024, 'voiture', 4, 4, 0, 5000, 'd * 0.606', 0, 0.606),
(2024, 'voiture', 5, 5, 0, 5000, 'd * 0.636', 0, 0.636),
(2024, 'voiture', 6, 6, 0, 5000, 'd * 0.665', 0, 0.665),
(2024, 'voiture', 7, 99, 0, 5000, 'd * 0.697', 0, 0.697);

-- Voitures (5001 à 20000 km)
INSERT INTO bareme_kilometrique (annee_fiscale, type_vehicule, puissance_min, puissance_max, distance_min, distance_max, formule_calcul, cout_fixe, cout_variable) VALUES
(2024, 'voiture', 3, 3, 5001, 20000, 'd * 0.316 + 1065', 1065, 0.316),
(2024, 'voiture', 4, 4, 5001, 20000, 'd * 0.340 + 1330', 1330, 0.340),
(2024, 'voiture', 5, 5, 5001, 20000, 'd * 0.357 + 1395', 1395, 0.357),
(2024, 'voiture', 6, 6, 5001, 20000, 'd * 0.374 + 1457', 1457, 0.374),
(2024, 'voiture', 7, 99, 5001, 20000, 'd * 0.394 + 1515', 1515, 0.394);

-- Voitures (plus de 20000 km)
INSERT INTO bareme_kilometrique (annee_fiscale, type_vehicule, puissance_min, puissance_max, distance_min, distance_max, formule_calcul, cout_fixe, cout_variable) VALUES
(2024, 'voiture', 3, 3, 20001, 999999, 'd * 0.370', 0, 0.370),
(2024, 'voiture', 4, 4, 20001, 999999, 'd * 0.407', 0, 0.407),
(2024, 'voiture', 5, 5, 20001, 999999, 'd * 0.427', 0, 0.427),
(2024, 'voiture', 6, 6, 20001, 999999, 'd * 0.447', 0, 0.447),
(2024, 'voiture', 7, 99, 20001, 999999, 'd * 0.470', 0, 0.470);

-- Motos (jusqu'à 3000 km)
INSERT INTO bareme_kilometrique (annee_fiscale, type_vehicule, puissance_min, puissance_max, distance_min, distance_max, formule_calcul, cout_fixe, cout_variable) VALUES
(2024, 'moto', 1, 2, 0, 3000, 'd * 0.395', 0, 0.395),
(2024, 'moto', 3, 5, 0, 3000, 'd * 0.468', 0, 0.468),
(2024, 'moto', 6, 99, 0, 3000, 'd * 0.606', 0, 0.606);

-- Motos (3001 à 6000 km)
INSERT INTO bareme_kilometrique (annee_fiscale, type_vehicule, puissance_min, puissance_max, distance_min, distance_max, formule_calcul, cout_fixe, cout_variable) VALUES
(2024, 'moto', 1, 2, 3001, 6000, 'd * 0.099 + 891', 891, 0.099),
(2024, 'moto', 3, 5, 3001, 6000, 'd * 0.082 + 1158', 1158, 0.082),
(2024, 'moto', 6, 99, 3001, 6000, 'd * 0.079 + 1583', 1583, 0.079);

-- Motos (plus de 6000 km)
INSERT INTO bareme_kilometrique (annee_fiscale, type_vehicule, puissance_min, puissance_max, distance_min, distance_max, formule_calcul, cout_fixe, cout_variable) VALUES
(2024, 'moto', 1, 2, 6001, 999999, 'd * 0.248', 0, 0.248),
(2024, 'moto', 3, 5, 6001, 999999, 'd * 0.275', 0, 0.275),
(2024, 'moto', 6, 99, 6001, 999999, 'd * 0.343', 0, 0.343);

-- Cyclomoteurs (tous kilométrages)
INSERT INTO bareme_kilometrique (annee_fiscale, type_vehicule, puissance_min, puissance_max, distance_min, distance_max, formule_calcul, cout_fixe, cout_variable) VALUES
(2024, 'cyclomoteur', 0, 99, 0, 2000, 'd * 0.315', 0, 0.315),
(2024, 'cyclomoteur', 0, 99, 2001, 5000, 'd * 0.079 + 473', 473, 0.079),
(2024, 'cyclomoteur', 0, 99, 5001, 999999, 'd * 0.198', 0, 0.198);

-- Barèmes 2025 (estimation - à mettre à jour avec les valeurs officielles quand disponibles)
-- Pour l'instant, copie des valeurs 2024 avec une légère augmentation (+2%)
INSERT INTO bareme_kilometrique (annee_fiscale, type_vehicule, puissance_min, puissance_max, distance_min, distance_max, formule_calcul, cout_fixe, cout_variable) 
SELECT 2025, type_vehicule, puissance_min, puissance_max, distance_min, distance_max, 
       formule_calcul, 
       ROUND(cout_fixe * 1.02, 2), 
       ROUND(cout_variable * 1.02, 4)
FROM bareme_kilometrique 
WHERE annee_fiscale = 2024;

-- Mise à jour des véhicules existants avec des valeurs par défaut
UPDATE vehicules 
SET puissance_fiscale = CASE 
    WHEN type_vehicule = 'voiture' THEN 5
    WHEN type_vehicule = 'utilitaire' THEN 6
    WHEN type_vehicule = 'camionnette' THEN 7
    WHEN type_vehicule = 'moto' THEN 4
    ELSE 5
END,
kilometrage_annuel_estime = 15000,
mode_calcul_cout = 'bareme_fiscal'
WHERE puissance_fiscale IS NULL;
