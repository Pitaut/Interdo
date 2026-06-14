<?php
require_once 'config.php';

$message = '';
$error = '';

// Traitement des actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pdo = getDBConnection();
    $action = $_POST['action'] ?? '';
    
    try {
        if ($action === 'ajouter') {
            $stmt = $pdo->prepare("
                INSERT INTO vehicules (nom, immatriculation, type_vehicule, puissance_fiscale,
                                      kilometrage_annuel_estime, mode_calcul_cout,
                                      cout_mensuel_assurance, cout_mensuel_entretien, cout_mensuel_autre, 
                                      cout_carburant_km, cout_usure_km, date_acquisition, kilometrage_actuel, actif)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $_POST['nom'],
                $_POST['immatriculation'],
                $_POST['type_vehicule'],
                $_POST['puissance_fiscale'] ?: null,
                $_POST['kilometrage_annuel_estime'] ?: 15000,
                $_POST['mode_calcul_cout'] ?: 'bareme_fiscal',
                $_POST['cout_mensuel_assurance'],
                $_POST['cout_mensuel_entretien'],
                $_POST['cout_mensuel_autre'],
                $_POST['cout_carburant_km'],
                $_POST['cout_usure_km'],
                $_POST['date_acquisition'] ?: null,
                $_POST['kilometrage_actuel'],
                isset($_POST['actif']) ? 1 : 0
            ]);
            $message = 'Véhicule ajouté avec succès';
            
        } elseif ($action === 'modifier') {
            $stmt = $pdo->prepare("
                UPDATE vehicules 
                SET nom = ?, immatriculation = ?, type_vehicule = ?, puissance_fiscale = ?,
                    kilometrage_annuel_estime = ?, mode_calcul_cout = ?,
                    cout_mensuel_assurance = ?, cout_mensuel_entretien = ?, cout_mensuel_autre = ?, 
                    cout_carburant_km = ?, cout_usure_km = ?, date_acquisition = ?, 
                    kilometrage_actuel = ?, actif = ?
                WHERE id = ?
            ");
            $stmt->execute([
                $_POST['nom'],
                $_POST['immatriculation'],
                $_POST['type_vehicule'],
                $_POST['puissance_fiscale'] ?: null,
                $_POST['kilometrage_annuel_estime'] ?: 15000,
                $_POST['mode_calcul_cout'] ?: 'bareme_fiscal',
                $_POST['cout_mensuel_assurance'],
                $_POST['cout_mensuel_entretien'],
                $_POST['cout_mensuel_autre'],
                $_POST['cout_carburant_km'],
                $_POST['cout_usure_km'],
                $_POST['date_acquisition'] ?: null,
                $_POST['kilometrage_actuel'],
                isset($_POST['actif']) ? 1 : 0,
                $_POST['id']
            ]);
            $message = 'Véhicule modifié avec succès';
            
        } elseif ($action === 'supprimer') {
            $stmt = $pdo->prepare("DELETE FROM vehicules WHERE id = ?");
            $stmt->execute([$_POST['id']]);
            $message = 'Véhicule supprimé avec succès';
        }
    } catch (Exception $e) {
        $error = 'Erreur : ' . $e->getMessage();
    }
}

// Récupération des véhicules avec le nom du technicien
try {
    $pdo = getDBConnection();
    $stmt = $pdo->query("
        SELECT v.*, 
               GROUP_CONCAT(
                   CONCAT(t.prenom, ' ', t.nom, IF(tv.principal = 1, ' ★', ''))
                   ORDER BY tv.principal DESC, t.nom
                   SEPARATOR ', '
               ) as technicien_nom
        FROM vehicules v
        LEFT JOIN techniciens_vehicules tv ON v.id = tv.id_vehicule AND tv.date_fin IS NULL
        LEFT JOIN techniciens t ON tv.id_technicien = t.id
        GROUP BY v.id
        ORDER BY v.actif DESC, v.nom ASC
    ");
    $vehicules = $stmt->fetchAll();
    
    // Récupération des techniciens pour le formulaire
    $stmt_tech = $pdo->query("SELECT id, prenom, nom FROM techniciens WHERE actif = 1 ORDER BY nom, prenom");
    $techniciens = $stmt_tech->fetchAll();
} catch (Exception $e) {
    die('Erreur : ' . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des véhicules - <?php echo APP_NAME; ?></title>
    <link rel="stylesheet" href="includes/common_styles.css">
    <style>
        .vehicules-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }
        
        .vehicule-card {
            background: white;
            border-radius: 8px;
            padding: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .vehicule-card.inactif {
            opacity: 0.6;
            background: #f5f5f5;
        }
        
        .vehicule-header {
            display: flex;
            justify-content: space-between;
            align-items: start;
            margin-bottom: 15px;
        }
        
        .vehicule-nom {
            font-size: 18px;
            font-weight: bold;
            color: #333;
        }
        
        .vehicule-immat {
            background: #2196f3;
            color: white;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: bold;
        }
        
        .vehicule-info {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
            margin-bottom: 15px;
        }
        
        .info-item {
            font-size: 13px;
        }
        
        .info-label {
            color: #666;
            display: block;
            font-size: 11px;
            text-transform: uppercase;
        }
        
        .info-value {
            font-weight: 600;
            color: #333;
        }
        
        .vehicule-couts {
            background: #f9f9f9;
            padding: 10px;
            border-radius: 4px;
            margin-bottom: 10px;
        }
        
        .cout-ligne {
            display: flex;
            justify-content: space-between;
            font-size: 12px;
            margin-bottom: 5px;
        }
        
        .vehicule-actions {
            display: flex;
            gap: 10px;
        }
        
        .btn {
            padding: 8px 16px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
            flex: 1;
        }
        
        .btn-primary { background: #2196f3; color: white; }
        .btn-danger { background: #f44336; color: white; }
        .btn-success { background: #4caf50; color: white; }
        
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
        }
        
        .modal-content {
            background: white;
            margin: 50px auto;
            padding: 30px;
            border-radius: 8px;
            width: 90%;
            max-width: 600px;
            max-height: 80vh;
            overflow-y: auto;
        }
        
        .form-group {
            margin-bottom: 15px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: 600;
            font-size: 14px;
        }
        
        .form-group input, .form-group select {
            width: 100%;
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
        }
        
        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
        }
        
        .checkbox-group {
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .message {
            padding: 12px 16px;
            border-radius: 4px;
            margin-bottom: 20px;
        }
        
        .message-success { background: #d4edda; color: #155724; }
        .message-error { background: #f8d7da; color: #721c24; }
    </style>
</head>
<body>
    <?php include 'includes/header.php'; ?>
    
    <div class="main-container">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
            <h1>🚗 Gestion des véhicules</h1>
            <button class="btn btn-success" onclick="ouvrirModalAjout()">+ Ajouter un véhicule</button>
        </div>
        
        <?php if ($message): ?>
            <div class="message message-success"><?php echo htmlspecialchars($message); ?></div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="message message-error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        
        <div class="vehicules-grid">
            <?php foreach ($vehicules as $v): ?>
                <div class="vehicule-card <?php echo $v['actif'] ? '' : 'inactif'; ?>">
                    <div class="vehicule-header">
                        <div>
                            <div class="vehicule-nom"><?php echo htmlspecialchars($v['nom']); ?></div>
                            <span class="vehicule-immat"><?php echo htmlspecialchars($v['immatriculation']); ?></span>
                        </div>
                        <?php if (!$v['actif']): ?>
                            <span style="background: #999; color: white; padding: 4px 8px; border-radius: 4px; font-size: 12px;">Inactif</span>
                        <?php endif; ?>
                    </div>
                    
                    <div class="vehicule-info">
                        <div class="info-item">
                            <span class="info-label">Type</span>
                            <span class="info-value"><?php echo ucfirst($v['type_vehicule']); ?></span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Kilométrage</span>
                            <span class="info-value"><?php echo number_format($v['kilometrage_actuel'], 0, '', ' '); ?> km</span>
                        </div>
                        <div class="info-item" style="grid-column: 1 / -1;">
                            <span class="info-label">👤 Technicien attribué</span>
                            <span class="info-value" style="color: #2196f3;">
                                <?php echo $v['technicien_nom'] ? htmlspecialchars($v['technicien_nom']) : '<em style="color: #999;">Non attribué</em>'; ?>
                            </span>
                        </div>
                    </div>
                    
                    <div class="vehicule-couts">
                        <div class="cout-ligne">
                            <span>Assurance mensuelle :</span>
                            <strong><?php echo number_format($v['cout_mensuel_assurance'], 2, ',', ' '); ?> €</strong>
                        </div>
                        <div class="cout-ligne">
                            <span>Entretien mensuel :</span>
                            <strong><?php echo number_format($v['cout_mensuel_entretien'], 2, ',', ' '); ?> €</strong>
                        </div>
                        <div class="cout-ligne">
                            <span>Coût/km (carburant) :</span>
                            <strong><?php echo number_format($v['cout_carburant_km'], 4, ',', ' '); ?> €</strong>
                        </div>
                        <div class="cout-ligne">
                            <span>Coût/km (usure) :</span>
                            <strong><?php echo number_format($v['cout_usure_km'], 4, ',', ' '); ?> €</strong>
                        </div>
                        <div class="cout-ligne" style="border-top: 1px solid #ddd; padding-top: 5px; margin-top: 5px;">
                            <span><strong>Total/km :</strong></span>
                            <strong style="color: #f44336;"><?php echo number_format($v['cout_carburant_km'] + $v['cout_usure_km'], 4, ',', ' '); ?> €</strong>
                        </div>
                    </div>
                    
                    <div class="vehicule-actions">
                        <button class="btn btn-primary" onclick='modifierVehicule(<?php echo json_encode($v); ?>)'>Modifier</button>
                        <button class="btn btn-danger" onclick="supprimerVehicule(<?php echo $v['id']; ?>, '<?php echo htmlspecialchars($v['nom'], ENT_QUOTES); ?>')">Supprimer</button>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
    
    <!-- Modal Ajout/Modification -->
    <div id="modalVehicule" class="modal">
        <div class="modal-content">
            <h2 id="modalTitre">Ajouter un véhicule</h2>
            <form method="POST" id="formVehicule">
                <input type="hidden" name="action" id="formAction" value="ajouter">
                <input type="hidden" name="id" id="vehiculeId">
                
                <div class="form-group">
                    <label>Nom/Désignation *</label>
                    <input type="text" name="nom" id="vehiculeNom" required>
                </div>
                
                <div class="form-grid">
                    <div class="form-group">
                        <label>Immatriculation *</label>
                        <input type="text" name="immatriculation" id="vehiculeImmat" required>
                    </div>
                    
                    <div class="form-group">
                        <label>Type</label>
                        <select name="type_vehicule" id="vehiculeType">
                            <option value="utilitaire">Utilitaire</option>
                            <option value="voiture">Voiture</option>
                            <option value="camionnette">Camionnette</option>
                            <option value="moto">Moto</option>
                        </select>
                    </div>
                </div>
                
                <div class="form-grid">
                    <div class="form-group">
                        <label>Puissance fiscale (CV)</label>
                        <input type="number" name="puissance_fiscale" id="vehiculePuissance" min="1" max="20" placeholder="Ex: 5">
                        <small style="color: #666; font-size: 11px;">Chevaux fiscaux (indiqués sur la carte grise)</small>
                    </div>
                    
                    <div class="form-group">
                        <label>Kilométrage annuel estimé</label>
                        <input type="number" name="kilometrage_annuel_estime" id="vehiculeKmAnnuel" value="15000" min="0" step="1000">
                        <small style="color: #666; font-size: 11px;">Utilisé pour le barème fiscal</small>
                    </div>
                </div>
                
                <div class="form-group">
                    <label>Mode de calcul des coûts</label>
                    <select name="mode_calcul_cout" id="vehiculeModeCout" onchange="toggleModeCalcul(this.value)">
                        <option value="bareme_fiscal">📊 Barème fiscal (recommandé)</option>
                        <option value="cout_reel">💰 Coûts réels</option>
                    </select>
                    <small style="color: #666; font-size: 11px;">
                        Barème fiscal = calcul selon impots.gouv.fr | Coûts réels = carburant + usure manuels
                        <a href="bareme_fiscal.php" target="_blank" style="color: #2196f3; text-decoration: underline;">Voir les barèmes</a>
                    </small>
                </div>
                
                <div class="alert alert-info" style="background: #e3f2fd; padding: 10px; border-radius: 4px; margin: 15px 0; font-size: 13px;">
                    💡 <strong>Attribution aux techniciens :</strong> Gérez les attributions de véhicules depuis la page <a href="techniciens.php" style="color: #1976d2; text-decoration: underline;">Techniciens</a>
                </div>
                
                <div id="coutsReelsSection" class="form-grid" style="display: none;">
                    <div class="form-group">
                        <label>Assurance mensuelle (€)</label>
                        <input type="number" step="0.01" name="cout_mensuel_assurance" id="vehiculeAssurance" value="0">
                    </div>
                    
                    <div class="form-group">
                        <label>Entretien mensuel (€)</label>
                        <input type="number" step="0.01" name="cout_mensuel_entretien" id="vehiculeEntretien" value="0">
                    </div>
                </div>
                
                <div class="form-grid">
                    <div class="form-group">
                        <label>Autres frais mensuels (€)</label>
                        <input type="number" step="0.01" name="cout_mensuel_autre" id="vehiculeAutre" value="0">
                    </div>
                    
                    <div class="form-group">
                        <label>Kilométrage actuel</label>
                        <input type="number" name="kilometrage_actuel" id="vehiculeKm" value="0">
                    </div>
                </div>
                
                <div class="form-grid">
                    <div class="form-group">
                        <label>Coût carburant/km (€)</label>
                        <input type="number" step="0.0001" name="cout_carburant_km" id="vehiculeCarburant" value="0.15">
                    </div>
                    
                    <div class="form-group">
                        <label>Coût usure/km (€)</label>
                        <input type="number" step="0.0001" name="cout_usure_km" id="vehiculeUsure" value="0.05">
                    </div>
                </div>
                
                <div class="form-group">
                    <label>Date d'acquisition</label>
                    <input type="date" name="date_acquisition" id="vehiculeDate">
                </div>
                
                <div class="form-group">
                    <div class="checkbox-group">
                        <input type="checkbox" name="actif" id="vehiculeActif" checked>
                        <label for="vehiculeActif" style="margin: 0;">Véhicule actif (en service)</label>
                    </div>
                </div>
                
                <div style="display: flex; gap: 10px; margin-top: 20px;">
                    <button type="submit" class="btn btn-success" style="flex: 1;">Enregistrer</button>
                    <button type="button" class="btn btn-danger" onclick="fermerModal()">Annuler</button>
                </div>
            </form>
        </div>
    </div>
    
    <script>
        function toggleModeCalcul(mode) {
            const coutsReelsSection = document.getElementById('coutsReelsSection');
            if (mode === 'cout_reel') {
                coutsReelsSection.style.display = 'grid';
            } else {
                coutsReelsSection.style.display = 'none';
            }
        }
        
        function ouvrirModalAjout() {
            document.getElementById('modalTitre').textContent = 'Ajouter un véhicule';
            document.getElementById('formAction').value = 'ajouter';
            document.getElementById('formVehicule').reset();
            document.getElementById('vehiculeId').value = '';
            document.getElementById('vehiculeActif').checked = true;
            document.getElementById('vehiculeKmAnnuel').value = '15000';
            document.getElementById('vehiculeModeCout').value = 'bareme_fiscal';
            toggleModeCalcul('bareme_fiscal');
            document.getElementById('modalVehicule').style.display = 'block';
        }
        
        function modifierVehicule(vehicule) {
            document.getElementById('modalTitre').textContent = 'Modifier un véhicule';
            document.getElementById('formAction').value = 'modifier';
            document.getElementById('vehiculeId').value = vehicule.id;
            document.getElementById('vehiculeNom').value = vehicule.nom;
            document.getElementById('vehiculeImmat').value = vehicule.immatriculation;
            document.getElementById('vehiculeType').value = vehicule.type_vehicule;
            document.getElementById('vehiculePuissance').value = vehicule.puissance_fiscale || '';
            document.getElementById('vehiculeKmAnnuel').value = vehicule.kilometrage_annuel_estime || 15000;
            document.getElementById('vehiculeModeCout').value = vehicule.mode_calcul_cout || 'bareme_fiscal';
            toggleModeCalcul(vehicule.mode_calcul_cout || 'bareme_fiscal');
            document.getElementById('vehiculeAssurance').value = vehicule.cout_mensuel_assurance;
            document.getElementById('vehiculeEntretien').value = vehicule.cout_mensuel_entretien;
            document.getElementById('vehiculeAutre').value = vehicule.cout_mensuel_autre;
            document.getElementById('vehiculeCarburant').value = vehicule.cout_carburant_km;
            document.getElementById('vehiculeUsure').value = vehicule.cout_usure_km;
            document.getElementById('vehiculeKm').value = vehicule.kilometrage_actuel;
            document.getElementById('vehiculeDate').value = vehicule.date_acquisition || '';
            document.getElementById('vehiculeActif').checked = vehicule.actif == 1;
            document.getElementById('modalVehicule').style.display = 'block';
        }
        
        function fermerModal() {
            document.getElementById('modalVehicule').style.display = 'none';
        }
        
        function supprimerVehicule(id, nom) {
            if (confirm(`Êtes-vous sûr de vouloir supprimer le véhicule "${nom}" ?\n\nATTENTION : Cette action est irréversible.`)) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="supprimer">
                    <input type="hidden" name="id" value="${id}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }
        
        // Fermer le modal en cliquant en dehors
        window.onclick = function(event) {
            const modal = document.getElementById('modalVehicule');
            if (event.target == modal) {
                fermerModal();
            }
        }
    </script>
</body>
</html>
