<?php
/**
 * Script de test pour vérifier le système de détection des clients à risque
 * 
 * Usage: http://localhost/_Interdo/test_client_risque.php?client_id=X
 * Ou sans paramètre pour tester tous les clients
 */

require_once 'config.php';

// Récupérer l'ID client depuis l'URL ou tester tous les clients
$clientId = isset($_GET['client_id']) ? (int)$_GET['client_id'] : null;

?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Test - Clients à Risque</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            background: #f5f5f5;
            padding: 20px;
            margin: 0;
        }
        .container {
            max-width: 1200px;
            margin: 0 auto;
            background: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        h1 {
            color: #333;
            border-bottom: 3px solid #667eea;
            padding-bottom: 10px;
        }
        .config-section {
            background: #f9f9f9;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 30px;
            border-left: 4px solid #667eea;
        }
        .config-section h2 {
            margin-top: 0;
            color: #667eea;
            font-size: 18px;
        }
        .config-item {
            display: flex;
            justify-content: space-between;
            padding: 5px 0;
            border-bottom: 1px solid #eee;
        }
        .config-item:last-child {
            border-bottom: none;
        }
        .config-label {
            color: #666;
            font-weight: 500;
        }
        .config-value {
            color: #333;
            font-family: 'Courier New', monospace;
        }
        .client-card {
            border: 2px solid #ddd;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
            transition: all 0.3s;
        }
        .client-card.at-risk {
            border-color: #f44336;
            background: #fff5f5;
        }
        .client-card.safe {
            border-color: #4caf50;
            background: #f5fff5;
        }
        .client-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
            padding-bottom: 15px;
            border-bottom: 2px solid #eee;
        }
        .client-name {
            font-size: 20px;
            font-weight: bold;
            color: #333;
        }
        .risk-badge {
            padding: 8px 16px;
            border-radius: 20px;
            font-weight: bold;
            font-size: 14px;
            text-transform: uppercase;
        }
        .risk-badge.danger {
            background: #f44336;
            color: white;
        }
        .risk-badge.success {
            background: #4caf50;
            color: white;
        }
        .criteres-list {
            margin-top: 15px;
        }
        .critere-item {
            background: #fff;
            padding: 10px;
            margin: 5px 0;
            border-left: 4px solid #f44336;
            border-radius: 3px;
        }
        .critere-type {
            font-weight: bold;
            color: #f44336;
            text-transform: uppercase;
            font-size: 12px;
        }
        .critere-detail {
            color: #666;
            margin-top: 5px;
        }
        .no-critere {
            color: #4caf50;
            font-style: italic;
        }
        .stats-summary {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 30px;
        }
        .stat-card {
            background: #667eea;
            color: white;
            padding: 20px;
            border-radius: 8px;
            text-align: center;
        }
        .stat-card.danger {
            background: #f44336;
        }
        .stat-card.success {
            background: #4caf50;
        }
        .stat-number {
            font-size: 36px;
            font-weight: bold;
        }
        .stat-label {
            font-size: 14px;
            opacity: 0.9;
            margin-top: 5px;
        }
        .back-link {
            display: inline-block;
            padding: 10px 20px;
            background: #667eea;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        .back-link:hover {
            background: #5568d3;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔍 Système de Détection des Clients à Risque</h1>
        
        <!-- Configuration actuelle -->
        <div class="config-section">
            <h2>⚙️ Configuration Active</h2>
            <div class="config-item">
                <span class="config-label">Système activé</span>
                <span class="config-value"><?php echo CLIENT_RISQUE_ENABLED ? 'OUI' : 'NON'; ?></span>
            </div>
            <div class="config-item">
                <span class="config-label">Nombre de critères minimum</span>
                <span class="config-value"><?php echo CLIENT_RISQUE_CONFIG['nombre_criteres_minimum']; ?></span>
            </div>
            <div class="config-item">
                <span class="config-label">Seuil hors forfait (heures)</span>
                <span class="config-value"><?php echo CLIENT_RISQUE_CONFIG['hors_forfait_impaye_heures_seuil']; ?> h</span>
            </div>
            <div class="config-item">
                <span class="config-label">Seuil hors forfait (montant)</span>
                <span class="config-value"><?php echo number_format(CLIENT_RISQUE_CONFIG['hors_forfait_impaye_montant_seuil'], 2, ',', ' '); ?> €</span>
            </div>
            <div class="config-item">
                <span class="config-label">Seuil forfaits impayés</span>
                <span class="config-value"><?php echo CLIENT_RISQUE_CONFIG['forfaits_impayes_nombre_seuil']; ?> forfait(s)</span>
            </div>
            <div class="config-item">
                <span class="config-label">Seuil heures restantes</span>
                <span class="config-value"><?php echo CLIENT_RISQUE_CONFIG['forfait_heures_restantes_absolu_seuil']; ?> h (<?php echo CLIENT_RISQUE_CONFIG['forfait_heures_restantes_pourcent_seuil']; ?>%)</span>
            </div>
            <div class="config-item">
                <span class="config-label">Délai sans rappel</span>
                <span class="config-value"><?php echo CLIENT_RISQUE_CONFIG['delai_dernier_rappel_jours']; ?> jours</span>
            </div>
            <div class="config-item">
                <span class="config-label">Seuil bonus excessif</span>
                <span class="config-value"><?php echo CLIENT_RISQUE_CONFIG['heure_bonus_seuil_max']; ?> h</span>
            </div>
            <div class="config-item">
                <span class="config-label">Modes de paiement à risque</span>
                <span class="config-value"><?php echo implode(', ', CLIENT_RISQUE_CONFIG['modes_paiement_risque']); ?></span>
            </div>
        </div>

        <?php
        $pdo = getDBConnection();
        
        if ($clientId) {
            // Test d'un client spécifique
            echo '<a href="test_client_risque.php" class="back-link">← Voir tous les clients</a>';
            
            // Récupérer les infos du client
            $stmt = $pdo->prepare("SELECT * FROM clients WHERE id = ?");
            $stmt->execute([$clientId]);
            $client = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$client) {
                echo '<p style="color: red;">Client introuvable (ID: ' . $clientId . ')</p>';
            } else {
                $evaluation = isClientAtRisk($clientId, $pdo);
                
                echo '<div class="client-card ' . ($evaluation['at_risk'] ? 'at-risk' : 'safe') . '">';
                echo '<div class="client-header">';
                echo '<div class="client-name">' . htmlspecialchars($client['prenom'] . ' ' . $client['nom']) . '</div>';
                echo '<span class="risk-badge ' . ($evaluation['at_risk'] ? 'danger' : 'success') . '">';
                echo $evaluation['at_risk'] ? '⚠️ À RISQUE' : '✅ OK';
                echo '</span>';
                echo '</div>';
                
                echo '<p><strong>Email :</strong> ' . htmlspecialchars($client['email']) . '</p>';
                echo '<p><strong>Téléphone :</strong> ' . htmlspecialchars($client['telephone_mobile'] ?: $client['telephone_fixe'] ?: 'N/A') . '</p>';
                echo '<p><strong>Mode de paiement :</strong> ' . htmlspecialchars($client['mode_paiement']) . '</p>';
                echo '<p><strong>Heures bonus :</strong> ' . number_format($client['heure_bonus'], 2, ',', ' ') . ' h</p>';
                
                if ($evaluation['nb_criteres'] > 0) {
                    echo '<div class="criteres-list">';
                    echo '<h3>📋 Critères déclenchés (' . $evaluation['nb_criteres'] . ')</h3>';
                    foreach ($evaluation['details'] as $type => $detail) {
                        echo '<div class="critere-item">';
                        echo '<div class="critere-type">' . str_replace('_', ' ', $type) . '</div>';
                        echo '<div class="critere-detail">' . htmlspecialchars($detail) . '</div>';
                        echo '</div>';
                    }
                    echo '</div>';
                } else {
                    echo '<p class="no-critere">✅ Aucun critère de risque détecté pour ce client</p>';
                }
                
                echo '</div>';
            }
        } else {
            // Tester tous les clients
            $stmt = $pdo->query("SELECT id, nom, prenom, email FROM clients ORDER BY nom, prenom");
            $clients = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Calculer les statistiques
            $totalClients = count($clients);
            $clientsAtRisk = 0;
            $clientsSafe = 0;
            $evaluations = [];
            
            foreach ($clients as $client) {
                $evaluation = isClientAtRisk($client['id'], $pdo);
                $evaluations[$client['id']] = $evaluation;
                
                if ($evaluation['at_risk']) {
                    $clientsAtRisk++;
                } else {
                    $clientsSafe++;
                }
            }
            
            // Afficher les statistiques
            echo '<div class="stats-summary">';
            echo '<div class="stat-card">';
            echo '<div class="stat-number">' . $totalClients . '</div>';
            echo '<div class="stat-label">Total Clients</div>';
            echo '</div>';
            
            echo '<div class="stat-card danger">';
            echo '<div class="stat-number">' . $clientsAtRisk . '</div>';
            echo '<div class="stat-label">Clients à Risque</div>';
            echo '</div>';
            
            echo '<div class="stat-card success">';
            echo '<div class="stat-number">' . $clientsSafe . '</div>';
            echo '<div class="stat-label">Clients OK</div>';
            echo '</div>';
            
            if ($totalClients > 0) {
                $pourcentRisque = round(($clientsAtRisk / $totalClients) * 100, 1);
                echo '<div class="stat-card">';
                echo '<div class="stat-number">' . $pourcentRisque . '%</div>';
                echo '<div class="stat-label">Taux de Risque</div>';
                echo '</div>';
            }
            echo '</div>';
            
            // Afficher d'abord les clients à risque
            if ($clientsAtRisk > 0) {
                echo '<h2 style="color: #f44336;">⚠️ Clients à Risque (' . $clientsAtRisk . ')</h2>';
                
                foreach ($clients as $client) {
                    $evaluation = $evaluations[$client['id']];
                    
                    if ($evaluation['at_risk']) {
                        echo '<div class="client-card at-risk">';
                        echo '<div class="client-header">';
                        echo '<div>';
                        echo '<div class="client-name">' . htmlspecialchars($client['prenom'] . ' ' . $client['nom']) . '</div>';
                        echo '<small style="color: #666;">' . htmlspecialchars($client['email']) . '</small>';
                        echo '</div>';
                        echo '<span class="risk-badge danger">⚠️ ' . $evaluation['nb_criteres'] . ' critère(s)</span>';
                        echo '</div>';
                        
                        echo '<div class="criteres-list">';
                        foreach ($evaluation['details'] as $type => $detail) {
                            echo '<div class="critere-item">';
                            echo '<div class="critere-type">' . str_replace('_', ' ', $type) . '</div>';
                            echo '<div class="critere-detail">' . htmlspecialchars($detail) . '</div>';
                            echo '</div>';
                        }
                        echo '</div>';
                        
                        echo '<p style="margin-top: 15px;"><a href="test_client_risque.php?client_id=' . $client['id'] . '">Voir détails complets →</a></p>';
                        echo '</div>';
                    }
                }
            } else {
                echo '<p class="no-critere">✅ Aucun client à risque détecté</p>';
            }
            
            // Afficher ensuite les clients OK (repliés)
            if ($clientsSafe > 0) {
                echo '<details style="margin-top: 30px;">';
                echo '<summary style="cursor: pointer; padding: 10px; background: #f9f9f9; border-radius: 5px; font-weight: bold; color: #4caf50;">';
                echo '✅ Clients OK (' . $clientsSafe . ') - Cliquer pour afficher';
                echo '</summary>';
                echo '<div style="margin-top: 15px;">';
                
                foreach ($clients as $client) {
                    $evaluation = $evaluations[$client['id']];
                    
                    if (!$evaluation['at_risk']) {
                        echo '<div class="client-card safe">';
                        echo '<div class="client-header">';
                        echo '<div>';
                        echo '<div class="client-name">' . htmlspecialchars($client['prenom'] . ' ' . $client['nom']) . '</div>';
                        echo '<small style="color: #666;">' . htmlspecialchars($client['email']) . '</small>';
                        echo '</div>';
                        echo '<span class="risk-badge success">✅ OK</span>';
                        echo '</div>';
                        echo '<p class="no-critere">Aucun critère de risque</p>';
                        echo '</div>';
                    }
                }
                
                echo '</div>';
                echo '</details>';
            }
        }
        ?>
        
        <div style="margin-top: 40px; padding: 20px; background: #f0f0f0; border-radius: 5px;">
            <h3>💡 Informations</h3>
            <p>Ce système évalue automatiquement les clients selon plusieurs critères :</p>
            <ul>
                <li><strong>Facturation impayée :</strong> heures hors forfait non payées, ancienneté des impayés</li>
                <li><strong>Forfaits impayés :</strong> forfaits signés mais non réglés</li>
                <li><strong>Épuisement de forfait :</strong> heures restantes faibles (&lt;10% ou &lt;2h)</li>
                <li><strong>Communication :</strong> absence de rappel depuis plus de <?php echo CLIENT_RISQUE_CONFIG['delai_dernier_rappel_jours']; ?> jours</li>
                <li><strong>Mode de paiement :</strong> paiement par chèque ou espèces (moins traçables)</li>
                <li><strong>Heures bonus :</strong> accumulation excessive d'heures bonus</li>
            </ul>
            <p><strong>Configuration :</strong> Tous les seuils sont modifiables dans <code>config.php</code> → <code>CLIENT_RISQUE_CONFIG</code></p>
        </div>
    </div>
</body>
</html>
