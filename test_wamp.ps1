# Script de test adapté pour WAMP avec gestion du cache opcache
# Usage: .\test_wamp.ps1

param(
    [switch]$ClearCache = $true
)

$baseUrl = "http://localhost/_Interdo"
$ErrorActionPreference = "Continue"

Write-Host "`n════════════════════════════════════════════════════════" -ForegroundColor Yellow
Write-Host "  TEST API INTERVENTIONS - Version WAMP-friendly" -ForegroundColor Yellow
Write-Host "════════════════════════════════════════════════════════`n" -ForegroundColor Yellow

# Étape 1: Vider le cache opcache si demandé
if ($ClearCache) {
    Write-Host "Étape 1: Vidage du cache opcache..." -ForegroundColor Cyan
    try {
        $cacheResult = Invoke-RestMethod -Uri "$baseUrl/clear_opcache.php" -Method Get -TimeoutSec 5
        if ($cacheResult.cache_reset) {
            Write-Host "✅ Cache opcache vidé avec succès" -ForegroundColor Green
        } else {
            Write-Host "⚠️  opcache non disponible ou déjà vidé" -ForegroundColor Yellow
        }
        Start-Sleep -Seconds 1
    } catch {
        Write-Host "⚠️  Impossible de vider le cache: $_" -ForegroundColor Yellow
    }
    Write-Host ""
}

# Étape 2: Récupérer un rendez-vous de test
Write-Host "Étape 2: Récupération d'un rendez-vous de test..." -ForegroundColor Cyan
try {
    $phpResult = php test_interventions.php 2>&1 | Out-String
    
    # Extraire l'ID du rendez-vous
    if ($phpResult -match 'ID:\s+(\d+)') {
        $rdvId = $Matches[1]
        Write-Host "✅ Rendez-vous trouvé: ID $rdvId" -ForegroundColor Green
        
        # Extraire la date
        if ($phpResult -match 'Date:\s+([\d-]+)') {
            $rdvDate = $Matches[1]
        }
        
        # Extraire les heures
        if ($phpResult -match 'Heures:\s+([\d:]+)\s+→\s+([\d:]+)') {
            $heureDebut = $Matches[1]
            $heureFin = $Matches[2]
        }
    } else {
        Write-Host "❌ Aucun rendez-vous disponible" -ForegroundColor Red
        exit 1
    }
} catch {
    Write-Host "❌ Erreur lors de la récupération du rendez-vous: $_" -ForegroundColor Red
    exit 1
}

Write-Host ""

# Étape 3: TEST 1 - Durée exacte (60 minutes)
Write-Host "════════════════════════════════════════════════════════" -ForegroundColor Yellow
Write-Host "TEST 1: Durée exacte 60 minutes (multiple de 30)" -ForegroundColor Cyan
Write-Host "════════════════════════════════════════════════════════" -ForegroundColor Yellow

$body1 = @{
    rendez_vous_id = [int]$rdvId
    heure_debut = "${rdvDate}T09:00:00"
    heure_fin = "${rdvDate}T10:00:00"
    appliquer_arrondi = $true
} | ConvertTo-Json

Write-Host "`nRequête:" -ForegroundColor Gray
Write-Host "  Durée: 09:00:00 → 10:00:00 (60 minutes)" -ForegroundColor White

try {
    $response1 = Invoke-RestMethod `
        -Uri "$baseUrl/api/interventions.php?action=check_heures&nocache=$(Get-Date -Format 'yyyyMMddHHmmss')" `
        -Method Post `
        -Body $body1 `
        -ContentType "application/json" `
        -Headers @{ "Cache-Control" = "no-cache" }
    
    Write-Host "`nRéponse:" -ForegroundColor Gray
    Write-Host "  Heures nécessaires: $($response1.heures_necessaires)h" -ForegroundColor White
    Write-Host "  Heures restantes: $($response1.heures_restantes)h" -ForegroundColor White
    
    # Vérifier la présence des nouveaux champs
    if ($response1.PSObject.Properties.Name -contains 'arrondi_necessaire') {
        Write-Host "`n✅ Nouveaux champs présents!" -ForegroundColor Green
        Write-Host "  - arrondi_necessaire: $($response1.arrondi_necessaire)" -ForegroundColor White
        Write-Host "  - duree_exacte: $($response1.duree_exacte)" -ForegroundColor White
        
        if ($response1.duree_exacte -eq $true) {
            Write-Host "`n✅ SUCCÈS: Durée détectée comme exacte (multiple de 30min)" -ForegroundColor Green
        } else {
            Write-Host "`n❌ ÉCHEC: Durée devrait être détectée comme exacte" -ForegroundColor Red
        }
    } else {
        Write-Host "`n❌ PROBLÈME: Nouveaux champs absents (cache opcache non vidé)" -ForegroundColor Red
        Write-Host "   Solution: Redémarrez WAMP manuellement" -ForegroundColor Yellow
    }
    
} catch {
    Write-Host "`n❌ Erreur lors du test 1: $_" -ForegroundColor Red
}

# Étape 4: TEST 2 - Durée inexacte (55 minutes)
Write-Host "`n════════════════════════════════════════════════════════" -ForegroundColor Yellow
Write-Host "TEST 2: Durée inexacte 55 minutes (PAS multiple de 30)" -ForegroundColor Cyan
Write-Host "════════════════════════════════════════════════════════" -ForegroundColor Yellow

$body2 = @{
    rendez_vous_id = [int]$rdvId
    heure_debut = "${rdvDate}T09:00:00"
    heure_fin = "${rdvDate}T09:55:00"
    appliquer_arrondi = $true
} | ConvertTo-Json

Write-Host "`nRequête:" -ForegroundColor Gray
Write-Host "  Durée: 09:00:00 → 09:55:00 (55 minutes)" -ForegroundColor White

try {
    $response2 = Invoke-RestMethod `
        -Uri "$baseUrl/api/interventions.php?action=check_heures&nocache=$(Get-Date -Format 'yyyyMMddHHmmss')" `
        -Method Post `
        -Body $body2 `
        -ContentType "application/json" `
        -Headers @{ "Cache-Control" = "no-cache" }
    
    Write-Host "`nRéponse:" -ForegroundColor Gray
    Write-Host "  Heures nécessaires: $($response2.heures_necessaires)h" -ForegroundColor White
    Write-Host "  Heures restantes: $($response2.heures_restantes)h" -ForegroundColor White
    
    if ($response2.PSObject.Properties.Name -contains 'arrondi_necessaire') {
        Write-Host "`n✅ Nouveaux champs présents!" -ForegroundColor Green
        Write-Host "  - arrondi_necessaire: $($response2.arrondi_necessaire)" -ForegroundColor White
        Write-Host "  - duree_exacte: $($response2.duree_exacte)" -ForegroundColor White
        
        if ($response2.arrondi_necessaire -eq $true) {
            Write-Host "`n✅ SUCCÈS: Arrondi détecté comme nécessaire" -ForegroundColor Green
        } else {
            Write-Host "`n❌ ÉCHEC: Arrondi devrait être détecté comme nécessaire" -ForegroundColor Red
        }
    }
    
} catch {
    Write-Host "`n❌ Erreur lors du test 2: $_" -ForegroundColor Red
}

# Étape 5: TEST 3 - Durée exacte (30 minutes)
Write-Host "`n════════════════════════════════════════════════════════" -ForegroundColor Yellow
Write-Host "TEST 3: Durée exacte 30 minutes (1 tranche)" -ForegroundColor Cyan
Write-Host "════════════════════════════════════════════════════════" -ForegroundColor Yellow

$body3 = @{
    rendez_vous_id = [int]$rdvId
    heure_debut = "${rdvDate}T09:00:00"
    heure_fin = "${rdvDate}T09:30:00"
    appliquer_arrondi = $true
} | ConvertTo-Json

Write-Host "`nRequête:" -ForegroundColor Gray
Write-Host "  Durée: 09:00:00 → 09:30:00 (30 minutes)" -ForegroundColor White

try {
    $response3 = Invoke-RestMethod `
        -Uri "$baseUrl/api/interventions.php?action=check_heures&nocache=$(Get-Date -Format 'yyyyMMddHHmmss')" `
        -Method Post `
        -Body $body3 `
        -ContentType "application/json"
    
    Write-Host "`nRéponse:" -ForegroundColor Gray
    Write-Host "  Heures nécessaires: $($response3.heures_necessaires)h" -ForegroundColor White
    
    if ($response3.PSObject.Properties.Name -contains 'duree_exacte') {
        if ($response3.duree_exacte -eq $true) {
            Write-Host "`n✅ SUCCÈS: Durée 30min détectée comme exacte" -ForegroundColor Green
        }
    }
    
} catch {
    Write-Host "`n❌ Erreur lors du test 3: $_" -ForegroundColor Red
}

Write-Host "`n════════════════════════════════════════════════════════" -ForegroundColor Yellow
Write-Host "FIN DES TESTS" -ForegroundColor Yellow
Write-Host "════════════════════════════════════════════════════════`n" -ForegroundColor Yellow

# Résumé
Write-Host "RÉSUMÉ:" -ForegroundColor Cyan
Write-Host "  - Si les champs 'arrondi_necessaire' et 'duree_exacte' sont absents," -ForegroundColor White
Write-Host "    vous devez redémarrer WAMP pour vider le cache opcache." -ForegroundColor White
Write-Host "  - Une fois redémarré, relancez ce script avec: .\test_wamp.ps1`n" -ForegroundColor White
