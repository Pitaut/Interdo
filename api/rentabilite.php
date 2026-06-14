<?php
/**
 * API Rentabilité
 * 
 * Gestion des données de rentabilité des interventions
 */

require_once '../config.php';
require_once '../includes/distance_calculator.php';
header('Content-Type: application/json; charset=utf-8');

$action = $_GET['action'] ?? '';

switch ($action) {
    case 'get_rentabilite':
        handleGetRentabilite();
        break;
    case 'calculate_distances':
        handleCalculateDistances();
        break;
    default:
        http_response_code(400);
        echo json_encode(['error' => 'Action invalide']);
}

/**
 * Récupère les données de rentabilité avec filtres
 */
function handleGetRentabilite() {
    try {
        $pdo = getDBConnection();
        ensureRentabiliteViewExists($pdo);
        
        // Paramètres
        $dateDebut = $_GET['date_debut'] ?? date('Y-m-01');
        $dateFin = $_GET['date_fin'] ?? date('Y-m-d');
        $technicienId = $_GET['technicien_id'] ?? null;
        $vehiculeId = $_GET['vehicule_id'] ?? null;
        
        // Construction de la requête avec filtres
        $sql = "SELECT * FROM v_rentabilite_interventions WHERE 1=1";
        $params = [];
        
        if ($dateDebut) {
            $sql .= " AND date_rdv >= ?";
            $params[] = $dateDebut;
        }
        
        if ($dateFin) {
            $sql .= " AND date_rdv <= ?";
            $params[] = $dateFin;
        }
        
        if ($technicienId) {
            $sql .= " AND technicien_id = ?";
            $params[] = $technicienId;
        }
        
        if ($vehiculeId) {
            $sql .= " AND vehicule_id = ?";
            $params[] = $vehiculeId;
        }
        
        $sql .= " ORDER BY date_rdv DESC, id DESC";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $interventions = $stmt->fetchAll();
        
        // Calcul des KPIs
        $kpis = [
            'nombre_interventions' => count($interventions),
            'ca_total' => 0,
            'couts_totaux' => 0,
            'marge_brute' => 0,
            'taux_marge_moyen' => 0
        ];
        
        $totalTauxMarge = 0;
        $nbAvecMarge = 0;
        
        foreach ($interventions as $inter) {
            $kpis['ca_total'] += (float)$inter['revenu'];
            $kpis['couts_totaux'] += (float)$inter['cout_total'];
            $kpis['marge_brute'] += (float)$inter['marge_brute'];
            
            if ($inter['taux_marge_pct'] !== null) {
                $totalTauxMarge += (float)$inter['taux_marge_pct'];
                $nbAvecMarge++;
            }
        }
        
        if ($nbAvecMarge > 0) {
            $kpis['taux_marge_moyen'] = $totalTauxMarge / $nbAvecMarge;
        }
        
        echo json_encode([
            'status' => 'success',
            'interventions' => $interventions,
            'kpis' => $kpis
        ], JSON_NUMERIC_CHECK);
        
    } catch (PDOException $e) {
        if ((int)$e->getCode() === 1146 || strpos($e->getMessage(), 'v_rentabilite_interventions') !== false) {
            http_response_code(500);
            echo json_encode([
                'error' => 'La vue SQL v_rentabilite_interventions est absente. Exécutez migrations/011_fix_view_without_definer.sql sur le serveur puis réessayez.'
            ]);
            return;
        }

        http_response_code(500);
        echo json_encode([
            'error' => $e->getMessage()
        ]);

    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode([
            'error' => $e->getMessage()
        ]);
    }
}


/**
 * Vérifie la présence de la vue de rentabilité et la recrée si nécessaire
 */
function ensureRentabiliteViewExists(PDO $pdo): void {
    $checkSql = "SELECT COUNT(*) FROM information_schema.VIEWS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'v_rentabilite_interventions'";
    $exists = (int)$pdo->query($checkSql)->fetchColumn() > 0;

    if ($exists) {
        return;
    }

    $pdo->exec(getRentabiliteViewSql());
}

/**
 * SQL de création de la vue sans DEFINER (compatible hébergement mutualisé)
 */
function getRentabiliteViewSql(): string {
    return <<<SQL
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
    
    CASE 
        WHEN fhf.id IS NOT NULL THEN fhf.montant_total
        WHEN hc.id IS NOT NULL AND fv.id IS NOT NULL AND tf.nbr_heure_forfait > 0 THEN 
            ROUND((fv.tarif / tf.nbr_heure_forfait) * r.duree_reelle, 2)
        ELSE 0 
    END AS revenu,
    
    CASE 
        WHEN fhf.id IS NOT NULL THEN 
            ROUND(fhf.montant_total - IFNULL(r.cout_total, 0), 2)
        WHEN hc.id IS NOT NULL AND fv.id IS NOT NULL AND tf.nbr_heure_forfait > 0 THEN 
            ROUND((fv.tarif / tf.nbr_heure_forfait) * r.duree_reelle - IFNULL(r.cout_total, 0), 2)
        ELSE -IFNULL(r.cout_total, 0)
    END AS marge_brute,
    
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
WHERE r.statut = 'termine'
SQL;
}

/**
 * Calcule les distances manquantes pour les interventions terminées
 */
function handleCalculateDistances() {
    try {
        $pdo = getDBConnection();
        
        // Paramètres
        $dateDebut = $_GET['date_debut'] ?? date('Y-m-01');
        $dateFin = $_GET['date_fin'] ?? date('Y-m-d');
        
        // Vérifier que la clé API est configurée
        if (!defined('OPENROUTE_API_KEY') || OPENROUTE_API_KEY === 'VOTRE_CLE_API_ICI') {
            http_response_code(400);
            echo json_encode([
                'error' => 'Clé API OpenRouteService non configurée dans config.php'
            ]);
            return;
        }
        
        // Calculer les distances
        $stats = calculerDistancesInterventions($pdo, $dateDebut, $dateFin);
        
        // Message détaillé
        $message = "Calcul terminé : {$stats['calculated']} distances calculées, {$stats['errors']} erreurs, {$stats['skipped']} ignorées";
        
        echo json_encode([
            'status' => 'success',
            'message' => $message,
            'stats' => $stats,
            'errors_details' => $stats['errors_details'] ?? []
        ]);
        
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode([
            'error' => $e->getMessage()
        ]);
    }
}
?>
