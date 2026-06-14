<?php
require_once 'config.php';

$pdo = getDBConnection();

// Récupérer les barèmes par année
$stmt = $pdo->query("
    SELECT DISTINCT annee_fiscale 
    FROM bareme_kilometrique 
    ORDER BY annee_fiscale DESC
");
$annees = $stmt->fetchAll(PDO::FETCH_COLUMN);

$annee_selectionnee = $_GET['annee'] ?? $annees[0] ?? date('Y');

$stmt = $pdo->prepare("
    SELECT * 
    FROM bareme_kilometrique 
    WHERE annee_fiscale = ?
    ORDER BY type_vehicule, puissance_min, distance_min
");
$stmt->execute([$annee_selectionnee]);
$baremes = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Barèmes kilométriques fiscaux - <?php echo APP_NAME; ?></title>
    <link rel="stylesheet" href="includes/common_styles.css">
    <style>
        .annees-tabs {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
            flex-wrap: wrap;
            align-items: center;
        }
        
        .annee-tab {
            padding: 10px 20px;
            background: white;
            border: 2px solid #ddd;
            border-radius: 4px;
            cursor: pointer;
            text-decoration: none;
            color: #333;
            font-weight: 500;
        }
        
        .annee-tab.active {
            background: #2196f3;
            color: white;
            border-color: #2196f3;
        }
        
        .annee-tab:hover:not(.active) {
            border-color: #2196f3;
            color: #2196f3;
        }
        
        .btn-action {
            padding: 10px 20px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-weight: 500;
            font-size: 14px;
            transition: all 0.2s;
        }
        
        .btn-primary {
            background: #4caf50;
            color: white;
        }
        
        .btn-primary:hover {
            background: #45a049;
        }
        
        .btn-danger {
            background: #f44336;
            color: white;
        }
        
        .btn-danger:hover {
            background: #da190b;
        }
        
        .btn-edit {
            background: #ff9800;
            color: white;
            padding: 4px 12px;
            font-size: 12px;
        }
        
        .btn-edit:hover {
            background: #e68900;
        }
        
        .bareme-section {
            background: white;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        
        .bareme-section h2 {
            margin-top: 0;
            color: #333;
            border-bottom: 2px solid #2196f3;
            padding-bottom: 10px;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
        }
        
        th {
            background: #f5f5f5;
            padding: 12px;
            text-align: left;
            font-weight: 600;
            font-size: 13px;
            border-bottom: 2px solid #ddd;
        }
        
        td {
            padding: 12px;
            border-bottom: 1px solid #eee;
        }
        
        .info-box {
            background: #e3f2fd;
            border-left: 4px solid #2196f3;
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 4px;
        }
        
        .info-box h3 {
            margin-top: 0;
            color: #1976d2;
        }
        
        .type-badge {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: 500;
        }
        
        .type-voiture { background: #e3f2fd; color: #1976d2; }
        .type-moto { background: #fff3e0; color: #f57c00; }
        .type-cyclomoteur { background: #f3e5f5; color: #7b1fa2; }
        
        /* Modal styles */
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
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
            max-width: 500px;
            width: 90%;
            max-height: 90vh;
            overflow-y: auto;
        }
        
        .modal-header {
            margin-bottom: 20px;
        }
        
        .modal-header h2 {
            margin: 0;
            color: #333;
        }
        
        .form-group {
            margin-bottom: 15px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: 500;
            color: #555;
        }
        
        .form-group input,
        .form-group select {
            width: 100%;
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
            box-sizing: border-box;
        }
        
        .form-actions {
            display: flex;
            gap: 10px;
            justify-content: flex-end;
            margin-top: 20px;
        }
        
        .btn-cancel {
            background: #999;
            color: white;
        }
        
        .btn-cancel:hover {
            background: #777;
        }
        
        .input-editable {
            border: 1px solid transparent;
            padding: 4px;
            border-radius: 3px;
            cursor: pointer;
            transition: all 0.2s;
        }
        
        .input-editable:hover {
            border-color: #2196f3;
            background: #f5f5f5;
        }
        
        .input-editable:focus {
            outline: none;
            border-color: #2196f3;
            background: white;
            cursor: text;
        }
    </style>
</head>
<body>
    <?php include 'includes/header.php'; ?>
    
    <div class="main-container">
        <h1>📊 Barèmes kilométriques fiscaux</h1>
        
        <div class="info-box">
            <h3>ℹ️ À propos des barèmes kilométriques</h3>
            <p>Les barèmes kilométriques sont fixés chaque année par l'administration fiscale française. Ils permettent d'évaluer les frais de véhicule de manière forfaitaire en fonction de la puissance fiscale et du kilométrage annuel.</p>
            <p><strong>Source :</strong> <a href="https://www.impots.gouv.fr/bareme-kilometrique" target="_blank">impots.gouv.fr</a></p>
            <p><strong>💡 Nouveauté :</strong> Vous pouvez maintenant créer de nouvelles années en dupliquant les barèmes existants, et modifier les valeurs directement en cliquant dans les champs du tableau.</p>
        </div>
        
        <div class="annees-tabs">
            <?php foreach ($annees as $annee): ?>
                <a href="?annee=<?php echo $annee; ?>" 
                   class="annee-tab <?php echo $annee == $annee_selectionnee ? 'active' : ''; ?>">
                    <?php echo $annee; ?>
                </a>
            <?php endforeach; ?>
            
            <button class="btn-action btn-primary" onclick="ouvrirModalDupliquer()">
                ➕ Nouvelle année
            </button>
            
            <?php if ($annee_selectionnee): ?>
                <button class="btn-action btn-danger" onclick="supprimerAnnee(<?php echo $annee_selectionnee; ?>)">
                    🗑️ Supprimer <?php echo $annee_selectionnee; ?>
                </button>
            <?php endif; ?>
        </div>
        
        <?php
        $baremes_par_type = [];
        foreach ($baremes as $bareme) {
            $baremes_par_type[$bareme['type_vehicule']][] = $bareme;
        }
        
        foreach ($baremes_par_type as $type => $liste_baremes):
            switch($type) {
                case 'voiture':
                    $type_label = '🚗 Voitures et véhicules utilitaires';
                    break;
                case 'moto':
                    $type_label = '🏍️ Motos';
                    break;
                case 'scooter':
                    $type_label = '🛵 Scooters';
                    break;
                case 'cyclomoteur':
                    $type_label = '🛵 Cyclomoteurs';
                    break;
                default:
                    $type_label = $type;
            }
            $badge_class = 'type-' . $type;
        ?>
        
        <div class="bareme-section">
            <h2><span class="type-badge <?php echo $badge_class; ?>"><?php echo $type_label; ?></span></h2>
            
            <table>
                <thead>
                    <tr>
                        <th>Puissance (CV)</th>
                        <th>Kilométrage annuel</th>
                        <th>Formule</th>
                        <th>Coût fixe</th>
                        <th>Coût variable (€/km)</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($liste_baremes as $bareme): 
                        $puissance = $bareme['puissance_min'] == $bareme['puissance_max'] 
                            ? $bareme['puissance_min'] . ' CV'
                            : $bareme['puissance_min'] . ' à ' . $bareme['puissance_max'] . ' CV';
                        
                        $km = '';
                        if ($bareme['distance_min'] == 0 && $bareme['distance_max'] < 999999) {
                            $km = 'Jusqu\'à ' . number_format($bareme['distance_max'], 0, ',', ' ') . ' km';
                        } elseif ($bareme['distance_min'] > 0 && $bareme['distance_max'] < 999999) {
                            $km = number_format($bareme['distance_min'], 0, ',', ' ') . ' à ' . number_format($bareme['distance_max'], 0, ',', ' ') . ' km';
                        } else {
                            $km = 'Plus de ' . number_format($bareme['distance_min'], 0, ',', ' ') . ' km';
                        }
                    ?>
                    <tr data-id="<?php echo $bareme['id']; ?>">
                        <td><?php echo $puissance; ?></td>
                        <td><?php echo $km; ?></td>
                        <td>
                            <input type="text" 
                                   class="input-editable" 
                                   value="<?php echo htmlspecialchars($bareme['formule_calcul']); ?>"
                                   onblur="updateBareme(<?php echo $bareme['id']; ?>, 'formule_calcul', this.value)"
                                   style="width: 100%; font-family: monospace;">
                        </td>
                        <td>
                            <input type="number" 
                                   class="input-editable" 
                                   value="<?php echo $bareme['cout_fixe']; ?>"
                                   step="0.01"
                                   onblur="updateBareme(<?php echo $bareme['id']; ?>, 'cout_fixe', this.value)"
                                   style="width: 100px;"> €
                        </td>
                        <td>
                            <input type="number" 
                                   class="input-editable" 
                                   value="<?php echo $bareme['cout_variable']; ?>"
                                   step="0.0001"
                                   onblur="updateBareme(<?php echo $bareme['id']; ?>, 'cout_variable', this.value)"
                                   style="width: 100px;"> €
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
        <?php endforeach; ?>
        
        <div class="info-box">
            <h3>💡 Comment utiliser ces barèmes ?</h3>
            <ul>
                <li>Dans la page <a href="vehicules.php">Véhicules</a>, configurez la <strong>puissance fiscale (CV)</strong> et le <strong>kilométrage annuel estimé</strong></li>
                <li>Choisissez le <strong>mode de calcul</strong> : "Barème fiscal" ou "Coût réel"</li>
                <li>Les coûts seront automatiquement calculés selon le barème correspondant lors de chaque intervention</li>
                <li>L'historique est conservé : chaque intervention garde une référence au barème utilisé</li>
            </ul>
        </div>
    </div>
    
    <!-- Modal pour dupliquer une année -->
    <div id="modalDupliquer" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>➕ Créer une nouvelle année</h2>
            </div>
            
            <div class="form-group">
                <label>Année source (à copier)</label>
                <select id="anneeSource">
                    <?php foreach ($annees as $annee): ?>
                        <option value="<?php echo $annee; ?>" <?php echo $annee == $annee_selectionnee ? 'selected' : ''; ?>>
                            <?php echo $annee; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="form-group">
                <label>Nouvelle année</label>
                <input type="number" id="anneeCible" value="<?php echo date('Y') + 1; ?>" min="2020" max="2050">
            </div>
            
            <div class="form-group">
                <label>Coefficient d'ajustement (ex: 1.02 = +2%)</label>
                <input type="number" id="coefficient" value="1.02" step="0.01" min="0.5" max="2">
            </div>
            
            <div class="info-box" style="margin-top: 15px;">
                <p style="margin: 0; font-size: 13px;">
                    💡 Les barèmes de l'année source seront copiés vers la nouvelle année avec le coefficient appliqué.
                    Vous pourrez ensuite les modifier individuellement.
                </p>
            </div>
            
            <div class="form-actions">
                <button class="btn-action btn-cancel" onclick="fermerModal('modalDupliquer')">Annuler</button>
                <button class="btn-action btn-primary" onclick="dupliquerAnnee()">✓ Créer</button>
            </div>
        </div>
    </div>
    
    <script>
        function ouvrirModalDupliquer() {
            document.getElementById('modalDupliquer').classList.add('active');
        }
        
        function fermerModal(modalId) {
            document.getElementById(modalId).classList.remove('active');
        }
        
        // Fermer modal si clic en dehors
        document.querySelectorAll('.modal').forEach(modal => {
            modal.addEventListener('click', function(e) {
                if (e.target === this) {
                    fermerModal(this.id);
                }
            });
        });
        
        async function dupliquerAnnee() {
            const anneeSource = document.getElementById('anneeSource').value;
            const anneeCible = document.getElementById('anneeCible').value;
            const coefficient = document.getElementById('coefficient').value;
            
            if (!anneeCible || anneeCible <= anneeSource) {
                alert('L\'année cible doit être postérieure à l\'année source');
                return;
            }
            
            if (!confirm(`Dupliquer les barèmes de ${anneeSource} vers ${anneeCible} avec un coefficient de ${coefficient} ?`)) {
                return;
            }
            
            try {
                const formData = new FormData();
                formData.append('action', 'duplicate_year');
                formData.append('annee_source', anneeSource);
                formData.append('annee_cible', anneeCible);
                formData.append('coefficient', coefficient);
                
                const response = await fetch('api/bareme.php', {
                    method: 'POST',
                    body: formData
                });
                
                const data = await response.json();
                
                if (data.status === 'success') {
                    alert(data.message);
                    window.location.href = '?annee=' + anneeCible;
                } else {
                    alert('Erreur: ' + (data.error || 'Erreur inconnue'));
                }
            } catch (error) {
                console.error('Erreur:', error);
                alert('Erreur lors de la duplication');
            }
        }
        
        async function supprimerAnnee(annee) {
            if (!confirm(`⚠️ ATTENTION ⚠️\n\nSupprimer tous les barèmes de l'année ${annee} ?\n\nCette action est irréversible et échouera si des interventions utilisent ces barèmes.`)) {
                return;
            }
            
            try {
                const formData = new FormData();
                formData.append('action', 'delete_year');
                formData.append('annee', annee);
                
                const response = await fetch('api/bareme.php', {
                    method: 'POST',
                    body: formData
                });
                
                const data = await response.json();
                
                if (data.status === 'success') {
                    alert(data.message);
                    window.location.href = 'bareme_fiscal.php';
                } else {
                    alert('Erreur: ' + (data.error || 'Erreur inconnue'));
                }
            } catch (error) {
                console.error('Erreur:', error);
                alert('Erreur lors de la suppression');
            }
        }
        
        async function updateBareme(id, champ, valeur) {
            try {
                const formData = new FormData();
                formData.append('action', 'update_bareme');
                formData.append('id', id);
                
                if (champ === 'formule_calcul') {
                    formData.append('formule_calcul', valeur);
                    // Garder les valeurs existantes pour les autres champs
                    const row = document.querySelector(`tr[data-id="${id}"]`);
                    formData.append('cout_fixe', row.querySelector('input[type="number"]').value);
                    formData.append('cout_variable', row.querySelectorAll('input[type="number"]')[1].value);
                } else if (champ === 'cout_fixe') {
                    formData.append('cout_fixe', valeur);
                    const row = document.querySelector(`tr[data-id="${id}"]`);
                    formData.append('cout_variable', row.querySelectorAll('input[type="number"]')[1].value);
                    formData.append('formule_calcul', row.querySelector('input[type="text"]').value);
                } else if (champ === 'cout_variable') {
                    formData.append('cout_variable', valeur);
                    const row = document.querySelector(`tr[data-id="${id}"]`);
                    formData.append('cout_fixe', row.querySelector('input[type="number"]').value);
                    formData.append('formule_calcul', row.querySelector('input[type="text"]').value);
                }
                
                const response = await fetch('api/bareme.php', {
                    method: 'POST',
                    body: formData
                });
                
                const data = await response.json();
                
                if (data.status !== 'success') {
                    alert('Erreur: ' + (data.error || 'Erreur inconnue'));
                }
            } catch (error) {
                console.error('Erreur:', error);
                alert('Erreur lors de la mise à jour');
            }
        }
    </script>
</body>
</html>
