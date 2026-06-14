<?php
/**
 * API Interventions
 * Gère la clôture des interventions avec décompte automatique
 * Version: 1.1 - Détection automatique des durées multiples de 30min
 * 
 * Routes:
 * - POST ?action=close_forfait         → Clôturer avec décompte sur forfait
 * - POST ?action=close_hors_forfait    → Clôturer en facturation hors forfait
 * - POST ?action=close_annule          → Clôturer une intervention annulée sans décompte
 */

require_once __DIR__ . '/../config.php';

header('Content-Type: application/json');

/**
 * Vérifie si la durée entre deux DateTimes est un multiple de 30 minutes
 * @param DateTime $start Heure de début
 * @param DateTime $end Heure de fin
 * @return bool True si la durée est un multiple de 30 minutes
 */
function isMultipleOf30Minutes($start, $end) {
    $interval = $start->diff($end);
    $totalMinutes = ($interval->h * 60) + $interval->i;
    
    return $totalMinutes % 30 === 0;
}

$action = $_GET['action'] ?? $_POST['action'] ?? '';
$method = $_SERVER['REQUEST_METHOD'];

if ($method !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Méthode non autorisée']);
    exit;
}

try {
    $pdo = getDBConnection();
    
    switch ($action) {
        case 'check_heures':
            handleCheckHeures($pdo);
            break;
            
        case 'close_forfait':
            handleCloseForfait($pdo);
            break;
            
        case 'close_hors_forfait':
            handleCloseHorsForfait($pdo);
            break;

        case 'close_annule':
            handleCloseAnnule($pdo);
            break;
            
        case 'recalculate_costs':
            handleRecalculateCosts($pdo);
            break;
            
        default:
            http_response_code(400);
            echo json_encode(['error' => 'Action inconnue']);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}

/**
 * NOUVEAU : Vérifier si les heures sont suffisantes SANS clôturer
 * Utilisé pour décider si on demande signature simple ou vente forfait
 */
function handleCheckHeures($pdo) {
    $data = json_decode(file_get_contents('php://input'), true);
    
    $rendez_vous_id = intval($data['rendez_vous_id'] ?? 0);
    $heure_debut_custom = $data['heure_debut'] ?? null;
    $heure_fin_custom = $data['heure_fin'] ?? null;
    $appliquer_arrondi = isset($data['appliquer_arrondi']) ? (bool)$data['appliquer_arrondi'] : true;
    
    if ($rendez_vous_id <= 0) {
        http_response_code(400);
        echo json_encode(['error' => 'rendez_vous_id requis']);
        exit;
    }
    
    // Récupérer le rendez-vous
    $stmt = $pdo->prepare("SELECT * FROM rendez_vous WHERE id = ?");
    $stmt->execute([$rendez_vous_id]);
    $rdv = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$rdv) {
        http_response_code(404);
        echo json_encode(['error' => 'Rendez-vous introuvable']);
        exit;
    }
    
    if (!$rdv['client_id']) {
        http_response_code(400);
        echo json_encode([
            'error' => 'Pas de client associé à ce rendez-vous',
            'heures_suffisantes' => false
        ]);
        exit;
    }
    
    // Calculer la durée
    $heure_debut = $heure_debut_custom ?? $rdv['heure_debut'];
    $heure_fin = $heure_fin_custom ?? $rdv['heure_fin'];
    
    $debut = new DateTime($heure_debut);
    $fin = new DateTime($heure_fin);
    $diff_minutes = ($fin->getTimestamp() - $debut->getTimestamp()) / 60;
    $duree_heures = $diff_minutes / 60;
    
    // Vérifier si la durée est déjà un multiple de 30 minutes
    $est_multiple_30min = isMultipleOf30Minutes($debut, $fin);
    
    // Arrondi par tranches de 30min (FONCTION handleCheckHeures)
    if ($est_multiple_30min) {
        // Durée déjà arrondie : pas besoin d'arrondir
        $duree_arrondie = $duree_heures;
    } elseif ($appliquer_arrondi) {
        // Arrondi SUPÉRIEUR : ceil pour arrondir vers le haut
        $duree_arrondie = ceil($duree_heures * 2) / 2;
    } else {
        // Arrondi INFÉRIEUR : floor pour arrondir vers le bas
        $duree_arrondie = floor($duree_heures * 2) / 2;
    }
    
    // Calculer le total d'heures restantes pour tous les forfaits du client
    $stmt = $pdo->prepare("
        SELECT SUM(heures_restantes) as total_heures_restantes 
        FROM forfaits_vendus 
        WHERE client_id = ? AND heures_restantes > 0
    ");
    $stmt->execute([$rdv['client_id']]);
    $total = $stmt->fetch(PDO::FETCH_ASSOC);
    $heures_restantes_total = floatval($total['total_heures_restantes'] ?? 0);
    
    // Vérifier si assez d'heures
    if ($heures_restantes_total >= $duree_arrondie) {
        // Heures suffisantes
        echo json_encode([
            'heures_suffisantes' => true,
            'heures_necessaires' => $duree_arrondie,
            'heures_restantes' => $heures_restantes_total,
            'client_id' => $rdv['client_id'],
            'arrondi_necessaire' => !$est_multiple_30min,
            'duree_exacte' => $est_multiple_30min
        ]);
    } else {
        // Heures insuffisantes
        echo json_encode([
            'heures_suffisantes' => false,
            'message' => 'Heures de forfait insuffisantes',
            'besoin_nouveau_forfait' => true,
            'client_id' => $rdv['client_id'],
            'heures_necessaires' => $duree_arrondie,
            'heures_restantes' => $heures_restantes_total,
            'heures_manquantes' => $duree_arrondie - $heures_restantes_total,
            'arrondi_necessaire' => !$est_multiple_30min,
            'duree_exacte' => $est_multiple_30min
        ]);
    }
    exit;
}

function handleCloseForfait($pdo) {
    $data = json_decode(file_get_contents('php://input'), true);
    
    $rendez_vous_id = intval($data['rendez_vous_id'] ?? 0);
    $heure_debut_custom = $data['heure_debut'] ?? null;
    $heure_fin_custom = $data['heure_fin'] ?? null;
    $appliquer_arrondi = isset($data['appliquer_arrondi']) ? (bool)$data['appliquer_arrondi'] : true;
    $force_cloture = isset($data['force_cloture']) ? (bool)$data['force_cloture'] : false;
    $signature_client = $data['signature_client'] ?? null;
    
    if ($rendez_vous_id <= 0) {
        http_response_code(400);
        echo json_encode(['error' => 'rendez_vous_id requis']);
        exit;
    }
    
    // Récupérer le rendez-vous
    $stmt = $pdo->prepare("SELECT * FROM rendez_vous WHERE id = ?");
    $stmt->execute([$rendez_vous_id]);
    $rdv = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$rdv) {
        http_response_code(404);
        echo json_encode(['error' => 'Rendez-vous introuvable']);
        exit;
    }
    
    if (!$rdv['client_id']) {
        http_response_code(400);
        echo json_encode(['error' => 'Pas de client associé à ce rendez-vous']);
        exit;
    }

    if (($rdv['statut'] ?? '') === 'annule') {
        http_response_code(400);
        echo json_encode(['error' => 'Une intervention annulée ne peut pas être clôturée avec décompte de forfait']);
        exit;
    }
    
    // Calculer la durée
    $heure_debut = $heure_debut_custom ?? $rdv['heure_debut'];
    $heure_fin = $heure_fin_custom ?? $rdv['heure_fin'];
    
    $debut = new DateTime($heure_debut);
    $fin = new DateTime($heure_fin);
    $diff_minutes = ($fin->getTimestamp() - $debut->getTimestamp()) / 60;
    $duree_heures = $diff_minutes / 60;
    
    // Vérifier si la durée est déjà un multiple de 30 minutes
    $est_multiple_30min = isMultipleOf30Minutes($debut, $fin);
    
    // Arrondi par tranches de 30min (FONCTION handleCloseForfait)
    if ($est_multiple_30min) {
        // Durée déjà arrondie : pas besoin d'arrondir
        $duree_arrondie = $duree_heures;
    } elseif ($appliquer_arrondi) {
        // Arrondi SUPÉRIEUR : ceil pour arrondir vers le haut
        $duree_arrondie = ceil($duree_heures * 2) / 2;
    } else {
        // Arrondi INFÉRIEUR : floor pour arrondir vers le bas
        $duree_arrondie = floor($duree_heures * 2) / 2;
    }
    
    // Calculer le total d'heures restantes pour tous les forfaits du client
    $stmt = $pdo->prepare("
        SELECT SUM(heures_restantes) as total_heures_restantes 
        FROM forfaits_vendus 
        WHERE client_id = ? AND heures_restantes > 0
    ");
    $stmt->execute([$rdv['client_id']]);
    $total = $stmt->fetch(PDO::FETCH_ASSOC);
    $heures_restantes_total = floatval($total['total_heures_restantes'] ?? 0);
    
    // Vérifier si assez d'heures au total
    if ($heures_restantes_total < $duree_arrondie && !$force_cloture) {
        http_response_code(400);
        echo json_encode([
            'error' => 'Heures de forfait insuffisantes',
            'besoin_nouveau_forfait' => true,
            'client_id' => $rdv['client_id'],
            'heures_necessaires' => $duree_arrondie,
            'heures_restantes' => $heures_restantes_total,
            'duree_calculee' => $duree_arrondie
        ]);
        exit;
    }
    
    // Récupérer tous les forfaits avec heures restantes (FIFO)
    $stmt = $pdo->prepare("
        SELECT * FROM forfaits_vendus 
        WHERE client_id = ? AND heures_restantes > 0
        ORDER BY created_at ASC
    ");
    $stmt->execute([$rdv['client_id']]);
    $forfaits = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Décompter sur plusieurs forfaits si nécessaire
    $heures_a_decompter = $duree_arrondie;
    $heures_avant_total = $heures_restantes_total;
    $forfaits_utilises = [];
    
    foreach ($forfaits as $forfait) {
        if ($heures_a_decompter <= 0) break;
        
        $heures_disponibles = floatval($forfait['heures_restantes']);
        $heures_prises = min($heures_disponibles, $heures_a_decompter);
        
        // IMPORTANT : Arrondir les heures_prises à un multiple de 0.5h (30 min)
        // pour garantir que heures_restantes reste toujours un multiple de 0.5h
        $heures_prises = round($heures_prises * 2) / 2;
        
        // Décompter sur ce forfait
        $stmt = $pdo->prepare("
            UPDATE forfaits_vendus 
            SET heures_restantes = ROUND((heures_restantes - ?) * 2) / 2
            WHERE id = ?
        ");
        $stmt->execute([$heures_prises, $forfait['id']]);
        
        $forfaits_utilises[] = [
            'id' => $forfait['id'],
            'heures_prises' => $heures_prises,
            'heures_avant' => $heures_disponibles,
            'heures_apres' => round(($heures_disponibles - $heures_prises) * 2) / 2
        ];
        
        $heures_a_decompter -= $heures_prises;
    }
    
    // Calculer la différence d'arrondi (bonus/malus CLIENT)
    // Bonus client (positif) = on facture MOINS que le temps acheté → client a du crédit
    // Malus client (négatif) = on facture PLUS que le temps acheté → client a une dette
    // Formule : temps_facturé - temps_réel
    $difference_arrondi = $duree_arrondie - $duree_heures;
    
    // Utiliser le premier forfait pour l'historique (ou NULL si aucun)
    $forfait_principal = $forfaits[0] ?? null;
    $heures_avant = $heures_avant_total;
    $heures_decomptes = $duree_arrondie;
    $heures_apres = $heures_avant_total - $duree_arrondie;
    
    // Créer l'entrée historique (toujours, même sans forfait pour garder une trace)
    $stmt = $pdo->prepare("
        INSERT INTO historique_consommation 
        (rendez_vous_id, forfait_vendu_id, client_id, temps_reel, temps_arrondi, difference_arrondi, 
         heures_decomptes, heures_avant, heures_apres, date_rdv, heure_debut, heure_fin)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    
    $stmt->execute([
        $rendez_vous_id,
        $forfait_principal['id'] ?? null,
        $rdv['client_id'],
        $duree_heures,
        $duree_arrondie,
        $difference_arrondi,
        $heures_decomptes,
        $heures_avant,
        $heures_apres,
        $rdv['date_rdv'],
        $heure_debut,
        $heure_fin
    ]);
    
    // Mettre à jour le rendez-vous : heures réelles, durée réelle et signature
    $stmt = $pdo->prepare("
        UPDATE rendez_vous 
        SET statut = 'termine', 
            heure_debut = ?, 
            heure_fin = ?, 
            duree_reelle = ?,
            signature_client = ?
        WHERE id = ?
    ");
    $stmt->execute([$heure_debut, $heure_fin, $duree_heures, $signature_client, $rendez_vous_id]);
    
    // Calculer et enregistrer les coûts de l'intervention
    calculerCoutsIntervention($pdo, $rendez_vous_id, $duree_heures);
    
    // Mettre à jour le bonus du client (cumuler la différence d'arrondi) TOUJOURS
    // Même sans forfait, on track le bonus/malus pour visibilité client
    $stmt = $pdo->prepare("
        UPDATE clients 
        SET heure_bonus = COALESCE(heure_bonus, 0) + ?
        WHERE id = ?
    ");
    $stmt->execute([$difference_arrondi, $rdv['client_id']]);
    
    echo json_encode([
        'success' => true,
        'temps_reel' => round($duree_heures, 2),
        'temps_arrondi' => $duree_arrondie,
        'difference_arrondi' => round($difference_arrondi, 2),
        'heures_decomptes' => $heures_decomptes,
        'heures_avant' => $heures_avant,
        'heures_apres' => $heures_apres,
        'forfait_id' => $forfait_principal['id'] ?? null,
        'forfaits_utilises' => $forfaits_utilises,
        'nb_forfaits_utilises' => count($forfaits_utilises),
        'arrondi_applique' => !$est_multiple_30min,
        'duree_exacte' => $est_multiple_30min
    ]);
}

function handleCloseHorsForfait($pdo) {
    $data = json_decode(file_get_contents('php://input'), true);
    
    $rendez_vous_id = intval($data['rendez_vous_id'] ?? 0);
    $heure_debut = $data['heure_debut'] ?? null;
    $heure_fin = $data['heure_fin'] ?? null;
    $tarif_horaire = floatval($data['tarif_horaire'] ?? 0);
    
    if ($rendez_vous_id <= 0 || !$heure_debut || !$heure_fin || $tarif_horaire <= 0) {
        http_response_code(400);
        echo json_encode(['error' => 'rendez_vous_id, heure_debut, heure_fin et tarif_horaire requis']);
        exit;
    }
    
    // Récupérer le rendez-vous
    $stmt = $pdo->prepare("SELECT * FROM rendez_vous WHERE id = ?");
    $stmt->execute([$rendez_vous_id]);
    $rdv = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$rdv) {
        http_response_code(404);
        echo json_encode(['error' => 'Rendez-vous introuvable']);
        exit;
    }
    
    // Calculer durée
    $debut = new DateTime($heure_debut);
    $fin = new DateTime($heure_fin);
    $diff_minutes = ($fin->getTimestamp() - $debut->getTimestamp()) / 60;
    
    // Règle: 1ère heure = 1h, puis tranches de 30min
    if ($diff_minutes <= 60) {
        $heures_facturees = 1.0;
    } else {
        $minutes_supplementaires = $diff_minutes - 60;
        $tranches_30min = ceil($minutes_supplementaires / 30);
        $heures_facturees = 1.0 + ($tranches_30min * 0.5);
    }
    
    $montant_total = $heures_facturees * $tarif_horaire;
    
    // Insérer dans facturation hors forfait
    $stmt = $pdo->prepare("
        INSERT INTO facturation_hors_forfait 
        (rendez_vous_id, client_id, date_intervention, heure_debut, heure_fin, duree_reelle, quantite, tarif_horaire, montant_total)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    
    $stmt->execute([
        $rendez_vous_id,
        $rdv['client_id'],
        $rdv['date_rdv'],
        $heure_debut,
        $heure_fin,
        round($diff_minutes / 60, 2),
        $heures_facturees,
        $tarif_horaire,
        $montant_total
    ]);
    
    // Mettre à jour le rendez-vous avec les heures réelles et la durée
    $stmt = $pdo->prepare("
        UPDATE rendez_vous 
        SET statut = 'termine', 
            heure_debut = ?, 
            heure_fin = ?, 
            duree_reelle = ?
        WHERE id = ?
    ");
    $stmt->execute([$heure_debut, $heure_fin, round($diff_minutes / 60, 2), $rendez_vous_id]);
    
    // Calculer et enregistrer les coûts de l'intervention
    calculerCoutsIntervention($pdo, $rendez_vous_id, round($diff_minutes / 60, 2));
    
    // Créer aussi un historique pour traçabilité (avec forfait_vendu_id NULL car hors forfait)
    $duree_heures = round($diff_minutes / 60, 2);
    // Calculer la vraie différence d'arrondi (bonus/malus client)
    $difference_arrondi = $heures_facturees - $duree_heures;
    
    $stmt = $pdo->prepare("
        INSERT INTO historique_consommation 
        (rendez_vous_id, forfait_vendu_id, client_id, temps_reel, temps_arrondi, difference_arrondi, 
         heures_decomptes, heures_avant, heures_apres, date_rdv, heure_debut, heure_fin)
        VALUES (?, NULL, ?, ?, ?, ?, 0, 0, 0, ?, ?, ?)
    ");
    $stmt->execute([
        $rendez_vous_id,
        $rdv['client_id'],
        $duree_heures,
        $heures_facturees,
        $difference_arrondi,
        $rdv['date_rdv'],
        $heure_debut,
        $heure_fin
    ]);
    
    // Mettre à jour le bonus client (même en hors forfait pour traçabilité)
    $stmt = $pdo->prepare("
        UPDATE clients 
        SET heure_bonus = COALESCE(heure_bonus, 0) + ?
        WHERE id = ?
    ");
    $stmt->execute([$difference_arrondi, $rdv['client_id']]);
    
    echo json_encode([
        'success' => true,
        'temps_reel_minutes' => $diff_minutes,
        'temps_reel' => round($diff_minutes / 60, 2),
        'quantite_facturee' => $heures_facturees,
        'tarif_horaire' => $tarif_horaire,
        'montant_total' => round($montant_total, 2)
    ]);
}

function handleCloseAnnule($pdo) {
    $data = json_decode(file_get_contents('php://input'), true);

    $rendez_vous_id = intval($data['rendez_vous_id'] ?? 0);
    $heure_debut = $data['heure_debut'] ?? null;
    $heure_fin = $data['heure_fin'] ?? null;
    $signature_client = $data['signature_client'] ?? null;

    if ($rendez_vous_id <= 0 || !$heure_debut || !$heure_fin) {
        http_response_code(400);
        echo json_encode(['error' => 'rendez_vous_id, heure_debut et heure_fin requis']);
        exit;
    }

    $stmt = $pdo->prepare("SELECT * FROM rendez_vous WHERE id = ?");
    $stmt->execute([$rendez_vous_id]);
    $rdv = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$rdv) {
        http_response_code(404);
        echo json_encode(['error' => 'Rendez-vous introuvable']);
        exit;
    }

    $debut = new DateTime($heure_debut);
    $fin = new DateTime($heure_fin);
    $diff_minutes = ($fin->getTimestamp() - $debut->getTimestamp()) / 60;
    $duree_heures = round($diff_minutes / 60, 2);

    $stmt = $pdo->prepare("\
        INSERT INTO historique_consommation 
        (rendez_vous_id, forfait_vendu_id, client_id, temps_reel, temps_arrondi, difference_arrondi, 
         heures_decomptes, heures_avant, heures_apres, date_rdv, heure_debut, heure_fin)
        VALUES (?, NULL, ?, ?, ?, 0, 0, 0, 0, ?, ?, ?)
    ");
    $stmt->execute([
        $rendez_vous_id,
        $rdv['client_id'],
        $duree_heures,
        $duree_heures,
        $rdv['date_rdv'],
        $heure_debut,
        $heure_fin
    ]);

    $stmt = $pdo->prepare("\
        UPDATE rendez_vous 
        SET statut = 'annule', 
            heure_debut = ?, 
            heure_fin = ?, 
            duree_reelle = ?,
            signature_client = ?
        WHERE id = ?
    ");
    $stmt->execute([$heure_debut, $heure_fin, $duree_heures, $signature_client, $rendez_vous_id]);

    calculerCoutsIntervention($pdo, $rendez_vous_id, 0);

    echo json_encode([
        'success' => true,
        'temps_reel' => $duree_heures,
        'temps_arrondi' => $duree_heures,
        'difference_arrondi' => 0,
        'heures_decomptes' => 0,
        'heures_avant' => null,
        'heures_apres' => null,
        'forfait_id' => null,
        'forfaits_utilises' => [],
        'nb_forfaits_utilises' => 0,
        'arrondi_applique' => false,
        'duree_exacte' => true,
        'statut' => 'annule'
    ]);
}

/**
 * Calcule et enregistre les coûts d'une intervention
 * @param PDO $pdo Connexion à la base de données
 * @param int $rendez_vous_id ID du rendez-vous
 * @param float $duree_heures Durée réelle de l'intervention en heures
 */
function calculerCoutsIntervention($pdo, $rendez_vous_id, $duree_heures) {
    try {
        // Récupérer les infos du rendez-vous avec le véhicule principal du technicien si vehicule_id est NULL
        $stmt = $pdo->prepare("
            SELECT r.*, 
                   t.cout_horaire_total,
                   COALESCE(v1.id, v2.id) as vehicule_effectif_id,
                   COALESCE(v1.mode_calcul_cout, v2.mode_calcul_cout) as mode_calcul_cout,
                   COALESCE(v1.type_vehicule, v2.type_vehicule) as type_vehicule,
                   COALESCE(v1.puissance_fiscale, v2.puissance_fiscale) as puissance_fiscale,
                   COALESCE(v1.kilometrage_annuel_estime, v2.kilometrage_annuel_estime) as kilometrage_annuel_estime,
                   COALESCE(v1.cout_carburant_km, v2.cout_carburant_km) as cout_carburant_km,
                   COALESCE(v1.cout_usure_km, v2.cout_usure_km) as cout_usure_km
            FROM rendez_vous r
            LEFT JOIN techniciens t ON r.id_technicien = t.id
            LEFT JOIN vehicules v1 ON r.vehicule_id = v1.id
            LEFT JOIN techniciens_vehicules tv ON t.id = tv.id_technicien AND tv.date_fin IS NULL AND tv.principal = 1
            LEFT JOIN vehicules v2 ON tv.id_vehicule = v2.id
            WHERE r.id = ?
        ");
        $stmt->execute([$rendez_vous_id]);
        $rdv = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$rdv) return;
        
        // Si vehicule_id est NULL, l'assigner automatiquement depuis le véhicule principal du technicien
        if (!$rdv['vehicule_id'] && $rdv['vehicule_effectif_id']) {
            $stmt_update_vehicule = $pdo->prepare("UPDATE rendez_vous SET vehicule_id = ? WHERE id = ?");
            $stmt_update_vehicule->execute([$rdv['vehicule_effectif_id'], $rendez_vous_id]);
        }
        
        // Calcul coût technicien
        $cout_technicien = 0;
        if ($rdv['cout_horaire_total'] && $duree_heures > 0) {
            $cout_technicien = floatval($rdv['cout_horaire_total']) * $duree_heures;
        }
        
        // Calcul coût véhicule
        $cout_vehicule = 0;
        $bareme_id = null;
        
        if ($rdv['distance_km'] && $rdv['distance_km'] > 0) {
            $mode_calcul = $rdv['mode_calcul_cout'] ?? 'cout_reel';
            
            if ($mode_calcul === 'bareme_fiscal' && $rdv['puissance_fiscale']) {
                // Utiliser le barème kilométrique fiscal
                $resultat = calculerCoutBaremeFiscal(
                    $pdo,
                    $rdv['type_vehicule'],
                    $rdv['puissance_fiscale'],
                    $rdv['kilometrage_annuel_estime'] ?? 15000,
                    $rdv['distance_km'],
                    $rdv['date_rdv']
                );
                $cout_vehicule = $resultat['cout'];
                $bareme_id = $resultat['bareme_id'];
            } else {
                // Méthode classique : coût réel
                $cout_km = floatval($rdv['cout_carburant_km'] ?? 0) + floatval($rdv['cout_usure_km'] ?? 0);
                $cout_vehicule = $cout_km * floatval($rdv['distance_km']);
            }
        }
        
        // Mettre à jour les coûts
        $cout_total = $cout_technicien + $cout_vehicule;
        
        $stmt = $pdo->prepare("
            UPDATE rendez_vous 
            SET cout_technicien = ?, 
                cout_vehicule = ?,
                cout_total = ?,
                bareme_km_utilise_id = ?
            WHERE id = ?
        ");
        $stmt->execute([$cout_technicien, $cout_vehicule, $cout_total, $bareme_id, $rendez_vous_id]);
        
    } catch (Exception $e) {
        // En cas d'erreur, on ne bloque pas la clôture
        error_log("Erreur calcul coûts intervention $rendez_vous_id: " . $e->getMessage());
    }
}

/**
 * Calcule le coût kilométrique selon le barème fiscal
 * @param PDO $pdo
 * @param string $type_vehicule Type de véhicule
 * @param int $puissance_fiscale Chevaux fiscaux
 * @param int $kilometrage_annuel Kilométrage annuel estimé
 * @param float $distance Distance parcourue pour l'intervention
 * @param string $date_intervention Date de l'intervention
 * @return array ['cout' => float, 'bareme_id' => int]
 */
function calculerCoutBaremeFiscal($pdo, $type_vehicule, $puissance_fiscale, $kilometrage_annuel, $distance, $date_intervention) {
    try {
        // Convertir le type de véhicule (compatible PHP 7.x et 8.x)
        switch($type_vehicule) {
            case 'voiture':
            case 'utilitaire':
            case 'camionnette':
                $type_bareme = 'voiture';
                break;
            case 'moto':
                $type_bareme = 'moto';
                break;
            case 'scooter':
                $type_bareme = 'scooter';
                break;
            case 'cyclomoteur':
                $type_bareme = 'cyclomoteur';
                break;
            default:
                $type_bareme = 'voiture';
        }
        
        // Déterminer l'année fiscale
        $annee_fiscale = intval(date('Y', strtotime($date_intervention)));
        
        // Chercher le barème approprié
        $stmt = $pdo->prepare("
            SELECT id, cout_fixe, cout_variable, formule_calcul
            FROM bareme_kilometrique
            WHERE annee_fiscale = ?
              AND type_vehicule = ?
              AND puissance_min <= ?
              AND puissance_max >= ?
              AND distance_min <= ?
              AND distance_max >= ?
            ORDER BY distance_max ASC
            LIMIT 1
        ");
        
        $stmt->execute([
            $annee_fiscale,
            $type_bareme,
            $puissance_fiscale,
            $puissance_fiscale,
            $kilometrage_annuel,
            $kilometrage_annuel
        ]);
        
        $bareme = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$bareme) {
            // Si pas de barème trouvé pour l'année, essayer l'année précédente
            $stmt->execute([
                $annee_fiscale - 1,
                $type_bareme,
                $puissance_fiscale,
                $puissance_fiscale,
                $kilometrage_annuel,
                $kilometrage_annuel
            ]);
            $bareme = $stmt->fetch(PDO::FETCH_ASSOC);
        }
        
        if (!$bareme) {
            // Pas de barème trouvé, retourner 0
            return ['cout' => 0, 'bareme_id' => null];
        }
        
        // Calculer le coût
        $d = floatval($distance);
        $cout = ($d * floatval($bareme['cout_variable'])) + floatval($bareme['cout_fixe']);
        
        return [
            'cout' => round($cout, 2),
            'bareme_id' => $bareme['id']
        ];
        
    } catch (Exception $e) {
        error_log("Erreur calcul barème fiscal: " . $e->getMessage());
        return ['cout' => 0, 'bareme_id' => null];
    }
}

/**
 * Recalcule les coûts de toutes les interventions terminées
 */
function handleRecalculateCosts($pdo) {
    try {
        // Récupérer toutes les interventions terminées avec duree_reelle
        $stmt = $pdo->query("
            SELECT id, duree_reelle 
            FROM rendez_vous 
            WHERE statut = 'termine' 
              AND duree_reelle IS NOT NULL 
              AND duree_reelle > 0
        ");
        $interventions = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $updated = 0;
        foreach ($interventions as $inter) {
            $duree_heures = floatval($inter['duree_reelle']); // Déjà en heures
            calculerCoutsIntervention($pdo, $inter['id'], $duree_heures);
            $updated++;
        }
        
        echo json_encode([
            'status' => 'success',
            'message' => "$updated interventions recalculées",
            'updated' => $updated
        ]);
        
    } catch (Exception $e) {
        throw $e;
    }
}

