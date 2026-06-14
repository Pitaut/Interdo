CREATE OR REPLACE VIEW v_rentabilite_interventions AS
SELECT 
    r.id,
    r.date_rdv,
    r.titre,
    r.duree_reelle,
    r.statut,
    r.distance_km,
    r.temps_trajet_minutes,
    r.cout_technicien,
    r.cout_vehicule,
    r.cout_total,
    
    c.id AS client_id,
    CONCAT(c.prenom, ' ', c.nom) AS client_nom,
    
    t.id AS technicien_id,
    CONCAT(t.prenom, ' ', t.nom) AS technicien_nom,
    t.cout_horaire_total,
    
    COALESCE(v1.id, v2.id) AS vehicule_id,
    COALESCE(v1.nom, v2.nom) AS vehicule_nom,
    COALESCE(v1.immatriculation, v2.immatriculation) AS vehicule_immat,
    
    -- Calcul du revenu selon le type
    CASE 
        WHEN fhf.id IS NOT NULL THEN fhf.montant_total
        WHEN hc.id IS NOT NULL AND fv.id IS NOT NULL AND tf.nbr_heure_forfait > 0 THEN 
            ROUND((fv.tarif / tf.nbr_heure_forfait) * r.duree_reelle, 2)
        ELSE 0 
    END AS revenu,
    
    -- Calcul de la marge brute
    CASE 
        WHEN fhf.id IS NOT NULL THEN 
            ROUND(fhf.montant_total - IFNULL(r.cout_total, 0), 2)
        WHEN hc.id IS NOT NULL AND fv.id IS NOT NULL AND tf.nbr_heure_forfait > 0 THEN 
            ROUND((fv.tarif / tf.nbr_heure_forfait) * r.duree_reelle - IFNULL(r.cout_total, 0), 2)
        ELSE -IFNULL(r.cout_total, 0)
    END AS marge_brute,
    
    -- Calcul du taux de marge
    CASE 
        WHEN fhf.id IS NOT NULL AND fhf.montant_total > 0 THEN 
            ROUND(((fhf.montant_total - IFNULL(r.cout_total, 0)) / fhf.montant_total) * 100, 2)
        WHEN hc.id IS NOT NULL AND fv.id IS NOT NULL AND tf.nbr_heure_forfait > 0 THEN 
            ROUND((((fv.tarif / tf.nbr_heure_forfait) * r.duree_reelle - IFNULL(r.cout_total, 0)) / ((fv.tarif / tf.nbr_heure_forfait) * r.duree_reelle)) * 100, 2)
        ELSE NULL 
    END AS taux_marge_pct,
    
    CASE 
        WHEN fhf.id IS NOT NULL THEN 'Hors forfait'
        WHEN hc.id IS NOT NULL THEN 'Forfait'
        ELSE 'Non facturé'
    END AS type_facturation
    
FROM rendez_vous r
LEFT JOIN clients c ON r.client_id = c.id
LEFT JOIN techniciens t ON r.id_technicien = t.id
LEFT JOIN vehicules v1 ON r.vehicule_id = v1.id
LEFT JOIN techniciens_vehicules tv ON t.id = tv.id_technicien AND tv.date_fin IS NULL AND tv.principal = 1
LEFT JOIN vehicules v2 ON tv.id_vehicule = v2.id
LEFT JOIN historique_consommation hc ON r.id = hc.rendez_vous_id
LEFT JOIN forfaits_vendus fv ON hc.forfait_vendu_id = fv.id
LEFT JOIN type_forfait tf ON fv.type_forfait_id = tf.id
LEFT JOIN facturation_hors_forfait fhf ON r.id = fhf.rendez_vous_id
WHERE r.statut = 'termine';
