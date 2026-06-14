-- Migration pour permettre forfait_vendu_id NULL dans historique_consommation
-- Cela permettra de créer un historique même pour les interventions sans forfait

ALTER TABLE historique_consommation 
MODIFY COLUMN forfait_vendu_id INT NULL;
