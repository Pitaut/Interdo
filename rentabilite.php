<?php
require_once 'config.php';

// Récupérer la liste des techniciens et véhicules pour les filtres
try {
    $pdo = getDBConnection();
    
    // Techniciens actifs
    $stmtTech = $pdo->query("SELECT id, nom, prenom FROM techniciens WHERE actif = 1 ORDER BY nom, prenom");
    $techniciens = $stmtTech->fetchAll();
    
    // Véhicules actifs
    $stmtVeh = $pdo->query("SELECT id, nom, immatriculation FROM vehicules WHERE actif = 1 ORDER BY nom");
    $vehicules = $stmtVeh->fetchAll();
    
} catch (Exception $e) {
    die('Erreur : ' . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rentabilité - <?php echo APP_NAME; ?></title>
    <link rel="stylesheet" href="includes/common_styles.css">
    <style>
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .stat-value {
            font-size: 32px;
            font-weight: bold;
            margin: 10px 0;
        }
        
        .stat-label {
            color: #666;
            font-size: 14px;
        }
        
        .stat-positive { color: #4caf50; }
        .stat-negative { color: #f44336; }
        .stat-neutral { color: #2196f3; }
        
        .filters {
            background: white;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
            align-items: end;
        }
        
        .filter-group {
            display: flex;
            flex-direction: column;
            gap: 5px;
        }
        
        .filter-group label {
            font-size: 13px;
            color: #666;
        }
        
        .filter-group select,
        .filter-group input {
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
        }
        
        .btn-filter {
            padding: 8px 20px;
            background: #2196f3;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
        }
        
        .btn-filter:hover {
            background: #1976d2;
        }
        
        .interventions-table {
            background: white;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
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
            color: #333;
            border-bottom: 2px solid #ddd;
        }
        
        td {
            padding: 12px;
            border-bottom: 1px solid #eee;
            font-size: 14px;
        }
        
        tr:hover {
            background: #f9f9f9;
        }
        
        .badge {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: 500;
        }
        
        .badge-forfait { background: #e3f2fd; color: #1976d2; }
        .badge-hors-forfait { background: #fff3e0; color: #f57c00; }
        .badge-non-facture { background: #f5f5f5; color: #666; }
        
        .export-btn {
            padding: 8px 16px;
            background: #4caf50;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
            margin-left: auto;
        }
        
        .export-btn:hover {
            background: #45a049;
        }
    </style>
</head>
<body>
    <?php include 'includes/header.php'; ?>
    
    <div class="main-container">
        <h1>📊 Rentabilité des Interventions</h1>
        
        <!-- Filtres -->
        <div class="filters">
            <div class="filter-group">
                <label>Période du</label>
                <input type="date" id="dateDebut" value="<?php echo date('Y-m-01'); ?>">
            </div>
            
            <div class="filter-group">
                <label>au</label>
                <input type="date" id="dateFin" value="<?php echo date('Y-m-d'); ?>">
            </div>
            
            <div class="filter-group">
                <label>Technicien</label>
                <select id="technicienFilter">
                    <option value="">Tous</option>
                    <?php foreach ($techniciens as $tech): ?>
                        <option value="<?php echo $tech['id']; ?>">
                            <?php echo htmlspecialchars($tech['prenom'] . ' ' . $tech['nom']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="filter-group">
                <label>Véhicule</label>
                <select id="vehiculeFilter">
                    <option value="">Tous</option>
                    <?php foreach ($vehicules as $veh): ?>
                        <option value="<?php echo $veh['id']; ?>">
                            <?php echo htmlspecialchars($veh['nom'] . ' - ' . $veh['immatriculation']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <button class="btn-filter" onclick="chargerDonnees()">🔍 Filtrer</button>
            <button class="export-btn" onclick="exporterCSV()">📥 Export CSV</button>
            <button class="btn-filter" onclick="calculerDistances()" style="background: #f57c00;">📍 Calculer distances</button>
            <button class="btn-filter" onclick="recalculerCouts()" style="background: #7b1fa2;">💰 Recalculer coûts</button>
        </div>
        
        <!-- KPIs -->
        <div class="stats-grid" id="kpiContainer">
            <div class="stat-card">
                <div class="stat-label">Nombre d'interventions</div>
                <div class="stat-value stat-neutral" id="kpiNombre">-</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-label">Chiffre d'affaires total</div>
                <div class="stat-value stat-neutral" id="kpiCA">-</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-label">Coûts totaux</div>
                <div class="stat-value stat-negative" id="kpiCouts">-</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-label">Marge brute</div>
                <div class="stat-value" id="kpiMarge">-</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-label">Taux de marge moyen</div>
                <div class="stat-value" id="kpiTauxMarge">-</div>
            </div>
        </div>
        
        <!-- Tableau des interventions -->
        <div class="interventions-table">
            <table id="tableauInterventions">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Client</th>
                        <th>Technicien</th>
                        <th>Véhicule</th>
                        <th>Distance (km)</th>
                        <th>Trajet (min)</th>
                        <th>Durée client (h)</th>
                        <th>Coût tech.</th>
                        <th>Coût véh.</th>
                        <th>Coût total</th>
                        <th>Revenu</th>
                        <th>Marge</th>
                        <th>%</th>
                        <th>Type</th>
                    </tr>
                </thead>
                <tbody id="tableauBody">
                    <tr>
                        <td colspan="14" style="text-align: center; padding: 40px; color: #999;">
                            Sélectionnez une période et cliquez sur "Filtrer"
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
    
    <script>
        let donneesCache = [];
        
        function chargerDonnees() {
            const dateDebut = document.getElementById('dateDebut').value;
            const dateFin = document.getElementById('dateFin').value;
            const technicien = document.getElementById('technicienFilter').value;
            const vehicule = document.getElementById('vehiculeFilter').value;
            
            if (!dateDebut || !dateFin) {
                alert('Veuillez sélectionner une période');
                return;
            }
            
            const params = new URLSearchParams({
                action: 'get_rentabilite',
                date_debut: dateDebut,
                date_fin: dateFin
            });
            
            if (technicien) params.append('technicien_id', technicien);
            if (vehicule) params.append('vehicule_id', vehicule);
            
            fetch('api/rentabilite.php?' + params)
                .then(r => r.json())
                .then(data => {
                    if (data.error) {
                        alert('Erreur : ' + data.error);
                        return;
                    }
                    donneesCache = data.interventions || [];
                    afficherDonnees(data);
                })
                .catch(err => {
                    console.error('Erreur chargement données:', err);
                    alert('Erreur lors du chargement des données');
                });
        }
        
        function afficherDonnees(data) {
            // KPIs
            const kpis = data.kpis || {};
            document.getElementById('kpiNombre').textContent = kpis.nombre_interventions || 0;
            document.getElementById('kpiCA').textContent = formatEuros(kpis.ca_total || 0);
            document.getElementById('kpiCouts').textContent = formatEuros(kpis.couts_totaux || 0);
            
            const marge = (kpis.ca_total || 0) - (kpis.couts_totaux || 0);
            const margeEl = document.getElementById('kpiMarge');
            margeEl.textContent = formatEuros(marge);
            margeEl.className = 'stat-value ' + (marge >= 0 ? 'stat-positive' : 'stat-negative');
            
            const tauxMarge = kpis.taux_marge_moyen || 0;
            const tauxMargeEl = document.getElementById('kpiTauxMarge');
            tauxMargeEl.textContent = tauxMarge.toFixed(1) + '%';
            tauxMargeEl.className = 'stat-value ' + (tauxMarge >= 0 ? 'stat-positive' : 'stat-negative');
            
            // Tableau
            const tbody = document.getElementById('tableauBody');
            tbody.innerHTML = '';
            
            if (!data.interventions || data.interventions.length === 0) {
                tbody.innerHTML = '<tr><td colspan="14" style="text-align: center; padding: 40px; color: #999;">Aucune intervention trouvée pour cette période</td></tr>';
                return;
            }
            
            data.interventions.forEach(inter => {
                const tr = document.createElement('tr');
                
                const badgeClass = inter.type_facturation === 'Forfait' ? 'badge-forfait' :
                                   inter.type_facturation === 'Hors forfait' ? 'badge-hors-forfait' :
                                   'badge-non-facture';
                
                const margeClass = (inter.marge_brute || 0) >= 0 ? 'stat-positive' : 'stat-negative';
                const tauxClass = (inter.taux_marge_pct || 0) >= 0 ? 'stat-positive' : 'stat-negative';
                
                tr.innerHTML = `
                    <td>${formatDate(inter.date_rdv)}</td>
                    <td>${escapeHtml(inter.client_nom || '-')}</td>
                    <td>${escapeHtml(inter.technicien_nom || '-')}</td>
                    <td>${escapeHtml(inter.vehicule_nom || '-')}</td>
                    <td style="text-align: right;">${(inter.distance_km || 0).toFixed(1)}</td>
                    <td style="text-align: right;">${Math.round(inter.temps_trajet_minutes || 0)}</td>
                    <td style="text-align: right;">${(inter.duree_reelle || 0).toFixed(2)}</td>
                    <td style="text-align: right;">${formatEuros(inter.cout_technicien || 0)}</td>
                    <td style="text-align: right;">${formatEuros(inter.cout_vehicule || 0)}</td>
                    <td style="text-align: right; font-weight: 600;">${formatEuros(inter.cout_total || 0)}</td>
                    <td style="text-align: right; font-weight: 600;">${formatEuros(inter.revenu || 0)}</td>
                    <td style="text-align: right; font-weight: 600;" class="${margeClass}">${formatEuros(inter.marge_brute || 0)}</td>
                    <td style="text-align: right; font-weight: 600;" class="${tauxClass}">${(inter.taux_marge_pct || 0).toFixed(1)}%</td>
                    <td><span class="badge ${badgeClass}">${escapeHtml(inter.type_facturation)}</span></td>
                `;
                
                tbody.appendChild(tr);
            });
        }
        
        function formatEuros(montant) {
            return new Intl.NumberFormat('fr-FR', { 
                style: 'currency', 
                currency: 'EUR' 
            }).format(montant);
        }
        
        function formatDate(dateStr) {
            if (!dateStr) return '-';
            const d = new Date(dateStr);
            return d.toLocaleDateString('fr-FR');
        }
        
        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
        
        function exporterCSV() {
            if (donneesCache.length === 0) {
                alert('Aucune donnée à exporter. Veuillez d\'abord filtrer les résultats.');
                return;
            }
            
            // En-têtes CSV
            const headers = ['Date', 'Client', 'Technicien', 'Véhicule', 'Distance (km)', 'Trajet (min)', 'Durée client (h)', 
                           'Coût tech.', 'Coût véh.', 'Coût total', 'Revenu', 'Marge', 'Taux %', 'Type'];
            
            // Lignes de données
            const rows = donneesCache.map(inter => [
                inter.date_rdv,
                inter.client_nom || '-',
                inter.technicien_nom || '-',
                inter.vehicule_nom || '-',
                (inter.distance_km || 0).toFixed(2),
                Math.round(inter.temps_trajet_minutes || 0),
                (inter.duree_reelle || 0).toFixed(2),
                (inter.cout_technicien || 0).toFixed(2),
                (inter.cout_vehicule || 0).toFixed(2),
                (inter.cout_total || 0).toFixed(2),
                (inter.revenu || 0).toFixed(2),
                (inter.marge_brute || 0).toFixed(2),
                (inter.taux_marge_pct || 0).toFixed(2),
                inter.type_facturation
            ]);
            
            // Construire le CSV
            let csv = headers.join(';') + '\n';
            rows.forEach(row => {
                csv += row.map(cell => `"${cell}"`).join(';') + '\n';
            });
            
            // Télécharger
            const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
            const link = document.createElement('a');
            link.href = URL.createObjectURL(blob);
            link.download = `rentabilite_${new Date().toISOString().split('T')[0]}.csv`;
            link.click();
        }
        
        /**
         * Calcule les distances manquantes via l'API OpenRouteService
         */
        async function calculerDistances() {
            if (!confirm('Calculer les distances manquantes pour la période sélectionnée ?\n\nCela peut prendre quelques secondes et consommer des appels API.')) {
                return;
            }
            
            const dateDebut = document.getElementById('dateDebut').value;
            const dateFin = document.getElementById('dateFin').value;
            
            const btn = event.target;
            const originalText = btn.textContent;
            btn.disabled = true;
            btn.textContent = '⏳ Calcul en cours...';
            
            try {
                const params = new URLSearchParams({
                    action: 'calculate_distances',
                    date_debut: dateDebut,
                    date_fin: dateFin
                });
                
                const response = await fetch(`api/rentabilite.php?${params}`);
                const data = await response.json();
                
                if (data.status === 'success') {
                    let message = data.message;
                    
                    // Afficher les détails des erreurs si présents
                    if (data.errors_details && data.errors_details.length > 0) {
                        message += '\n\n⚠️ Détails des erreurs :\n' + data.errors_details.slice(0, 10).join('\n');
                        if (data.errors_details.length > 10) {
                            message += `\n... et ${data.errors_details.length - 10} autres erreurs`;
                        }
                    }
                    
                    alert(message);
                    // Recharger les données pour voir les nouvelles distances
                    chargerDonnees();
                } else {
                    alert('Erreur: ' + (data.error || 'Erreur inconnue'));
                }
            } catch (error) {
                console.error('Erreur calcul distances:', error);
                alert('Erreur lors du calcul des distances');
            } finally {
                btn.disabled = false;
                btn.textContent = originalText;
            }
        }
        
        /**
         * Recalcule les coûts de toutes les interventions terminées
         */
        async function recalculerCouts() {
            if (!confirm('Recalculer les coûts (technicien + véhicule) de toutes les interventions terminées ?')) {
                return;
            }
            
            const btn = event.target;
            const originalText = btn.textContent;
            btn.disabled = true;
            btn.textContent = '⏳ Calcul en cours...';
            
            try {
                const formData = new FormData();
                formData.append('action', 'recalculate_costs');
                
                const response = await fetch('api/interventions.php', {
                    method: 'POST',
                    body: formData
                });
                const data = await response.json();
                
                if (data.status === 'success') {
                    alert(data.message);
                    // Recharger les données pour voir les nouveaux coûts
                    chargerDonnees();
                } else {
                    alert('Erreur: ' + (data.error || 'Erreur inconnue'));
                }
            } catch (error) {
                console.error('Erreur recalcul coûts:', error);
                alert('Erreur lors du recalcul des coûts');
            } finally {
                btn.disabled = false;
                btn.textContent = originalText;
            }
        }
        
        // Charger automatiquement au chargement de la page
        document.addEventListener('DOMContentLoaded', () => {
            chargerDonnees();
        });
    </script>
</body>
</html>
