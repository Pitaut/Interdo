<?php
require_once 'config.php';

$pdo = getDBConnection();

// Si action API pour récupérer les types
if (isset($_GET['action']) && $_GET['action'] === 'get_types') {
    header('Content-Type: application/json');
    $stmt = $pdo->query("SELECT * FROM type_forfait ORDER BY type_forfait ASC");
    $types = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode($types);
    exit;
}

// Créer la table type_forfait si elle n'existe pas avec les bons noms de colonnes
$pdo->exec("CREATE TABLE IF NOT EXISTS type_forfait (
    id INT AUTO_INCREMENT PRIMARY KEY,
    type_forfait VARCHAR(100) NOT NULL,
    prix_forfait DECIMAL(10,2) NOT NULL,
    nbr_heure_forfait DECIMAL(10,2) NOT NULL,
    date_creation TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    actif BOOLEAN DEFAULT TRUE,
    INDEX idx_actif (actif)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

// Charger les types de forfait
$stmt = $pdo->query("SELECT * FROM type_forfait ORDER BY type_forfait ASC");
$types_forfait = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des Forfaits - <?php echo APP_NAME; ?></title>
    <link rel="stylesheet" href="includes/common_styles.css">
    <style>
        body { padding: 0; }
        
        .header-forfaits {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 2px solid #e53935;
        }
        
        .header-forfaits h1 {
            color: #e53935;
            margin: 0;
        }
        
        .btn-icon {
            padding: 6px 12px;
            font-size: 12px;
        }
        
        .modal-header {
            background: #e53935;
        }
        
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #999;
        }
        
        .empty-state-icon {
            font-size: 64px;
            margin-bottom: 20px;
        }
</head>
<body>
    <?php include 'includes/header.php'; ?>
    <div class="container">
        <div class="header-forfaits">
            <h1>📦 Gestion des Types de Forfait</h1>
            <button class="btn btn-success" onclick="openModal()">➕ Nouveau forfait</button>
        </div>
        
        <div class="section">
            <?php if (count($types_forfait) > 0): ?>
            <table>
                <thead>
                    <tr>
                        <th>Type de forfait</th>
                        <th>Nombre d'heures</th>
                        <th>Prix</th>
                        <th>Statut</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($types_forfait as $forfait): ?>
                    <tr>
                        <td><strong><?php echo htmlspecialchars($forfait['type_forfait']); ?></strong></td>
                        <td><?php echo number_format($forfait['nbr_heure_forfait'], 2); ?>h</td>
                        <td><?php echo number_format($forfait['prix_forfait'], 2); ?> €</td>
                        <td>
                            <span class="badge <?php echo $forfait['actif'] ? 'badge-actif' : 'badge-inactif'; ?>">
                                <?php echo $forfait['actif'] ? 'Actif' : 'Inactif'; ?>
                            </span>
                        </td>
                        <td>
                            <div class="actions">
                                <button class="btn btn-warning btn-icon" onclick='editForfait(<?php echo json_encode($forfait); ?>)'>✏️ Modifier</button>
                                <button class="btn btn-info btn-icon" onclick="toggleActif(<?php echo $forfait['id']; ?>, <?php echo $forfait['actif'] ? 'false' : 'true'; ?>)">
                                    <?php echo $forfait['actif'] ? '🚫 Désactiver' : '✅ Activer'; ?>
                                </button>
                                <button class="btn btn-danger btn-icon" onclick="deleteForfait(<?php echo $forfait['id']; ?>, '<?php echo htmlspecialchars($forfait['type_forfait']); ?>')">🗑️ Supprimer</button>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php else: ?>
            <div class="empty-state">
                <div class="empty-state-icon">📦</div>
                <h3>Aucun type de forfait</h3>
                <p>Commencez par créer votre premier type de forfait</p>
            </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Modal Création/Édition -->
    <div id="forfaitModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 id="modalTitle">Nouveau type de forfait</h2>
                <button class="close" onclick="closeModal()">&times;</button>
            </div>
            <div class="modal-body">
                <form id="forfaitForm">
                    <input type="hidden" id="forfaitId" name="id">
                    
                    <div class="form-group">
                        <label for="typeForfait">Type de forfait *</label>
                        <input type="text" id="typeForfait" name="type_forfait" required placeholder="Ex: Forfait 10 heures">
                    </div>
                    
                    <div class="form-group">
                        <label for="nbrHeures">Nombre d'heures *</label>
                        <input type="number" id="nbrHeures" name="nbr_heure_forfait" step="0.5" min="0" required placeholder="Ex: 10">
                    </div>
                    
                    <div class="form-group">
                        <label for="prixForfait">Prix (€) *</label>
                        <input type="number" id="prixForfait" name="prix_forfait" step="0.01" min="0" required placeholder="Ex: 500">
                    </div>
                    
                    <div class="form-group">
                        <label for="actif">Statut</label>
                        <select id="actif" name="actif">
                            <option value="1">Actif</option>
                            <option value="0">Inactif</option>
                        </select>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" onclick="closeModal()">Annuler</button>
                <button class="btn btn-success" onclick="saveForfait()">💾 Enregistrer</button>
            </div>
        </div>
    </div>
    
    <script>
        let currentForfaitId = null;
        
        function openModal(forfait = null) {
            const modal = document.getElementById('forfaitModal');
            const title = document.getElementById('modalTitle');
            const form = document.getElementById('forfaitForm');
            
            form.reset();
            
            if (forfait) {
                title.textContent = 'Modifier le type de forfait';
                document.getElementById('forfaitId').value = forfait.id;
                document.getElementById('typeForfait').value = forfait.type_forfait;
                document.getElementById('nbrHeures').value = forfait.nbr_heure_forfait;
                document.getElementById('prixForfait').value = forfait.prix_forfait;
                document.getElementById('actif').value = forfait.actif ? '1' : '0';
                currentForfaitId = forfait.id;
            } else {
                title.textContent = 'Nouveau type de forfait';
                currentForfaitId = null;
            }
            
            modal.style.display = 'block';
        }
        
        function closeModal() {
            document.getElementById('forfaitModal').style.display = 'none';
            currentForfaitId = null;
        }
        
        function editForfait(forfait) {
            openModal(forfait);
        }
        
        function saveForfait() {
            const form = document.getElementById('forfaitForm');
            const formData = new FormData(form);
            const data = {};
            
            formData.forEach((value, key) => {
                if (key === 'actif') {
                    data[key] = value === '1';
                } else if (key === 'nbr_heure_forfait' || key === 'prix_forfait') {
                    data[key] = parseFloat(value);
                } else if (key === 'id' && value) {
                    data[key] = parseInt(value);
                } else {
                    data[key] = value;
                }
            });
            
            const url = currentForfaitId ? 'api/forfaits.php?action=update_type' : 'api/forfaits.php?action=create_type';
            
            fetch(url, {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify(data)
            })
            .then(resp => resp.json())
            .then(json => {
                if (json.error) {
                    alert('Erreur: ' + json.error);
                } else {
                    alert(currentForfaitId ? 'Forfait modifié avec succès' : 'Forfait créé avec succès');
                    location.reload();
                }
            })
            .catch(err => {
                console.error('Erreur:', err);
                alert('Erreur réseau lors de la sauvegarde');
            });
        }
        
        function toggleActif(id, actif) {
            fetch('api/forfaits.php?action=toggle_type', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({ id: id, actif: actif })
            })
            .then(resp => resp.json())
            .then(json => {
                if (json.error) {
                    alert('Erreur: ' + json.error);
                } else {
                    location.reload();
                }
            })
            .catch(err => {
                console.error('Erreur:', err);
                alert('Erreur réseau');
            });
        }
        
        function deleteForfait(id, nom) {
            if (!confirm(`Confirmer la suppression du forfait "${nom}" ?\n\nCette action est irréversible.`)) {
                return;
            }
            
            fetch('api/forfaits.php?action=delete_type', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({ id: id })
            })
            .then(resp => resp.json())
            .then(json => {
                if (json.error) {
                    alert('Erreur: ' + json.error);
                } else {
                    alert('Forfait supprimé avec succès');
                    location.reload();
                }
            })
            .catch(err => {
                console.error('Erreur:', err);
                alert('Erreur réseau lors de la suppression');
            });
        }
        
        // Fermer la modale en cliquant en dehors
        window.onclick = function(event) {
            const modal = document.getElementById('forfaitModal');
            if (event.target == modal) {
                closeModal();
            }
        }
        
        // Fermer avec Échap
        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape') {
                closeModal();
            }
        });
    </script>
</body>
</html>
