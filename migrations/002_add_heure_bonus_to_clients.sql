-- Migration pour ajouter le champ heure_bonus à la table clients
-- Ce champ mémorise les arrondis cumulés (bonus si positif, malus si négatif)

ALTER TABLE clients 
ADD COLUMN heure_bonus DECIMAL(10,2) DEFAULT 0.00 COMMENT 'Cumul des arrondis de temps (positif=bonus, négatif=malus) en heures';
