-- Ajout des nouveaux champs pour les clients

USE agenda_db;

-- Ajouter la source d'acquisition
ALTER TABLE clients ADD COLUMN IF NOT EXISTS source_acquisition ENUM('bouche_a_oreille', 'publicite', 'site_web', 'reseau_social', 'partenaire', 'autre') DEFAULT NULL;

-- Ajouter le mode de paiement préféré
ALTER TABLE clients ADD COLUMN IF NOT EXISTS mode_paiement ENUM('especes', 'cheque', 'virement', 'carte_bancaire', 'avance_immediate') DEFAULT NULL;

-- Commentaire explicatif
-- source_acquisition : permet de tracer l'origine du client
-- mode_paiement : si "avance_immediate", afficher une icône dans l'agenda
