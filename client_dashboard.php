<?php
// Tableau de bord client
require_once 'config.php';

$pdo = getDBConnection();

// Récupérer l'ID du client
$client_id = intval($_GET['client_id'] ?? 0);

if ($client_id <= 0) {
    header('Location: clients.php');
    exit;
}

// Récupérer les infos du client
$stmt = $pdo->prepare("SELECT * FROM clients WHERE id = ?");
$stmt->execute([$client_id]);
$client = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$client) {
    header('Location: clients.php');
    exit;
}

// Récupérer les forfaits (en cours et terminés)
$stmt = $pdo->prepare("
    SELECT 
        fv.*,
        tf.type_forfait,
        tf.nbr_heure_forfait,
        tf.prix_forfait,
        CASE 
            WHEN fv.heures_restantes > 0 THEN 'en_cours'
            ELSE 'termine'
        END as statut_forfait
    FROM forfaits_vendus fv
    LEFT JOIN type_forfait tf ON fv.type_forfait_id = tf.id
    WHERE fv.client_id = ?
    ORDER BY 
        CASE WHEN fv.heures_restantes > 0 THEN 0 ELSE 1 END,
        COALESCE(fv.date_vente, fv.created_at) DESC,
        fv.id DESC
");
$stmt->execute([$client_id]);
$forfaits = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Calculer les totaux
$total_heures_achetees = 0;
$total_heures_restantes = 0;
$total_heures_utilisees = 0;

foreach ($forfaits as $f) {
    $total_heures_achetees += floatval($f['heures_total']);
    $total_heures_restantes += floatval($f['heures_restantes']);
}
$total_heures_utilisees = $total_heures_achetees - $total_heures_restantes;

// Calculer les statistiques client
// 1. Client depuis combien de temps (basé sur le premier forfait ou la première intervention)
$stmt = $pdo->prepare("
    SELECT MIN(date_acquisition) as premiere_date 
    FROM (
        SELECT MIN(COALESCE(date_vente, created_at)) as date_acquisition FROM forfaits_vendus WHERE client_id = ?
        UNION ALL
        SELECT MIN(date_rdv) as date_acquisition FROM rendez_vous WHERE client_id = ?
    ) as dates
");
$stmt->execute([$client_id, $client_id]);
$premiere_date = $stmt->fetchColumn();

if ($premiere_date) {
    $jours_client = floor((time() - strtotime($premiere_date)) / 86400);
    $annees_client = floor($jours_client / 365);
    $mois_client = floor(($jours_client % 365) / 30);
} else {
    // Si aucune donnée, considérer comme nouveau client
    $jours_client = 0;
    $annees_client = 0;
    $mois_client = 0;
}

// 2. Chiffre d'affaires total
$stmt = $pdo->prepare("
    SELECT 
        COALESCE(SUM(tarif), 0) as ca_forfaits
    FROM forfaits_vendus 
    WHERE client_id = ?
");
$stmt->execute([$client_id]);
$ca_forfaits = floatval($stmt->fetchColumn());

$stmt = $pdo->prepare("
    SELECT 
        COALESCE(SUM(montant_total), 0) as ca_hors_forfait
    FROM facturation_hors_forfait 
    WHERE client_id = ?
");
$stmt->execute([$client_id]);
$ca_hors_forfait = floatval($stmt->fetchColumn());

$ca_total = $ca_forfaits + $ca_hors_forfait;

// 3. Chiffre d'affaires année en cours
$annee_en_cours = date('Y');
$stmt = $pdo->prepare("
    SELECT 
        COALESCE(SUM(tarif), 0) as ca_forfaits_annee
    FROM forfaits_vendus 
    WHERE client_id = ? AND YEAR(created_at) = ?
");
$stmt->execute([$client_id, $annee_en_cours]);
$ca_forfaits_annee = floatval($stmt->fetchColumn());

$stmt = $pdo->prepare("
    SELECT 
        COALESCE(SUM(montant_total), 0) as ca_hors_forfait_annee
    FROM facturation_hors_forfait 
    WHERE client_id = ? AND YEAR(created_at) = ?
");
$stmt->execute([$client_id, $annee_en_cours]);
$ca_hors_forfait_annee = floatval($stmt->fetchColumn());

$ca_annee = $ca_forfaits_annee + $ca_hors_forfait_annee;

// Récupérer l'historique des interventions
$stmt = $pdo->prepare("
    SELECT 
        rv.id AS rendez_vous_id,
        rv.date_rdv,
        rv.heure_debut,
        rv.heure_fin,
        rv.titre,
        rv.description,
        rv.lieu,
        rv.statut AS rdv_statut,
        hc.id AS historique_id,
        hc.temps_reel,
        hc.temps_arrondi,
        hc.heures_decomptes,
        hc.difference_arrondi,
        t.nom as technicien_nom,
        t.prenom as technicien_prenom,
        fv.type_forfait_id,
        tf.type_forfait,
        CASE WHEN hc.id IS NULL THEN 0 ELSE 1 END AS est_cloturee
    FROM rendez_vous rv
    LEFT JOIN historique_consommation hc ON hc.rendez_vous_id = rv.id AND hc.client_id = rv.client_id
    LEFT JOIN forfaits_vendus fv ON hc.forfait_vendu_id = fv.id
    LEFT JOIN type_forfait tf ON fv.type_forfait_id = tf.id
    LEFT JOIN techniciens t ON rv.id_technicien = t.id
    WHERE rv.client_id = ?
    ORDER BY rv.date_rdv DESC, rv.heure_debut DESC
");
$stmt->execute([$client_id]);
$interventions = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tableau de bord - <?php echo htmlspecialchars($client['prenom'] . ' ' . $client['nom']); ?></title>
    <link rel="stylesheet" href="includes/common_styles.css">
    <style>
        body { padding: 0; }
        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            border-radius: 8px 8px 0 0;
        }
        .header h1 {
            font-size: 28px;
            margin-bottom: 10px;
        }
        .header-info {
            display: flex;
            gap: 30px;
            margin-top: 15px;
            font-size: 14px;
            opacity: 0.9;
        }
        .header-info-item {
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            padding: 30px;
            background: #fafafa;
        }
        .stat-card {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }
        .stat-label {
            font-size: 13px;
            color: #666;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 8px;
        }
        .stat-value {
            font-size: 32px;
            font-weight: bold;
            color: #333;
        }
        .stat-value.green { color: #4caf50; }
        .stat-value.blue { color: #2196f3; }
        .stat-value.orange { color: #ff9800; }
        .stat-value.purple { color: #9c27b0; }
        .content {
            padding: 30px;
        }
        .section {
            margin-bottom: 40px;
        }
        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        .section-title {
            font-size: 20px;
            font-weight: 600;
            color: #333;
        }
        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 500;
            transition: all 0.2s;
            text-decoration: none;
            display: inline-block;
        }
        .btn-primary {
            background: #667eea;
            color: white;
        }
        .btn-primary:hover {
            background: #5568d3;
        }
        .btn-secondary {
            background: #e0e0e0;
            color: #333;
        }
        .btn-secondary:hover {
            background: #d0d0d0;
        }
        .btn-success {
            background: #4caf50;
            color: white;
        }
        .btn-success:hover {
            background: #45a049;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            background: white;
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
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 500;
        }
        .badge-success {
            background: #e8f5e9;
            color: #2e7d32;
        }
        .badge-info {
            background: #e3f2fd;
            color: #1976d2;
        }
        .badge-warning {
            background: #fff3e0;
            color: #f57c00;
        }
        .badge-danger {
            background: #ffebee;
            color: #c62828;
        }
        .no-data {
            text-align: center;
            padding: 40px;
            color: #999;
            font-style: italic;
        }
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 1000;
            align-items: center;
            justify-content: center;
        }
        .modal.active {
            display: flex;
        }
        .modal-content {
            background: white;
            padding: 30px;
            border-radius: 8px;
            max-width: 600px;
            width: 90%;
            max-height: 90vh;
            overflow-y: auto;
        }
        .modal-header {
            margin-bottom: 20px;
        }
        .modal-title {
            font-size: 22px;
            font-weight: 600;
            color: #333;
        }
        .form-group {
            margin-bottom: 20px;
        }
        .form-label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: #333;
            font-size: 14px;
        }
        .form-control {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 14px;
        }
        .form-control:focus {
            outline: none;
            border-color: #667eea;
        }
        textarea.form-control {
            resize: vertical;
            min-height: 100px;
        }
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
        }
        .modal-footer {
            display: flex;
            gap: 10px;
            justify-content: flex-end;
            margin-top: 25px;
            padding-top: 20px;
            border-top: 1px solid #eee;
        }
        .back-link {
            color: white;
            opacity: 0.9;
            text-decoration: none;
        }
        .back-link:hover {
            opacity: 1;
        }
    </style>
</head>
<body>
    <?php include 'includes/header.php'; ?>
    <div class="container">
        <div class="header">
            <a href="clients.php" class="back-link">← Retour à la liste des clients</a>
            <h1><?php echo htmlspecialchars($client['prenom'] . ' ' . $client['nom']); ?></h1>
            <div class="header-info">
                <?php if ($client['email']): ?>
                    <div class="header-info-item">
                        <span>📧</span>
                        <span><?php echo htmlspecialchars($client['email']); ?></span>
                    </div>
                <?php endif; ?>
                <?php if ($client['telephone_mobile']): ?>
                    <div class="header-info-item">
                        <span>📱</span>
                        <span><?php echo htmlspecialchars($client['telephone_mobile']); ?></span>
                    </div>
                <?php endif; ?>
                <?php if ($client['ville']): ?>
                    <div class="header-info-item">
                        <span>📍</span>
                        <span><?php echo htmlspecialchars($client['ville']); ?></span>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-label">Heures achetées</div>
                <div class="stat-value purple"><?php echo number_format($total_heures_achetees, 2); ?>h</div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Heures utilisées</div>
                <div class="stat-value blue"><?php echo number_format($total_heures_utilisees, 2); ?>h</div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Heures restantes</div>
                <div class="stat-value <?php echo $total_heures_restantes > 2 ? 'green' : 'orange'; ?>">
                    <?php echo number_format($total_heures_restantes, 2); ?>h
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Bonus accumulé</div>
                <div class="stat-value <?php echo $client['heure_bonus'] >= 0 ? 'green' : 'orange'; ?>">
                    <?php echo $client['heure_bonus'] >= 0 ? '+' : ''; ?><?php echo round($client['heure_bonus'] * 60); ?> min
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-label">📅 Client depuis</div>
                <div class="stat-value blue">
                    <?php 
                    if ($annees_client > 0) {
                        echo $annees_client . ' an' . ($annees_client > 1 ? 's' : '');
                        if ($mois_client > 0) echo ' et ' . $mois_client . ' mois';
                    } elseif ($mois_client > 0) {
                        echo $mois_client . ' mois';
                    } else {
                        echo $jours_client . ' jour' . ($jours_client > 1 ? 's' : '');
                    }
                    ?>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-label">💰 CA Total</div>
                <div class="stat-value green"><?php echo number_format($ca_total, 2); ?> €</div>
            </div>
            <div class="stat-card">
                <div class="stat-label">📊 CA <?php echo $annee_en_cours; ?></div>
                <div class="stat-value purple"><?php echo number_format($ca_annee, 2); ?> €</div>
            </div>
        </div>

        <div class="content">
            <!-- Section Interventions -->
            <div class="section">
                <div class="section-header">
                    <h2 class="section-title">Historique des interventions (<?php echo count($interventions); ?>)</h2>
                    <button class="btn btn-primary" onclick="openAddInterventionModal()">+ Ajouter une intervention</button>
                </div>

                <?php if (empty($interventions)): ?>
                    <div class="no-data">Aucune intervention enregistrée</div>
                <?php else: ?>
                    <table>
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Intervention</th>
                                <th>État</th>
                                <th>Technicien</th>
                                <th>Temps réel</th>
                                <th>Temps facturé</th>
                                <th>Heures décomptées</th>
                                <th>Bonus/Malus</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($interventions as $inter): ?>
                                <tr>
                                    <td>
                                        <strong><?php echo date('d/m/Y', strtotime($inter['date_rdv'])); ?></strong><br>
                                        <small style="color: #666;"><?php echo substr($inter['heure_debut'], 0, 5); ?> - <?php echo substr($inter['heure_fin'], 0, 5); ?></small>
                                    </td>
                                    <td>
                                        <strong><?php echo htmlspecialchars($inter['titre']); ?></strong>
                                        <?php if ($inter['description']): ?>
                                            <br><small style="color: #666;"><?php echo htmlspecialchars($inter['description']); ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if (($inter['rdv_statut'] ?? '') === 'annule'): ?>
                                            <span class="badge badge-danger">Annulée</span>
                                        <?php elseif (!empty($inter['est_cloturee'])): ?>
                                            <span class="badge badge-success">Clôturée</span>
                                        <?php else: ?>
                                            <span class="badge badge-warning">Non clôturée</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo htmlspecialchars(($inter['technicien_prenom'] ?? '') . ' ' . ($inter['technicien_nom'] ?? 'Non assigné')); ?></td>
                                    <td><?php echo isset($inter['temps_reel']) ? number_format((float)$inter['temps_reel'], 2) . 'h' : '—'; ?></td>
                                    <td><?php echo isset($inter['temps_arrondi']) ? number_format((float)$inter['temps_arrondi'], 2) . 'h' : '—'; ?></td>
                                    <td><?php echo isset($inter['heures_decomptes']) ? number_format((float)$inter['heures_decomptes'], 2) . 'h' : '—'; ?></td>
                                    <td>
                                        <?php if (isset($inter['difference_arrondi'])): ?>
                                        <?php 
                                        $bonus_minutes = round(((float)$inter['difference_arrondi']) * 60);
                                        if ($bonus_minutes > 0): ?>
                                            <span class="badge badge-success">+<?php echo $bonus_minutes; ?> min</span>
                                        <?php elseif ($bonus_minutes < 0): ?>
                                            <span class="badge badge-warning"><?php echo $bonus_minutes; ?> min</span>
                                        <?php else: ?>
                                            <span class="badge badge-info">0 min</span>
                                        <?php endif; ?>
                                        <?php else: ?>
                                            —
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>

            <!-- Section Forfaits -->
            <div class="section">
                <div class="section-header">
                    <h2 class="section-title">Forfaits (<?php echo count($forfaits); ?>)</h2>
                </div>

                <?php if (empty($forfaits)): ?>
                    <div class="no-data">Aucun forfait acheté</div>
                <?php else: ?>
                    <table>
                        <thead>
                            <tr>
                                <th>Date d'achat</th>
                                <th>Type</th>
                                <th>Heures total</th>
                                <th>Heures utilisées</th>
                                <th>Heures restantes</th>
                                <th>Montant</th>
                                <th>Statut</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($forfaits as $forfait): ?>
                                <?php 
                                $heures_utilisees_forfait = $forfait['heures_total'] - $forfait['heures_restantes'];
                                ?>
                                <tr>
                                    <td><?php echo date('d/m/Y', strtotime($forfait['date_vente'] ?: $forfait['created_at'])); ?></td>
                                    <td><strong><?php echo htmlspecialchars($forfait['type_forfait'] ?: 'N/A'); ?></strong></td>
                                    <td><?php echo number_format($forfait['heures_total'], 2); ?>h</td>
                                    <td><?php echo number_format($heures_utilisees_forfait, 2); ?>h</td>
                                    <td><?php echo number_format($forfait['heures_restantes'], 2); ?>h</td>
                                    <td><?php echo number_format($forfait['tarif'], 2); ?> €</td>
                                    <td>
                                        <?php if ($forfait['heures_restantes'] > 0): ?>
                                            <span class="badge badge-success">En cours</span>
                                        <?php else: ?>
                                            <span class="badge badge-danger">Terminé</span>
                                        <?php endif; ?>
                                        <?php if (!$forfait['paye']): ?>
                                            <span class="badge badge-warning">Non payé</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Modal Ajout Intervention -->
    <div id="addInterventionModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title">Ajouter une intervention manuelle</h2>
            </div>
            <form id="addInterventionForm">
                <input type="hidden" name="client_id" value="<?php echo $client_id; ?>">
                
                <div class="form-group">
                    <label class="form-label">Titre de l'intervention *</label>
                    <input type="text" name="titre" class="form-control" required>
                </div>

                <div class="form-group">
                    <label class="form-label">Description</label>
                    <textarea name="description" class="form-control" placeholder="Description détaillée de l'intervention..."></textarea>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Date *</label>
                        <input type="date" name="date_rdv" class="form-control" value="<?php echo date('Y-m-d'); ?>" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Technicien</label>
                        <select name="id_technicien" class="form-control">
                            <option value="">Non assigné</option>
                            <?php
                            $stmt = $pdo->query("SELECT id, nom, prenom FROM techniciens WHERE actif = 1 ORDER BY nom, prenom");
                            while ($tech = $stmt->fetch(PDO::FETCH_ASSOC)) {
                                echo '<option value="' . $tech['id'] . '">' . htmlspecialchars($tech['prenom'] . ' ' . $tech['nom']) . '</option>';
                            }
                            ?>
                        </select>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Heure début *</label>
                        <input type="time" name="heure_debut" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Heure fin *</label>
                        <input type="time" name="heure_fin" class="form-control" required>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">Lieu</label>
                    <input type="text" name="lieu" class="form-control" value="<?php echo htmlspecialchars($client['adresse'] . ', ' . $client['ville']); ?>">
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeAddInterventionModal()">Annuler</button>
                    <button type="submit" class="btn btn-success">Enregistrer et clôturer</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function openAddInterventionModal() {
            document.getElementById('addInterventionModal').classList.add('active');
        }

        function closeAddInterventionModal() {
            document.getElementById('addInterventionModal').classList.remove('active');
            document.getElementById('addInterventionForm').reset();
        }

        // Fermer le modal en cliquant à l'extérieur
        document.getElementById('addInterventionModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeAddInterventionModal();
            }
        });

        // Soumettre le formulaire
        document.getElementById('addInterventionForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            const data = Object.fromEntries(formData.entries());

            // Valider les champs requis
            if (!data.titre || !data.date_rdv || !data.heure_debut || !data.heure_fin) {
                alert('Veuillez remplir tous les champs obligatoires (titre, date, heures)');
                return;
            }

            // Normaliser les heures (ajouter :00 si secondes manquantes)
            const heureDebut = data.heure_debut.length === 5 ? data.heure_debut + ':00' : data.heure_debut;
            const heureFin = data.heure_fin.length === 5 ? data.heure_fin + ':00' : data.heure_fin;

            // Formater les données pour add_event.php
            const eventData = {
                title: data.titre,
                start: data.date_rdv + 'T' + heureDebut,
                end: data.date_rdv + 'T' + heureFin,
                description: data.description || '',
                lieu: data.lieu || '',
                client_id: data.client_id,
                id_technicien: data.id_technicien || null,
                statut: 'planifie'
            };

            console.log('Données envoyées:', eventData);
            console.log('Format start:', eventData.start);
            console.log('Format end:', eventData.end);

            // Créer le rendez-vous
            fetch('api/events.php?action=create', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify(eventData)
            })
            .then(resp => resp.json())
            .then(json => {
                if (json.error) {
                    alert('Erreur : ' + json.error);
                    console.error('Réponse serveur:', json);
                    return;
                }

                const rdvId = json.id;

                // Vérifier d'abord si les heures sont suffisantes.
                // Si insuffisantes, on conserve l'intervention créée sans la clôturer.
                return fetch('api/interventions.php?action=check_heures', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({
                        rendez_vous_id: rdvId,
                        heure_debut: data.heure_debut,
                        heure_fin: data.heure_fin,
                        appliquer_arrondi: true
                    })
                })
                .then(resp => resp.json())
                .then(check => {
                    if (check.error) {
                        return { error: check.error };
                    }

                    if (check.heures_suffisantes === false || check.besoin_nouveau_forfait) {
                        return {
                            status: 'created_not_closed',
                            besoin_nouveau_forfait: true,
                            rdv_id: rdvId,
                            heures_necessaires: check.heures_necessaires,
                            heures_restantes: check.heures_restantes
                        };
                    }

                    // Heures suffisantes: clôture automatique inchangée
                    return fetch('api/interventions.php?action=close_forfait', {
                        method: 'POST',
                        headers: {'Content-Type': 'application/json'},
                        body: JSON.stringify({
                            rendez_vous_id: rdvId,
                            heure_debut: data.heure_debut,
                            heure_fin: data.heure_fin,
                            appliquer_arrondi: true
                        })
                    }).then(resp => resp.json());
                });
            })
            .then(json => {
                if (!json) return; // Si erreur à l'étape précédente
                
                if (json.error) {
                    alert('Erreur de clôture : ' + json.error);
                } else if (json.status === 'created_not_closed') {
                    const rdvId = json.rdv_id || 'inconnu';
                    alert('Intervention créée avec succès (ID: ' + rdvId + '), mais non clôturée car les heures de forfait sont insuffisantes.\n\nNote: cette page affiche principalement les interventions clôturées.\nVous allez être redirigé vers l\'agenda pour retrouver ce rendez-vous.');
                    if (json.rdv_id) {
                        window.location.href = 'agenda.php?open_event=' + encodeURIComponent(json.rdv_id);
                        return;
                    }
                } else {
                    alert('Intervention ajoutée et clôturée avec succès !');
                }
                location.reload();
            })
            .catch(err => {
                console.error('Erreur:', err);
                alert('Erreur réseau : ' + err.message);
            });
        });
    </script>
</body>
</html>
