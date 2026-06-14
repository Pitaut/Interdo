<?php
require_once 'config.php';

$pdo = getDBConnection();

// Créer les tables si nécessaire
$pdo->exec("CREATE TABLE IF NOT EXISTS rendez_vous (
    id INT AUTO_INCREMENT PRIMARY KEY,
    titre VARCHAR(255) NOT NULL,
    date_rdv DATE NOT NULL,
    heure_debut TIME NOT NULL,
    heure_fin TIME NOT NULL,
    description TEXT,
    lieu VARCHAR(255),
    statut VARCHAR(50) DEFAULT 'planifie',
    id_technicien INT,
    client_id INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$pdo->exec("CREATE TABLE IF NOT EXISTS forfaits_vendus (
    id INT AUTO_INCREMENT PRIMARY KEY,
    client_id INT NOT NULL,
    type_forfait_id INT NOT NULL,
    heures_total DECIMAL(10,2) NOT NULL,
    heures_restantes DECIMAL(10,2) NOT NULL,
    tarif DECIMAL(10,2) NOT NULL,
    date_debut DATE DEFAULT NULL,
    date_fin DATE DEFAULT NULL,
    date_vente DATE DEFAULT NULL,
    paye BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_client (client_id),
    INDEX idx_paye (paye)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// Ajouter la colonne paye si elle n'existe pas
$stmt = $pdo->query("SHOW COLUMNS FROM forfaits_vendus LIKE 'paye'");
if ($stmt->rowCount() === 0) {
    $pdo->exec("ALTER TABLE forfaits_vendus ADD COLUMN paye BOOLEAN DEFAULT FALSE");
}

$stmt = $pdo->query("SHOW COLUMNS FROM forfaits_vendus LIKE 'date_vente'");
if ($stmt->rowCount() === 0) {
    $pdo->exec("ALTER TABLE forfaits_vendus ADD COLUMN date_vente DATE DEFAULT NULL");
}

// Récupérer les interventions non clôturées
$interventions = $pdo->query("
    SELECT 
        rv.id,
        rv.titre,
        rv.date_rdv,
        rv.heure_debut,
        rv.heure_fin,
        rv.statut,
        rv.lieu,
        c.prenom AS client_prenom,
        c.nom AS client_nom,
        t.prenom AS tech_prenom,
        t.nom AS tech_nom
    FROM rendez_vous rv
    LEFT JOIN clients c ON rv.client_id = c.id
    LEFT JOIN techniciens t ON rv.id_technicien = t.id
    WHERE rv.statut NOT IN ('termine', 'annule')
    ORDER BY rv.date_rdv DESC, rv.heure_debut DESC
")->fetchAll(PDO::FETCH_ASSOC);

// Récupérer les forfaits vendus non payés
$forfaits_impaye = $pdo->query("
    SELECT 
        fv.id,
        fv.client_id,
        fv.heures_total,
        fv.heures_restantes,
        fv.tarif,
        COALESCE(fv.date_vente, DATE(fv.created_at)) AS date_vente,
        fv.created_at,
        fv.date_debut,
        c.prenom AS client_prenom,
        c.nom AS client_nom,
        tf.type_forfait
    FROM forfaits_vendus fv
    LEFT JOIN clients c ON fv.client_id = c.id
    LEFT JOIN type_forfait tf ON fv.type_forfait_id = tf.id
    WHERE fv.paye = FALSE
    ORDER BY COALESCE(fv.date_vente, fv.created_at) DESC, fv.id DESC
")->fetchAll(PDO::FETCH_ASSOC);

?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion - <?php echo APP_NAME; ?></title>
    <link rel="stylesheet" href="includes/common_styles.css">
    <style>
        body { padding: 0; }
        
        .header-gestion {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 2px solid #667eea;
        }
        
        .header-gestion h1 {
            color: #667eea;
            margin: 0;
        }
        
        .count-badge {
            background: #667eea;
            color: white;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.9em;
            font-weight: bold;
        }
        
        .status-badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 4px;
            font-size: 0.85em;
            font-weight: 600;
        }
        
        .status-planifie { background: #2196f3; color: white; }
        .status-en_cours { background: #ff9800; color: white; }
        .status-termine { background: #4caf50; color: white; }
        .status-annule { background: #f44336; color: white; }
        
        .montant {
            font-weight: bold;
            color: #667eea;
        }
        
        .total-row {
            font-weight: bold;
            background: #f5f5f5 !important;
            border-top: 2px solid #ddd;
        }
        
        .empty-state {
            text-align: center;
            padding: 40px;
            color: #999;
        }
        
        .empty-state-icon {
            font-size: 3em;
            margin-bottom: 10px;
        }
    </style>
</head>
<body>
    <?php include 'includes/header.php'; ?>
    <div class="container">
        <div class="header-gestion">
            <h1>📊 Gestion</h1>
        </div>

        <!-- Interventions non clôturées -->
        <div class="section">
            <div class="section-title">
                <span>🔧 Interventions non clôturées</span>
                <span class="count-badge"><?php echo count($interventions); ?></span>
            </div>
            
            <?php if (empty($interventions)): ?>
                <div class="empty-state">
                    <div class="empty-state-icon">✓</div>
                    <p>Toutes les interventions sont clôturées</p>
                </div>
            <?php else: ?>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Heure</th>
                            <th>Client</th>
                            <th>Titre</th>
                            <th>Lieu</th>
                            <th>Technicien</th>
                            <th>Statut</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($interventions as $interv): ?>
                            <tr>
                                <td><?php echo date('d/m/Y', strtotime($interv['date_rdv'])); ?></td>
                                <td><?php echo substr($interv['heure_debut'], 0, 5) . ' - ' . substr($interv['heure_fin'], 0, 5); ?></td>
                                <td>
                                    <?php echo htmlspecialchars($interv['client_prenom'] . ' ' . $interv['client_nom']); ?>
                                </td>
                                <td><strong><?php echo htmlspecialchars($interv['titre']); ?></strong></td>
                                <td><?php echo htmlspecialchars($interv['lieu'] ?: '-'); ?></td>
                                <td><?php echo htmlspecialchars($interv['tech_prenom'] . ' ' . $interv['tech_nom']); ?></td>
                                <td>
                                    <span class="status-badge status-<?php echo $interv['statut']; ?>">
                                        <?php echo ucfirst(str_replace('_', ' ', $interv['statut'])); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($interv['statut'] !== 'annule'): ?>
                                        <button class="btn btn-success" onclick="cloturer(<?php echo $interv['id']; ?>)">
                                            Clôturer
                                        </button>
                                    <?php else: ?>
                                        <span style="color:#999; font-size:0.9em;">Tracée uniquement dans l'historique</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>

        <!-- Forfaits vendus non payés -->
        <div class="section">
            <div class="section-title">
                <span>💰 Forfaits vendus non payés</span>
                <span class="count-badge"><?php echo count($forfaits_impaye); ?></span>
            </div>
            
            <?php if (empty($forfaits_impaye)): ?>
                <div class="empty-state">
                    <div class="empty-state-icon">✓</div>
                    <p>Tous les forfaits sont payés</p>
                </div>
            <?php else: 
                $total_impaye = array_sum(array_column($forfaits_impaye, 'tarif'));
            ?>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Date vente</th>
                            <th>Client</th>
                            <th>Type forfait</th>
                            <th>Heures total</th>
                            <th>Heures restantes</th>
                            <th>Montant</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($forfaits_impaye as $forfait): ?>
                            <tr>
                                <td><?php echo date('d/m/Y', strtotime($forfait['date_vente'] ?: $forfait['created_at'])); ?></td>
                                <td>
                                    <?php echo htmlspecialchars($forfait['client_prenom'] . ' ' . $forfait['client_nom']); ?>
                                </td>
                <td><?php echo htmlspecialchars($forfait['type_forfait'] ?: 'N/A'); ?></td>
                                <td><?php echo number_format($forfait['heures_total'], 2); ?>h</td>
                                <td><?php echo number_format($forfait['heures_restantes'], 2); ?>h</td>
                                <td class="montant"><?php echo number_format($forfait['tarif'], 2); ?> €</td>
                                <td>
                                    <?php 
                                        $clientNom = htmlspecialchars($forfait['client_prenom'] . ' ' . $forfait['client_nom'], ENT_QUOTES);
                                    ?>
                                    <button class="btn btn-primary" onclick="marquerPaye(<?php echo $forfait['id']; ?>, <?php echo $forfait['client_id']; ?>, '<?php echo $clientNom; ?>', '<?php echo htmlspecialchars($forfait['date_vente'] ?: date('Y-m-d'), ENT_QUOTES); ?>')">
                                        Marquer payé
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <tr class="total-row">
                            <td colspan="5" style="text-align:right;">TOTAL À ENCAISSER :</td>
                            <td class="montant"><?php echo number_format($total_impaye, 2); ?> €</td>
                            <td></td>
                        </tr>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>

    <!-- Modale pour le mode de règlement -->
    <div id="modalReglement" style="display:none; position:fixed; z-index:10000; left:0; top:0; width:100%; height:100%; background:rgba(0,0,0,0.5);">
        <div style="background:white; margin:10% auto; padding:20px; width:400px; border-radius:8px; box-shadow:0 4px 6px rgba(0,0,0,0.1);">
            <h3 style="margin-top:0;">Mode de règlement</h3>
            <p id="modalClientNom" style="color:#666; margin-bottom:20px;"></p>
            
            <div style="margin-bottom:15px;">
                <label style="display:block; margin-bottom:5px; font-weight:600;">Mode de règlement *</label>
                <select id="modeReglement" style="width:100%; padding:8px; border:1px solid #ddd; border-radius:4px; font-size:14px;">
                    <option value="">-- Sélectionner --</option>
                    <option value="especes">Espèces</option>
                    <option value="cheque">Chèque</option>
                    <option value="carte_bancaire">Carte bancaire</option>
                    <option value="virement">Virement</option>
                    <option value="prelevement">Prélèvement</option>
                    <option value="avance_immediate">💰 Avance immédiate</option>
                </select>
            </div>
            
            <div style="margin-bottom:20px;">
                <label style="display:block; margin-bottom:5px; font-weight:600;">Date de vente *</label>
                <input type="date" id="dateVente" value="<?php echo date('Y-m-d'); ?>" style="width:100%; padding:8px; border:1px solid #ddd; border-radius:4px; font-size:14px; margin-bottom:12px;">

                <label style="display:block; margin-bottom:5px; font-weight:600;">Date de paiement *</label>
                <input type="date" id="datePaiement" value="<?php echo date('Y-m-d'); ?>" style="width:100%; padding:8px; border:1px solid #ddd; border-radius:4px; font-size:14px;">
            </div>
            
            <div style="display:flex; gap:10px; justify-content:flex-end;">
                <button onclick="annulerReglement()" style="padding:8px 16px; background:#999; color:white; border:none; border-radius:4px; cursor:pointer;">Annuler</button>
                <button onclick="confirmerReglement()" style="padding:8px 16px; background:#4caf50; color:white; border:none; border-radius:4px; cursor:pointer;">Confirmer</button>
            </div>
        </div>
    </div>

    <script>
        let currentForfaitId = null;
        let currentClientId = null;
        
        function cloturer(rdvId) {
            // Rediriger vers l'agenda et lancer automatiquement le processus de clôture
            // Le paramètre auto_close=1 déclenche directement clotureIntervention()
            window.location.href = 'agenda.php?open_event=' + rdvId + '&auto_close=1';
        }
        
        function marquerPaye(forfaitId, clientId, clientNom, dateVente) {
            // Ouvrir la modale pour sélectionner le mode de règlement
            currentForfaitId = forfaitId;
            currentClientId = clientId;
            document.getElementById('modalClientNom').textContent = 'Forfait de ' + clientNom;
            document.getElementById('dateVente').value = dateVente || '<?php echo date('Y-m-d'); ?>';
            document.getElementById('datePaiement').value = '<?php echo date('Y-m-d'); ?>';
            
            // Récupérer le dernier mode de règlement utilisé par ce client
            fetch('api/forfaits.php?action=dernier_mode_reglement&client_id=' + clientId)
                .then(resp => resp.json())
                .then(data => {
                    const selectMode = document.getElementById('modeReglement');
                    if (data.mode_reglement) {
                        // Pré-sélectionner le dernier mode utilisé
                        selectMode.value = data.mode_reglement;
                    } else {
                        // Aucun historique : laisser vide
                        selectMode.value = '';
                    }
                })
                .catch(err => {
                    console.warn('Impossible de récupérer le dernier mode de règlement:', err);
                    document.getElementById('modeReglement').value = '';
                });
            
            document.getElementById('modalReglement').style.display = 'block';
        }
        
        function annulerReglement() {
            document.getElementById('modalReglement').style.display = 'none';
            currentForfaitId = null;
            currentClientId = null;
        }
        
        function confirmerReglement() {
            const modeReglement = document.getElementById('modeReglement').value;
            const dateVente = document.getElementById('dateVente').value;
            const datePaiement = document.getElementById('datePaiement').value;
            
            if (!modeReglement) {
                alert('Veuillez sélectionner un mode de règlement');
                return;
            }
            
            if (!datePaiement) {
                alert('Veuillez saisir une date de paiement');
                return;
            }

            if (!dateVente) {
                alert('Veuillez saisir une date de vente');
                return;
            }
            
            fetch('api/forfaits.php?action=marquer_paye', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({ 
                    forfait_id: currentForfaitId,
                    client_id: currentClientId,
                    mode_reglement: modeReglement,
                    date_vente: dateVente,
                    date_paiement: datePaiement
                })
            })
            .then(resp => resp.json())
            .then(json => {
                if (json.error) {
                    alert('Erreur : ' + json.error);
                } else {
                    alert('Forfait marqué comme payé !');
                    location.reload();
                }
            })
            .catch(err => {
                console.error('Erreur marquer payé:', err);
                alert('Erreur réseau');
            });
        }
    </script>
</body>
</html>
