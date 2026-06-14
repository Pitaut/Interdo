<?php
require_once 'config.php';

$pdo = getDBConnection();

// Récupérer la période d'analyse
$periode = $_GET['periode'] ?? 'mois_en_cours';
$date_debut_custom = $_GET['date_debut'] ?? '';
$date_fin_custom = $_GET['date_fin'] ?? '';

// Calculer les dates selon la période
switch ($periode) {
    case 'mois_en_cours':
        $date_debut = date('Y-m-01');
        $date_fin = date('Y-m-t');
        $label_periode = 'Mois en cours (' . date('F Y') . ')';
        break;
    case 'annee_en_cours':
        $date_debut = date('Y-01-01');
        $date_fin = date('Y-12-31');
        $label_periode = 'Année ' . date('Y');
        break;
    case 'personnalise':
        $date_debut = $date_debut_custom ?: date('Y-m-01');
        $date_fin = $date_fin_custom ?: date('Y-m-t');
        $label_periode = 'Du ' . date('d/m/Y', strtotime($date_debut)) . ' au ' . date('d/m/Y', strtotime($date_fin));
        break;
    default:
        $date_debut = date('Y-m-01');
        $date_fin = date('Y-m-t');
        $label_periode = 'Mois en cours';
}

// ============================================
// STATISTIQUES TECHNICIENS
// ============================================

// Récupérer tous les techniciens actifs
$stmt = $pdo->query("SELECT id, nom, prenom FROM techniciens WHERE actif = 1 ORDER BY nom, prenom");
$techniciens = $stmt->fetchAll(PDO::FETCH_ASSOC);

$stats_techniciens = [];

foreach ($techniciens as $tech) {
    $tech_id = $tech['id'];
    $tech_nom_complet = $tech['prenom'] . ' ' . $tech['nom'];
    
    // Nombre d'interventions clôturées
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as nb_interventions
        FROM historique_consommation hc
        INNER JOIN rendez_vous rv ON hc.rendez_vous_id = rv.id
        WHERE rv.id_technicien = ?
        AND hc.date_rdv BETWEEN ? AND ?
    ");
    $stmt->execute([$tech_id, $date_debut, $date_fin]);
    $nb_interventions = $stmt->fetchColumn();
    
    // Heures d'interventions réalisées
    $stmt = $pdo->prepare("
        SELECT COALESCE(SUM(hc.heures_decomptes), 0) as total_heures
        FROM historique_consommation hc
        INNER JOIN rendez_vous rv ON hc.rendez_vous_id = rv.id
        WHERE rv.id_technicien = ?
        AND hc.date_rdv BETWEEN ? AND ?
    ");
    $stmt->execute([$tech_id, $date_debut, $date_fin]);
    $total_heures = floatval($stmt->fetchColumn());
    
    // Durée moyenne
    $duree_moyenne = $nb_interventions > 0 ? $total_heures / $nb_interventions : 0;
    
    // Nombre de forfaits vendus (clients ayant eu une intervention avec ce technicien sur la période)
    $stmt = $pdo->prepare("
        SELECT COUNT(DISTINCT fv.id) as nb_forfaits
        FROM forfaits_vendus fv
        WHERE fv.client_id IN (
            SELECT DISTINCT rv.client_id
            FROM rendez_vous rv
            INNER JOIN historique_consommation hc ON rv.id = hc.rendez_vous_id
            WHERE rv.id_technicien = ?
            AND hc.date_rdv BETWEEN ? AND ?
        )
        AND COALESCE(fv.date_vente, DATE(fv.created_at)) BETWEEN ? AND ?
    ");
    $stmt->execute([$tech_id, $date_debut, $date_fin, $date_debut, $date_fin]);
    $nb_forfaits = intval($stmt->fetchColumn());
    
    // Forfait le plus vendu (pour ce technicien)
    $stmt = $pdo->prepare("
        SELECT tf.type_forfait, COUNT(*) as nb
        FROM forfaits_vendus fv
        INNER JOIN type_forfait tf ON fv.type_forfait_id = tf.id
        WHERE fv.client_id IN (
            SELECT DISTINCT rv.client_id
            FROM rendez_vous rv
            INNER JOIN historique_consommation hc ON rv.id = hc.rendez_vous_id
            WHERE rv.id_technicien = ?
            AND hc.date_rdv BETWEEN ? AND ?
        )
        AND COALESCE(fv.date_vente, DATE(fv.created_at)) BETWEEN ? AND ?
        GROUP BY tf.type_forfait
        ORDER BY nb DESC
        LIMIT 1
    ");
    $stmt->execute([$tech_id, $date_debut, $date_fin, $date_debut, $date_fin]);
    $forfait_plus_vendu = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Heures vendues (total des heures de forfaits vendus)
    $stmt = $pdo->prepare("
        SELECT COALESCE(SUM(fv.heures_total), 0) as heures_vendues
        FROM forfaits_vendus fv
        WHERE fv.client_id IN (
            SELECT DISTINCT rv.client_id
            FROM rendez_vous rv
            INNER JOIN historique_consommation hc ON rv.id = hc.rendez_vous_id
            WHERE rv.id_technicien = ?
            AND hc.date_rdv BETWEEN ? AND ?
        )
        AND COALESCE(fv.date_vente, DATE(fv.created_at)) BETWEEN ? AND ?
    ");
    $stmt->execute([$tech_id, $date_debut, $date_fin, $date_debut, $date_fin]);
    $heures_vendues = floatval($stmt->fetchColumn());
    
    // Montant facturé (forfaits + hors forfait pour les clients de ce technicien)
    $stmt = $pdo->prepare("
        SELECT COALESCE(SUM(fv.tarif), 0) as ca_forfaits
        FROM forfaits_vendus fv
        WHERE fv.client_id IN (
            SELECT DISTINCT rv.client_id
            FROM rendez_vous rv
            INNER JOIN historique_consommation hc ON rv.id = hc.rendez_vous_id
            WHERE rv.id_technicien = ?
            AND hc.date_rdv BETWEEN ? AND ?
        )
        AND COALESCE(fv.date_vente, DATE(fv.created_at)) BETWEEN ? AND ?
    ");
    $stmt->execute([$tech_id, $date_debut, $date_fin, $date_debut, $date_fin]);
    $ca_forfaits_tech = floatval($stmt->fetchColumn());
    
    $stmt = $pdo->prepare("
        SELECT COALESCE(SUM(fh.montant_total), 0) as ca_hors_forfait
        FROM facturation_hors_forfait fh
        WHERE fh.client_id IN (
            SELECT DISTINCT rv.client_id
            FROM rendez_vous rv
            INNER JOIN historique_consommation hc ON rv.id = hc.rendez_vous_id
            WHERE rv.id_technicien = ?
            AND hc.date_rdv BETWEEN ? AND ?
        )
        AND fh.created_at BETWEEN ? AND ?
    ");
    $stmt->execute([$tech_id, $date_debut, $date_fin, $date_debut, $date_fin]);
    $ca_hors_forfait_tech = floatval($stmt->fetchColumn());
    
    $montant_facture = $ca_forfaits_tech + $ca_hors_forfait_tech;
    
    // Ne garder que les techniciens avec au moins une activité
    if ($nb_interventions > 0 || $nb_forfaits > 0) {
        $stats_techniciens[] = [
            'nom' => $tech_nom_complet,
            'nb_interventions' => $nb_interventions,
            'total_heures' => $total_heures,
            'duree_moyenne' => $duree_moyenne,
            'nb_forfaits' => $nb_forfaits,
            'forfait_plus_vendu' => $forfait_plus_vendu ? $forfait_plus_vendu['type_forfait'] : 'N/A',
            'nb_forfait_plus_vendu' => $forfait_plus_vendu ? $forfait_plus_vendu['nb'] : 0,
            'heures_vendues' => $heures_vendues,
            'montant_facture' => $montant_facture
        ];
    }
}

// Calculer les totaux
$total_interventions = 0;
$total_heures_realisees = 0;
$total_forfaits_vendus = 0;
$total_heures_vendues = 0;
$total_montant_facture = 0;

foreach ($stats_techniciens as $stat) {
    $total_interventions += $stat['nb_interventions'];
    $total_heures_realisees += $stat['total_heures'];
    $total_forfaits_vendus += $stat['nb_forfaits'];
    $total_heures_vendues += $stat['heures_vendues'];
    $total_montant_facture += $stat['montant_facture'];
}

$duree_moyenne_globale = $total_interventions > 0 ? $total_heures_realisees / $total_interventions : 0;

// ============================================
// STATISTIQUES GLOBALES
// ============================================

// CA total de la période = somme des montants facturés par les techniciens
$ca_total_periode = $total_montant_facture;

// Nombre de clients actifs (avec heures > 0)
$stmt = $pdo->query("
    SELECT COUNT(DISTINCT c.id) as nb_actifs
    FROM clients c
    INNER JOIN forfaits_vendus fv ON c.id = fv.client_id
    WHERE fv.heures_restantes > 0
");
$nb_clients_actifs = intval($stmt->fetchColumn());

// Taux de conversion forfait
$taux_conversion = $total_heures_vendues > 0 ? ($total_heures_realisees / $total_heures_vendues) * 100 : 0;

// Total des heures non consommées (heures restantes sur tous les forfaits)
$stmt = $pdo->query("
    SELECT COALESCE(SUM(heures_restantes), 0) as total_heures_non_consommees
    FROM forfaits_vendus
");
$total_heures_non_consommees = floatval($stmt->fetchColumn());

// Clients à risque (critères élargis pour mieux identifier les clients à recontacter)
// 1. Clients avec 0h restantes ET pas de rappel depuis 30 jours
// 2. Clients avec < 2h restantes ET pas d'intervention depuis 60 jours
// 3. Clients avec forfait mais aucune intervention depuis 90 jours
$stmt = $pdo->query("
    SELECT COUNT(DISTINCT c.id) as nb_risque
    FROM clients c
    LEFT JOIN (
        SELECT client_id, SUM(heures_restantes) as total_heures
        FROM forfaits_vendus
        GROUP BY client_id
    ) fv ON c.id = fv.client_id
    LEFT JOIN (
        SELECT client_id, MAX(date_rdv) as derniere_intervention
        FROM historique_consommation
        GROUP BY client_id
    ) hc ON c.id = hc.client_id
    WHERE (
        -- Cas 1: 0h et pas de rappel depuis 30 jours
        (COALESCE(fv.total_heures, 0) = 0 
         AND (c.date_dernier_rappel IS NULL OR c.date_dernier_rappel < DATE_SUB(NOW(), INTERVAL 30 DAY)))
        
        OR
        
        -- Cas 2: < 2h et pas d'intervention depuis 60 jours
        (COALESCE(fv.total_heures, 0) > 0 
         AND COALESCE(fv.total_heures, 0) < 2
         AND (hc.derniere_intervention IS NULL OR hc.derniere_intervention < DATE_SUB(NOW(), INTERVAL 60 DAY)))
        
        OR
        
        -- Cas 3: A du forfait mais aucune intervention depuis 90 jours
        (COALESCE(fv.total_heures, 0) >= 2
         AND (hc.derniere_intervention IS NULL OR hc.derniere_intervention < DATE_SUB(NOW(), INTERVAL 90 DAY)))
    )
");
$nb_clients_risque = intval($stmt->fetchColumn());

// Clients inactifs (aucune intervention depuis 3 mois)
$stmt = $pdo->query("
    SELECT COUNT(DISTINCT c.id) as nb_inactifs
    FROM clients c
    WHERE c.id NOT IN (
        SELECT DISTINCT hc.client_id
        FROM historique_consommation hc
        WHERE hc.date_rdv >= DATE_SUB(NOW(), INTERVAL 3 MONTH)
    )
");
$nb_clients_inactifs = intval($stmt->fetchColumn());

// Répartition par source d'acquisition
$stmt = $pdo->query("
    SELECT 
        source_acquisition,
        COUNT(*) as nb_clients
    FROM clients
    WHERE source_acquisition IS NOT NULL
    GROUP BY source_acquisition
    ORDER BY nb_clients DESC
");
$repartition_sources = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Répartition par mode de paiement
$stmt = $pdo->query("
    SELECT 
        mode_paiement,
        COUNT(*) as nb_clients
    FROM clients
    WHERE mode_paiement IS NOT NULL
    GROUP BY mode_paiement
    ORDER BY nb_clients DESC
");
$repartition_paiements = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Top 10 clients les plus rentables
$stmt = $pdo->query("
    SELECT 
        c.id,
        c.nom,
        c.prenom,
        COALESCE(SUM(fv.tarif), 0) + COALESCE((
            SELECT SUM(fh.montant_total)
            FROM facturation_hors_forfait fh
            WHERE fh.client_id = c.id
        ), 0) as ca_total,
        COUNT(DISTINCT fv.id) as nb_forfaits_achetes
    FROM clients c
    LEFT JOIN forfaits_vendus fv ON c.id = fv.client_id
    GROUP BY c.id
    ORDER BY ca_total DESC
    LIMIT 10
");
$top_clients = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Forfaits les plus vendus (tous techniciens)
$stmt = $pdo->prepare("
    SELECT 
        tf.type_forfait,
        COUNT(*) as nb_ventes,
        SUM(fv.heures_total) as total_heures
    FROM forfaits_vendus fv
    INNER JOIN type_forfait tf ON fv.type_forfait_id = tf.id
    WHERE COALESCE(fv.date_vente, DATE(fv.created_at)) BETWEEN ? AND ?
    GROUP BY tf.type_forfait
    ORDER BY nb_ventes DESC
");
$stmt->execute([$date_debut, $date_fin]);
$forfaits_plus_vendus = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Statistiques techniciens enrichies
foreach ($stats_techniciens as &$stat_tech) {
    // Récupérer l'ID du technicien
    $stmt = $pdo->prepare("SELECT id FROM techniciens WHERE CONCAT(prenom, ' ', nom) = ?");
    $stmt->execute([$stat_tech['nom']]);
    $tech_id = $stmt->fetchColumn();
    
    if ($tech_id) {
        // Nombre de clients différents servis
        $stmt = $pdo->prepare("
            SELECT COUNT(DISTINCT rv.client_id) as nb_clients
            FROM rendez_vous rv
            INNER JOIN historique_consommation hc ON rv.id = hc.rendez_vous_id
            WHERE rv.id_technicien = ?
            AND hc.date_rdv BETWEEN ? AND ?
        ");
        $stmt->execute([$tech_id, $date_debut, $date_fin]);
        $stat_tech['nb_clients_servis'] = intval($stmt->fetchColumn());
    } else {
        $stat_tech['nb_clients_servis'] = 0;
    }
}
unset($stat_tech);

// ============================================
// STATISTIQUES CLIENTS - Heures restantes
// ============================================

$stmt = $pdo->prepare("
    SELECT 
        c.id,
        c.nom,
        c.prenom,
        c.email,
        c.telephone_fixe,
        c.telephone_mobile,
        c.date_dernier_rappel,
        c.commentaire_rappel,
        COALESCE(SUM(fv.heures_restantes), 0) as heures_restantes_total,
        (
            SELECT tf.type_forfait 
            FROM forfaits_vendus fv2
            INNER JOIN type_forfait tf ON fv2.type_forfait_id = tf.id
            WHERE fv2.client_id = c.id
            ORDER BY COALESCE(fv2.date_vente, fv2.created_at) DESC
            LIMIT 1
        ) as dernier_forfait_vendu,
        (
            SELECT COALESCE(fv2.date_vente, fv2.created_at)
            FROM forfaits_vendus fv2
            WHERE fv2.client_id = c.id
            ORDER BY COALESCE(fv2.date_vente, fv2.created_at) DESC
            LIMIT 1
        ) as date_dernier_forfait,
        (
            SELECT MAX(hc.date_rdv)
            FROM historique_consommation hc
            WHERE hc.client_id = c.id
        ) as date_derniere_intervention
    FROM clients c
    LEFT JOIN forfaits_vendus fv ON c.id = fv.client_id
    GROUP BY c.id
    ORDER BY heures_restantes_total ASC, c.nom, c.prenom
");
$stmt->execute();
$stats_clients = $stmt->fetchAll(PDO::FETCH_ASSOC);

?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Statistiques - <?php echo APP_NAME; ?></title>
    <link rel="stylesheet" href="includes/common_styles.css">
    <style>
        body {
            padding: 0;
        }
            margin: 0 auto;
        }
        .header {
            background: white;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        h1 {
            color: #333;
            margin-bottom: 20px;
        }
        .periode-selector {
            display: flex;
            gap: 15px;
            align-items: center;
            flex-wrap: wrap;
        }
        .periode-selector label {
            font-weight: 500;
        }
        .periode-selector select,
        .periode-selector input {
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
        }
        .btn {
            padding: 8px 16px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 500;
            background: #667eea;
            color: white;
            transition: all 0.2s;
        }
        .btn:hover {
            background: #5568d3;
        }
        .btn-back {
            background: #e0e0e0;
            color: #333;
            text-decoration: none;
            display: inline-block;
            margin-bottom: 15px;
        }
        .btn-back:hover {
            background: #d0d0d0;
        }
        .section {
            background: white;
            padding: 25px;
            border-radius: 8px;
            margin-bottom: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .section-title {
            font-size: 20px;
            color: #333;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid #667eea;
        }
        table {
            width: 100%;
            border-collapse: collapse;
        }
        th {
            background: #f5f5f5;
            padding: 12px;
            text-align: left;
            font-weight: 600;
            font-size: 13px;
            color: #666;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        td {
            padding: 12px;
            border-bottom: 1px solid #eee;
        }
        tr:hover {
            background: #fafafa;
        }
        .badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 500;
        }
        .badge-danger {
            background: #ffebee;
            color: #c62828;
        }
        .badge-warning {
            background: #fff3e0;
            color: #ef6c00;
        }
        .badge-success {
            background: #e8f5e9;
            color: #2e7d32;
        }
        .badge-info {
            background: #e3f2fd;
            color: #1565c0;
        }
        .no-data {
            text-align: center;
            padding: 40px;
            color: #999;
            font-style: italic;
        }
        .stat-highlight {
            font-weight: 600;
            color: #667eea;
        }
        .client-priority-high {
            background: #ffebee !important;
        }
        .client-priority-medium {
            background: #fff3e0 !important;
        }
        .action-btn {
            padding: 6px 12px;
            font-size: 12px;
            background: #667eea;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
        }
        .action-btn:hover {
            background: #5568d3;
        }
        .rappel-info {
            font-size: 12px;
            color: #666;
            font-style: italic;
        }
        .stats-overview {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 20px;
        }
        .stat-box {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            text-align: center;
        }
        .stat-box-label {
            font-size: 13px;
            color: #666;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 10px;
        }
        .stat-box-value {
            font-size: 28px;
            font-weight: 700;
            color: #333;
        }
        .stat-box-value.green { color: #2e7d32; }
        .stat-box-value.blue { color: #1565c0; }
        .stat-box-value.orange { color: #ef6c00; }
        .stat-box-value.red { color: #c62828; }
        .stat-box-value.purple { color: #667eea; }
        [title] {
            cursor: help;
            position: relative;
        }
        .subsection {
            background: #fafafa;
            padding: 15px;
            border-radius: 6px;
            margin-top: 20px;
        }
        .subsection-title {
            font-size: 16px;
            font-weight: 600;
            color: #555;
            margin-bottom: 15px;
            padding-bottom: 8px;
            border-bottom: 1px solid #ddd;
        }
        .mini-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 15px;
        }
        .mini-card {
            background: white;
            padding: 15px;
            border-radius: 6px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }
        .mini-card-title {
            font-size: 13px;
            color: #666;
            margin-bottom: 8px;
        }
        .mini-card-value {
            font-size: 20px;
            font-weight: 600;
            color: #667eea;
        }
        .header {
            background: white;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .periode-selector {
            display: flex;
            gap: 15px;
            align-items: center;
            flex-wrap: wrap;
        }
        .periode-selector label {
            font-weight: 500;
        }
        .periode-selector select,
        .periode-selector input {
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
        }
    </style>
</head>
<body>
    <?php include 'includes/header.php'; ?>
    
    <div class="container">
        <div class="header">
            <h1>📊 Statistiques</h1>
            <div class="periode-selector">
                <label>Période d'analyse :</label>
                <select id="periode" onchange="updatePeriode()">
                    <option value="mois_en_cours" <?php echo $periode === 'mois_en_cours' ? 'selected' : ''; ?>>Mois en cours</option>
                    <option value="annee_en_cours" <?php echo $periode === 'annee_en_cours' ? 'selected' : ''; ?>>Depuis début d'année</option>
                    <option value="personnalise" <?php echo $periode === 'personnalise' ? 'selected' : ''; ?>>Période personnalisée</option>
                </select>
                
                <div id="custom-dates" style="display: <?php echo $periode === 'personnalise' ? 'flex' : 'none'; ?>; gap: 10px;">
                    <input type="date" id="date_debut" value="<?php echo $date_debut; ?>">
                    <span>au</span>
                    <input type="date" id="date_fin" value="<?php echo $date_fin; ?>">
                </div>
                
                <button class="btn" onclick="appliquerPeriode()">Appliquer</button>
                <span style="margin-left: 20px; font-weight: 600; color: #667eea;"><?php echo $label_periode; ?></span>
            </div>
        </div>

        <!-- Vue d'ensemble globale -->
        <div class="stats-overview">
            <div class="stat-box" title="Somme des forfaits vendus + facturations hors forfait sur la période sélectionnée">
                <div class="stat-box-label">💰 CA Total Période</div>
                <div class="stat-box-value green"><?php echo number_format($ca_total_periode, 2); ?> €</div>
            </div>
            <div class="stat-box" title="Clients ayant au moins 1 heure restante sur leurs forfaits">
                <div class="stat-box-label">👥 Clients Actifs</div>
                <div class="stat-box-value blue"><?php echo $nb_clients_actifs; ?></div>
            </div>
            <div class="stat-box" title="Clients avec 0 heure restante ET aucun rappel depuis 30 jours (ou jamais rappelés)">
                <div class="stat-box-label">⚠️ Clients à Risque</div>
                <div class="stat-box-value <?php echo $nb_clients_risque > 0 ? 'red' : 'green'; ?>"><?php echo $nb_clients_risque; ?></div>
            </div>
            <div class="stat-box" title="Clients n'ayant eu aucune intervention clôturée depuis 3 mois">
                <div class="stat-box-label">📉 Clients Inactifs (3 mois)</div>
                <div class="stat-box-value orange"><?php echo $nb_clients_inactifs; ?></div>
            </div>
            <div class="stat-box" title="Pourcentage des heures réellement consommées par rapport aux heures vendues (heures réalisées ÷ heures vendues × 100)">
                <div class="stat-box-label">📊 Taux Conversion</div>
                <div class="stat-box-value purple"><?php echo number_format($taux_conversion, 1); ?>%</div>
            </div>
            <div class="stat-box" title="Total des heures restantes sur tous les forfaits vendus (non encore consommées)">
                <div class="stat-box-label">⏱️ Heures Non Consommées</div>
                <div class="stat-box-value blue"><?php echo number_format($total_heures_non_consommees, 2, ',', ' '); ?>h</div>
            </div>
        </div>

        <!-- Section Statistiques Techniciens -->
        <div class="section">
            <h2 class="section-title">👨‍💼 Statistiques Techniciens</h2>
            
            <?php if (empty($stats_techniciens)): ?>
                <div class="no-data">Aucune activité sur cette période</div>
            <?php else: ?>
                <table>
                    <thead>
                        <tr>
                            <th>Technicien</th>
                            <th title="Nombre total d'interventions clôturées sur la période">Nb interventions</th>
                            <th title="Somme des heures décomptées de tous les forfaits">Heures réalisées</th>
                            <th title="Temps moyen passé par intervention (heures réalisées ÷ nb interventions)">Durée moyenne</th>
                            <th title="Nombre de clients différents ayant eu au moins une intervention avec ce technicien">Clients servis</th>
                            <th title="Nombre de forfaits vendus aux clients de ce technicien sur la période">Forfaits vendus</th>
                            <th title="Type de forfait le plus fréquemment vendu par ce technicien">Forfait le + vendu</th>
                            <th title="Somme des heures totales de tous les forfaits vendus">Heures vendues</th>
                            <th title="Chiffre d'affaires généré (forfaits + facturations hors forfait) pour les clients de ce technicien">Montant facturé</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($stats_techniciens as $stat): ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($stat['nom']); ?></strong></td>
                                <td><?php echo $stat['nb_interventions']; ?></td>
                                <td class="stat-highlight"><?php echo number_format($stat['total_heures'], 2); ?>h</td>
                                <td><?php echo number_format($stat['duree_moyenne'], 2); ?>h</td>
                                <td><?php echo $stat['nb_clients_servis']; ?></td>
                                <td><?php echo $stat['nb_forfaits']; ?></td>
                                <td>
                                    <?php if ($stat['forfait_plus_vendu'] !== 'N/A'): ?>
                                        <span class="badge badge-info">
                                            <?php echo htmlspecialchars($stat['forfait_plus_vendu']); ?> 
                                            (×<?php echo $stat['nb_forfait_plus_vendu']; ?>)
                                        </span>
                                    <?php else: ?>
                                        <span style="color: #999;">N/A</span>
                                    <?php endif; ?>
                                </td>
                                <td class="stat-highlight"><?php echo number_format($stat['heures_vendues'], 2); ?>h</td>
                                <td class="stat-highlight"><?php echo number_format($stat['montant_facture'], 2); ?> €</td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <tr style="background: #f0f0f0; font-weight: bold; border-top: 2px solid #667eea;">
                            <td><strong>TOTAL</strong></td>
                            <td><?php echo $total_interventions; ?></td>
                            <td class="stat-highlight"><?php echo number_format($total_heures_realisees, 2); ?>h</td>
                            <td><?php echo number_format($duree_moyenne_globale, 2); ?>h</td>
                            <td style="color: #999;">-</td>
                            <td><?php echo $total_forfaits_vendus; ?></td>
                            <td style="color: #999;">-</td>
                            <td class="stat-highlight"><?php echo number_format($total_heures_vendues, 2); ?>h</td>
                            <td class="stat-highlight"><?php echo number_format($total_montant_facture, 2); ?> €</td>
                        </tr>
                    </tfoot>
                </table>
                
                <!-- Sous-sections statistiques -->
                <div class="subsection">
                    <div class="subsection-title" title="Classement des types de forfaits par nombre de ventes sur la période sélectionnée">📦 Forfaits les plus vendus (période)</div>
                    <?php if (empty($forfaits_plus_vendus)): ?>
                        <p style="color: #999; font-style: italic;">Aucun forfait vendu sur cette période</p>
                    <?php else: ?>
                        <div class="mini-grid">
                            <?php foreach ($forfaits_plus_vendus as $fpv): ?>
                                <div class="mini-card">
                                    <div class="mini-card-title"><?php echo htmlspecialchars($fpv['type_forfait']); ?></div>
                                    <div class="mini-card-value">
                                        <?php echo $fpv['nb_ventes']; ?> vente(s)
                                        <span style="font-size: 14px; color: #999;">
                                            (<?php echo number_format($fpv['total_heures'], 1); ?>h total)
                                        </span>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- Section Statistiques Clients -->
        <div class="section">
            <h2 class="section-title">👥 Statistiques Clients - Suivi des forfaits</h2>
            
            <!-- Sous-sections analytics -->
            <div class="subsection">
                <div class="subsection-title" title="Nombre de clients par canal d'acquisition (bouche à oreille, publicité, site web, etc.)">📊 Répartition par source d'acquisition</div>
                <?php if (empty($repartition_sources)): ?>
                    <p style="color: #999; font-style: italic;">Aucune donnée disponible</p>
                <?php else: ?>
                    <?php 
                    $total_sources = array_sum(array_column($repartition_sources, 'nb_clients'));
                    $labels_sources = [
                        'bouche_a_oreille' => 'Bouche à oreille',
                        'publicite' => 'Publicité',
                        'site_web' => 'Site web',
                        'reseau_social' => 'Réseau social',
                        'partenaire' => 'Partenaire',
                        'autre' => 'Autre'
                    ];
                    ?>
                    <div style="margin-bottom:15px; padding:10px; background:#f5f5f5; border-radius:4px;">
                        <strong>Total :</strong> <?php echo $total_sources; ?> client(s)
                    </div>
                    <div style="display:flex; flex-direction:column; gap:12px;">
                        <?php foreach ($repartition_sources as $rs): 
                            $pourcentage = round(($rs['nb_clients'] / $total_sources) * 100, 1);
                        ?>
                            <div style="padding:10px; background:#fff; border:1px solid #e0e0e0; border-radius:4px;">
                                <div style="display:flex; justify-content:space-between; margin-bottom:6px;">
                                    <strong><?php echo $labels_sources[$rs['source_acquisition']] ?? $rs['source_acquisition']; ?></strong>
                                    <span><?php echo $rs['nb_clients']; ?> client(s) (<?php echo $pourcentage; ?>%)</span>
                                </div>
                                <div style="background:#e0e0e0; height:8px; border-radius:4px; overflow:hidden;">
                                    <div style="background:#667eea; height:100%; width:<?php echo $pourcentage; ?>%; transition:width 0.3s;"></div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <div class="subsection">
                <div class="subsection-title" title="Nombre de clients par mode de paiement préférentiel (espèces, chèque, virement, CB, avance immédiate)">💳 Répartition par mode de paiement</div>
                <?php if (empty($repartition_paiements)): ?>
                    <p style="color: #999; font-style: italic;">Aucune donnée disponible</p>
                <?php else: ?>
                    <?php 
                    $total_paiements = array_sum(array_column($repartition_paiements, 'nb_clients'));
                    $labels_paiements = [
                        'especes' => 'Espèces',
                        'cheque' => 'Chèque',
                        'virement' => 'Virement',
                        'carte_bancaire' => 'Carte bancaire',
                        'avance_immediate' => '💰 Avance immédiate'
                    ];
                    ?>
                    <div style="margin-bottom:15px; padding:10px; background:#f5f5f5; border-radius:4px;">
                        <strong>Total :</strong> <?php echo $total_paiements; ?> client(s)
                    </div>
                    <div style="display:flex; flex-direction:column; gap:12px;">
                        <?php foreach ($repartition_paiements as $rp): 
                            $pourcentage = round(($rp['nb_clients'] / $total_paiements) * 100, 1);
                        ?>
                            <div style="padding:10px; background:#fff; border:1px solid #e0e0e0; border-radius:4px;">
                                <div style="display:flex; justify-content:space-between; margin-bottom:6px;">
                                    <strong><?php echo $labels_paiements[$rp['mode_paiement']] ?? $rp['mode_paiement']; ?></strong>
                                    <span><?php echo $rp['nb_clients']; ?> client(s) (<?php echo $pourcentage; ?>%)</span>
                                </div>
                                <div style="background:#e0e0e0; height:8px; border-radius:4px; overflow:hidden;">
                                    <div style="background:#4caf50; height:100%; width:<?php echo $pourcentage; ?>%; transition:width 0.3s;"></div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <div class="subsection">
                <div class="subsection-title" title="Classement des 10 clients ayant généré le plus de chiffre d'affaires (forfaits + facturations hors forfait)">🏆 Top 10 clients les plus rentables</div>
                <?php if (empty($top_clients)): ?>
                    <p style="color: #999; font-style: italic;">Aucun client enregistré</p>
                <?php else: ?>
                    <table>
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Client</th>
                                <th>CA Total</th>
                                <th>Nb forfaits achetés</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php $rank = 1; foreach ($top_clients as $tc): ?>
                                <tr>
                                    <td><strong><?php echo $rank++; ?></strong></td>
                                    <td><?php echo htmlspecialchars($tc['prenom'] . ' ' . $tc['nom']); ?></td>
                                    <td class="stat-highlight"><?php echo number_format($tc['ca_total'], 2); ?> €</td>
                                    <td><?php echo $tc['nb_forfaits_achetes']; ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
            
            <!-- Section Clients à Risque -->
            <div class="section-box" style="border-left: 4px solid #f44336;">
                <h3 style="margin-top: 0; margin-bottom: 15px; color: #f44336;">⚠️ Clients à risque (<?php echo $nb_clients_risque; ?>)</h3>
                <p style="color: #666; margin-bottom: 20px; font-size: 0.95em;">
                    Clients nécessitant une attention particulière selon les critères suivants :
                </p>
                <ul style="color: #666; margin-bottom: 20px; line-height: 1.6;">
                    <li><strong>0h restantes</strong> et pas de rappel depuis 30 jours</li>
                    <li><strong>&lt; 2h restantes</strong> et pas d'intervention depuis 60 jours</li>
                    <li><strong>≥ 2h restantes</strong> mais aucune intervention depuis 90 jours</li>
                </ul>
                
                <?php
                // Récupérer la liste détaillée des clients à risque
                $stmt = $pdo->query("
                    SELECT 
                        c.id,
                        c.nom,
                        c.prenom,
                        c.email,
                        c.telephone_mobile,
                        c.telephone_fixe,
                        c.date_dernier_rappel,
                        c.commentaire_rappel,
                        COALESCE(fv.total_heures, 0) as heures_restantes,
                        hc.derniere_intervention,
                        DATEDIFF(NOW(), COALESCE(hc.derniere_intervention, c.created_at)) as jours_sans_intervention,
                        DATEDIFF(NOW(), COALESCE(c.date_dernier_rappel, c.created_at)) as jours_sans_rappel,
                        CASE
                            WHEN COALESCE(fv.total_heures, 0) = 0 THEN '0h - Rappel nécessaire'
                            WHEN COALESCE(fv.total_heures, 0) < 2 THEN 'Forfait bientôt épuisé'
                            ELSE 'Forfait non utilisé'
                        END as raison_risque
                    FROM clients c
                    LEFT JOIN (
                        SELECT client_id, SUM(heures_restantes) as total_heures
                        FROM forfaits_vendus
                        GROUP BY client_id
                    ) fv ON c.id = fv.client_id
                    LEFT JOIN (
                        SELECT client_id, MAX(date_rdv) as derniere_intervention
                        FROM historique_consommation
                        GROUP BY client_id
                    ) hc ON c.id = hc.client_id
                    WHERE (
                        (COALESCE(fv.total_heures, 0) = 0 
                         AND (c.date_dernier_rappel IS NULL OR c.date_dernier_rappel < DATE_SUB(NOW(), INTERVAL 30 DAY)))
                        OR
                        (COALESCE(fv.total_heures, 0) > 0 
                         AND COALESCE(fv.total_heures, 0) < 2
                         AND (hc.derniere_intervention IS NULL OR hc.derniere_intervention < DATE_SUB(NOW(), INTERVAL 60 DAY)))
                        OR
                        (COALESCE(fv.total_heures, 0) >= 2
                         AND (hc.derniere_intervention IS NULL OR hc.derniere_intervention < DATE_SUB(NOW(), INTERVAL 90 DAY)))
                    )
                    ORDER BY 
                        COALESCE(fv.total_heures, 0) ASC,
                        jours_sans_intervention DESC
                ");
                $clients_risque = $stmt->fetchAll(PDO::FETCH_ASSOC);
                ?>
                
                <?php if (empty($clients_risque)): ?>
                    <div style="padding: 20px; text-align: center; background: #e8f5e9; border-radius: 4px; color: #4caf50;">
                        ✓ Aucun client à risque identifié
                    </div>
                <?php else: ?>
                    <table>
                        <thead>
                            <tr>
                                <th>Client</th>
                                <th>Contact</th>
                                <th>Heures restantes</th>
                                <th>Raison du risque</th>
                                <th>Dernière intervention</th>
                                <th>Dernier rappel</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($clients_risque as $cr): ?>
                                <?php
                                $urgence_class = '';
                                if ($cr['heures_restantes'] == 0 && $cr['jours_sans_rappel'] > 60) {
                                    $urgence_class = 'client-priority-high';
                                } elseif ($cr['heures_restantes'] == 0 || $cr['jours_sans_intervention'] > 90) {
                                    $urgence_class = 'client-priority-medium';
                                }
                                ?>
                                <tr class="<?php echo $urgence_class; ?>">
                                    <td>
                                        <strong><?php echo htmlspecialchars($cr['prenom'] . ' ' . $cr['nom']); ?></strong>
                                    </td>
                                    <td style="font-size: 0.9em;">
                                        <?php if ($cr['email']): ?>
                                            📧 <?php echo htmlspecialchars($cr['email']); ?><br>
                                        <?php endif; ?>
                                        <?php if ($cr['telephone_mobile']): ?>
                                            📱 <?php echo htmlspecialchars($cr['telephone_mobile']); ?>
                                        <?php elseif ($cr['telephone_fixe']): ?>
                                            📞 <?php echo htmlspecialchars($cr['telephone_fixe']); ?>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($cr['heures_restantes'] == 0): ?>
                                            <span class="badge badge-danger">0h</span>
                                        <?php elseif ($cr['heures_restantes'] < 2): ?>
                                            <span class="badge badge-warning"><?php echo number_format($cr['heures_restantes'], 2); ?>h</span>
                                        <?php else: ?>
                                            <span class="badge badge-info"><?php echo number_format($cr['heures_restantes'], 2); ?>h</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <strong style="color: #f44336;"><?php echo htmlspecialchars($cr['raison_risque']); ?></strong>
                                    </td>
                                    <td>
                                        <?php if ($cr['derniere_intervention']): ?>
                                            <?php echo date('d/m/Y', strtotime($cr['derniere_intervention'])); ?>
                                            <div style="font-size: 0.85em; color: #999;">
                                                (il y a <?php echo $cr['jours_sans_intervention']; ?> jours)
                                            </div>
                                        <?php else: ?>
                                            <span style="color: #f44336;">Jamais</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($cr['date_dernier_rappel']): ?>
                                            <div><?php echo date('d/m/Y', strtotime($cr['date_dernier_rappel'])); ?></div>
                                            <div style="font-size: 0.85em; color: #999;">
                                                (il y a <?php echo $cr['jours_sans_rappel']; ?> jours)
                                            </div>
                                            <?php if ($cr['commentaire_rappel']): ?>
                                                <div class="rappel-info"><?php echo htmlspecialchars($cr['commentaire_rappel']); ?></div>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <span style="color: #f44336; font-weight: bold;">Jamais rappelé</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <button class="action-btn" style="background: #f44336;" onclick="enregistrerRappel(<?php echo $cr['id']; ?>, '<?php echo htmlspecialchars($cr['prenom'] . ' ' . $cr['nom']); ?>')">
                                            📞 Rappeler
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
            
            <h3 style="margin-top: 30px; margin-bottom: 15px; color: #555;">📋 Liste complète des clients</h3>
            
            <?php if (empty($stats_clients)): ?>
                <div class="no-data">Aucun client enregistré</div>
            <?php else: ?>
                <table>
                    <thead>
                        <tr>
                            <th>Client</th>
                            <th>Contact</th>
                            <th title="Somme de toutes les heures restantes sur l'ensemble des forfaits actifs du client">Heures restantes</th>
                            <th title="Type du forfait acheté le plus récemment">Dernier forfait vendu</th>
                            <th title="Date d'achat du forfait le plus récent">Date dernier forfait</th>
                            <th title="Date de la dernière intervention clôturée pour ce client">Dernière intervention</th>
                            <th title="Date et commentaire du dernier rappel manuel enregistré">Dernier rappel</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($stats_clients as $client): ?>
                            <?php
                            $heures = floatval($client['heures_restantes_total']);
                            $row_class = '';
                            if ($heures == 0) {
                                $row_class = 'client-priority-high';
                            } elseif ($heures < 2) {
                                $row_class = 'client-priority-medium';
                            }
                            ?>
                            <tr class="<?php echo $row_class; ?>">
                                <td>
                                    <strong><?php echo htmlspecialchars($client['prenom'] . ' ' . $client['nom']); ?></strong>
                                </td>
                                <td>
                                    <?php if ($client['email']): ?>
                                        📧 <?php echo htmlspecialchars($client['email']); ?><br>
                                    <?php endif; ?>
                                    <?php if ($client['telephone_mobile']): ?>
                                        📱 <?php echo htmlspecialchars($client['telephone_mobile']); ?>
                                    <?php elseif ($client['telephone_fixe']): ?>
                                        📞 <?php echo htmlspecialchars($client['telephone_fixe']); ?>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($heures == 0): ?>
                                        <span class="badge badge-danger">0h - À recontacter</span>
                                    <?php elseif ($heures < 2): ?>
                                        <span class="badge badge-warning"><?php echo number_format($heures, 2); ?>h - Bientôt épuisé</span>
                                    <?php else: ?>
                                        <span class="badge badge-success"><?php echo number_format($heures, 2); ?>h</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($client['dernier_forfait_vendu']): ?>
                                        <span class="badge badge-info"><?php echo htmlspecialchars($client['dernier_forfait_vendu']); ?></span>
                                    <?php else: ?>
                                        <span style="color: #999;">Aucun</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($client['date_dernier_forfait']): ?>
                                        <?php echo date('d/m/Y', strtotime($client['date_dernier_forfait'])); ?>
                                    <?php else: ?>
                                        -
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($client['date_derniere_intervention']): ?>
                                        <?php echo date('d/m/Y', strtotime($client['date_derniere_intervention'])); ?>
                                    <?php else: ?>
                                        <span style="color: #999;">Jamais</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($client['date_dernier_rappel']): ?>
                                        <div><?php echo date('d/m/Y', strtotime($client['date_dernier_rappel'])); ?></div>
                                        <?php if ($client['commentaire_rappel']): ?>
                                            <div class="rappel-info"><?php echo htmlspecialchars($client['commentaire_rappel']); ?></div>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span style="color: #999;">Jamais rappelé</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <button class="action-btn" onclick="enregistrerRappel(<?php echo $client['id']; ?>, '<?php echo htmlspecialchars($client['prenom'] . ' ' . $client['nom']); ?>')">
                                        📞 Marquer rappel
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>

    <!-- Modale pour enregistrer un rappel -->
    <div id="modalRappel" style="display:none; position:fixed; z-index:10000; left:0; top:0; width:100%; height:100%; background:rgba(0,0,0,0.5);">
        <div style="background:white; margin:10% auto; padding:25px; width:450px; border-radius:8px; box-shadow:0 4px 6px rgba(0,0,0,0.1);">
            <h3 style="margin-top:0; margin-bottom:15px;">📞 Marquer rappel</h3>
            <p id="modalClientNom" style="color:#666; margin-bottom:20px; font-weight:600;"></p>
            
            <div style="margin-bottom:20px;">
                <label style="display:block; margin-bottom:8px; font-weight:600;">Commentaire (optionnel)</label>
                <textarea id="commentaireRappel" rows="4" style="width:100%; padding:10px; border:1px solid #ddd; border-radius:4px; font-size:14px; font-family:inherit; resize:vertical;" placeholder="Notes sur le rappel..."></textarea>
            </div>
            
            <div style="display:flex; gap:10px; justify-content:space-between; align-items:center; margin-top:25px;">
                <button onclick="ouvrirAgenda()" style="padding:10px 16px; background:#2196F3; color:white; border:none; border-radius:4px; cursor:pointer; font-weight:600; display:flex; align-items:center; gap:6px;">
                    📅 Prendre rendez-vous
                </button>
                <div style="display:flex; gap:10px;">
                    <button onclick="annulerRappel()" style="padding:10px 16px; background:#999; color:white; border:none; border-radius:4px; cursor:pointer;">Annuler</button>
                    <button onclick="confirmerRappel()" style="padding:10px 16px; background:#4caf50; color:white; border:none; border-radius:4px; cursor:pointer; font-weight:600;">Confirmer</button>
                </div>
            </div>
        </div>
    </div>

    <script>
        let currentClientIdRappel = null;
        
        function updatePeriode() {
            const periode = document.getElementById('periode').value;
            const customDates = document.getElementById('custom-dates');
            customDates.style.display = periode === 'personnalise' ? 'flex' : 'none';
        }

        function appliquerPeriode() {
            const periode = document.getElementById('periode').value;
            let url = 'statistiques.php?periode=' + periode;
            
            if (periode === 'personnalise') {
                const dateDebut = document.getElementById('date_debut').value;
                const dateFin = document.getElementById('date_fin').value;
                url += '&date_debut=' + dateDebut + '&date_fin=' + dateFin;
            }
            
            window.location.href = url;
        }

        function enregistrerRappel(clientId, clientNom) {
            // Ouvrir la modale
            currentClientIdRappel = clientId;
            document.getElementById('modalClientNom').textContent = clientNom;
            document.getElementById('commentaireRappel').value = '';
            document.getElementById('modalRappel').style.display = 'block';
        }
        
        function annulerRappel() {
            document.getElementById('modalRappel').style.display = 'none';
            currentClientIdRappel = null;
        }
        
        function ouvrirAgenda() {
            // Ouvrir l'agenda dans un nouvel onglet
            window.open('agenda.php', '_blank');
        }
        
        function confirmerRappel() {
            const commentaire = document.getElementById('commentaireRappel').value.trim();
            
            fetch('api/clients.php?action=update_rappel', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({
                    client_id: currentClientIdRappel,
                    commentaire: commentaire
                })
            })
            .then(resp => resp.json())
            .then(json => {
                if (json.success) {
                    alert('Rappel enregistré !');
                    location.reload();
                } else {
                    alert('Erreur : ' + (json.error || 'Erreur inconnue'));
                }
            })
            .catch(err => {
                console.error('Erreur:', err);
                alert('Erreur réseau');
            })
            .finally(() => {
                annulerRappel();
            });
        }
    </script>
</body>
</html>
