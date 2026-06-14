# Script de test complet de l'API interventions.php
# Teste plusieurs scenarii : durée exacte, avec arrondi, sans arrondi

Write-Host "`n═══════════════════════════════════════════════" -ForegroundColor Yellow
Write-Host "TEST API INTERVENTIONS - Détection multiples de 30min" -ForegroundColor Yellow
Write-Host "═══════════════════════════════════════════════`n" -ForegroundColor Yellow

# Test 1: Durée exacte de 1h (60 minutes = multiple de 30)
Write-Host "TEST 1: Durée 60min (devrait être exacte)" -ForegroundColor Cyan
$body1 = @{
    rendez_vous_id = 90
    heure_debut = '2026-02-10T13:00:00'
    heure_fin = '2026-02-10T14:00:00'
    appliquer_arrondi = $true
} | ConvertTo-Json

try {
    $response1 = Invoke-RestMethod -Uri "http://localhost/_Interdo/api/interventions.php?action=check_heures" `
        -Method Post `
        -Body $body1 `
        -ContentType "application/json" `
        -Headers @{
            "Cache-Control" = "no-cache"
            "Pragma" = "no-cache"
        }
    
    Write-Host "Durée nécessaire: $($response1.heures_necessaires)h" -ForegroundColor White
    
    if ($response1.PSObject.Properties['arrondi_necessaire']) {
        if ($response1.arrondi_necessaire) {
            Write-Host "❌ ERREUR: Arrondi nécessaire = TRUE (devrait être FALSE)" -ForegroundColor Red
        } else {
            Write-Host "✅ OK: Arrondi nécessaire = FALSE" -ForegroundColor Green
        }
        
        if ($response1.duree_exacte) {
            Write-Host "✅ OK: Durée exacte = TRUE" -ForegroundColor Green
        }
    } else {
        Write-Host "⚠️  ATTENTION: Champs 'arrondi_necessaire' et 'duree_exacte' absents" -ForegroundColor Yellow
        Write-Host "   Le cache opcache n'est pas encore vidé ou le code n'est pas chargé" -ForegroundColor Yellow
    }
    
    Write-Host "`nRéponse complète:" -ForegroundColor Gray
    $response1 | ConvertTo-Json -Depth 3
    
} catch {
    Write-Host "❌ Erreur: $_" -ForegroundColor Red
}

# Test 2: Durée inexacte (55 minutes, pas multiple de 30)
Write-Host "`n`nTEST 2: Durée 55min (PAS multiple de 30)" -ForegroundColor Cyan
$body2 = @{
    rendez_vous_id = 90
    heure_debut = '2026-02-10T13:00:00'
    heure_fin = '2026-02-10T13:55:00'
    appliquer_arrondi = $true
} | ConvertTo-Json

try {
    $response2 = Invoke-RestMethod -Uri "http://localhost/_Interdo/api/interventions.php?action=check_heures?ts=$(Get-Date -Format 'yyyyMMddHHmmss')" `
        -Method Post `
        -Body $body2 `
        -ContentType "application/json" `
        -Headers @{
            "Cache-Control" = "no-cache"
        }
    
    Write-Host "Durée nécessaire: $($response2.heures_necessaires)h" -ForegroundColor White
    
    if ($response2.PSObject.Properties['arrondi_necessaire']) {
        if ($response2.arrondi_necessaire) {
            Write-Host "✅ OK: Arrondi nécessaire = TRUE" -ForegroundColor Green
        } else {
            Write-Host "❌ ERREUR: Arrondi nécessaire = FALSE (devrait être TRUE)" -ForegroundColor Red
        }
    }
    
    Write-Host "`nRéponse complète:" -ForegroundColor Gray
    $response2 | ConvertTo-Json -Depth 3
    
} catch {
    Write-Host "❌ Erreur: $_" -ForegroundColor Red
}

Write-Host "`n`n═══════════════════════════════════════════════" -ForegroundColor Yellow
Write-Host "FIN DES TESTS" -ForegroundColor Yellow
Write-Host "═══════════════════════════════════════════════`n" -ForegroundColor Yellow
