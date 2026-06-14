# Script de migration et test du système de forfaits
# Exécuter dans PowerShell depuis le répertoire du projet

Write-Host "=== Migration et test du système de forfaits ===" -ForegroundColor Cyan

# 1. Appliquer la migration heure_bonus
Write-Host "`n1. Application de la migration heure_bonus..." -ForegroundColor Yellow
$migrationSQL = Get-Content "migrations\002_add_heure_bonus_to_clients.sql" -Raw
# Note: Cette commande nécessite l'accès MySQL - à adapter selon votre configuration
# mysql -u root agenda_interventions < migrations\002_add_heure_bonus_to_clients.sql

# 2. Créer quelques types de forfaits
Write-Host "`n2. Création des types de forfaits..." -ForegroundColor Yellow

$forfaits = @(
    @{ nom="Forfait Découverte 5h"; nombre_heures=5; prix=250; description="Forfait découverte de 5 heures" },
    @{ nom="Forfait Standard 10h"; nombre_heures=10; prix=480; description="Forfait standard de 10 heures" },
    @{ nom="Forfait Premium 20h"; nombre_heures=20; prix=920; description="Forfait premium de 20 heures" }
)

foreach ($f in $forfaits) {
    try {
        $response = Invoke-RestMethod -Uri 'http://localhost/_Interdo/add_type_forfait.php' `
            -Method Post `
            -Body ($f | ConvertTo-Json) `
            -ContentType 'application/json'
        Write-Host "  ✓ Forfait créé: $($response.nom) - $($response.nombre_heures)h à $($response.prix)€" -ForegroundColor Green
    } catch {
        Write-Host "  ✗ Erreur: $_" -ForegroundColor Red
    }
}

# 3. Vendre un forfait à un client (ID client 1 par exemple)
Write-Host "`n3. Vente d'un forfait au client ID 1..." -ForegroundColor Yellow

$vente = @{
    client_id = 1
    type_forfait_id = 2  # Forfait Standard 10h
    date_debut = (Get-Date -Format "yyyy-MM-dd")
}

try {
    $response = Invoke-RestMethod -Uri 'http://localhost/_Interdo/vendre_forfait.php' `
        -Method Post `
        -Body ($vente | ConvertTo-Json) `
        -ContentType 'application/json'
    Write-Host "  ✓ Forfait vendu: $($response.heures_total)h restantes" -ForegroundColor Green
    $forfait_vendu_id = $response.id
} catch {
    Write-Host "  ✗ Erreur: $_" -ForegroundColor Red
}

# 4. Charger les forfaits du client
Write-Host "`n4. Vérification des forfaits du client 1..." -ForegroundColor Yellow

try {
    $response = Invoke-RestMethod -Uri 'http://localhost/_Interdo/load_forfaits.php?client_id=1' `
        -Method Get
    Write-Host "  ✓ Total heures restantes: $($response.total_heures_restantes)h" -ForegroundColor Green
    Write-Host "  ✓ Bonus client: $($response.heure_bonus_minutes) minutes" -ForegroundColor Green
} catch {
    Write-Host "  ✗ Erreur: $_" -ForegroundColor Red
}

# 5. Exemple de clôture d'intervention (nécessite un rendez-vous existant)
Write-Host "`n5. Instructions pour tester la clôture:" -ForegroundColor Yellow
Write-Host "  - Créer un rendez-vous avec le client ID 1 dans l'agenda" -ForegroundColor Cyan
Write-Host "  - Noter l'ID du rendez-vous créé (par ex: 10)" -ForegroundColor Cyan
Write-Host "  - Passer le statut à 'Terminé' pour déclencher la clôture automatique" -ForegroundColor Cyan
Write-Host "  - Ou utiliser cette commande manuellement:" -ForegroundColor Cyan
Write-Host '    Invoke-RestMethod -Uri "http://localhost/_Interdo/close_intervention.php" -Method Post -Body (@{rendez_vous_id=10} | ConvertTo-Json) -ContentType "application/json" | ConvertTo-Json -Depth 5' -ForegroundColor Gray

Write-Host "`n=== Migration terminée ===" -ForegroundColor Green
Write-Host "`nPour utiliser le système:" -ForegroundColor Yellow
Write-Host "1. Ouvrez l'agenda: http://localhost/_Interdo/agenda.php" -ForegroundColor Cyan
Write-Host "2. Créez un rendez-vous et associez-le à un client" -ForegroundColor Cyan
Write-Host "3. Les infos de forfait s'afficheront dans 'Infos contrat'" -ForegroundColor Cyan
Write-Host "4. Passez le statut à 'Terminé' pour décompter automatiquement les heures" -ForegroundColor Cyan
