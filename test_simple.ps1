# Test simple pour WAMP
$baseUrl = "http://localhost/_Interdo"

Write-Host "`nVidage du cache opcache..." -ForegroundColor Cyan
try {
    Invoke-WebRequest -Uri "$baseUrl/clear_opcache.php" -UseBasicParsing | Out-Null
    Write-Host "Cache vide!" -ForegroundColor Green
} catch {
    Write-Host "Impossible de vider le cache - Continuons quand meme" -ForegroundColor Yellow
}

Write-Host "`n=== TEST: Duree exacte 60 minutes ===" -ForegroundColor Cyan

$body = @{
    rendez_vous_id = 90
    heure_debut = "2026-02-10T09:00:00"
    heure_fin = "2026-02-10T10:00:00"
    appliquer_arrondi = $true
} | ConvertTo-Json

Write-Host "Duree testee: 09:00 -> 10:00 (60 minutes)" -ForegroundColor White

$response = Invoke-RestMethod `
    -Uri "$baseUrl/api/interventions.php?action=check_heures" `
    -Method Post `
    -Body $body `
    -ContentType "application/json"

Write-Host "`nReponse:" -ForegroundColor Gray
$response | Format-List

if ($response.PSObject.Properties.Name -contains 'duree_exacte') {
    if ($response.duree_exacte) {
        Write-Host "`nSUCCES: Duree detectee comme exacte (multiple de 30min)" -ForegroundColor Green
    } else {
        Write-Host "`nERREUR: Duree devrait etre detectee comme exacte" -ForegroundColor Red
    }
} else {
    Write-Host "`nATTENTION: Champs 'duree_exacte' absent" -ForegroundColor Yellow
    Write-Host "SOLUTION: Redemarrez WAMP pour vider le cache opcache" -ForegroundColor Yellow
}

Write-Host "`n=== TEST 2: Duree inexacte 55 minutes ===" -ForegroundColor Cyan

$body2 = @{
    rendez_vous_id = 90
    heure_debut = "2026-02-10T09:00:00"
    heure_fin = "2026-02-10T09:55:00"
    appliquer_arrondi = $true
} | ConvertTo-Json

Write-Host "Duree testee: 09:00 -> 09:55 (55 minutes)" -ForegroundColor White

$response2 = Invoke-RestMethod `
    -Uri "$baseUrl/api/interventions.php?action=check_heures" `
    -Method Post `
    -Body $body2 `
    -ContentType "application/json"

Write-Host "`nReponse:" -ForegroundColor Gray
$response2 | Format-List

if ($response2.PSObject.Properties.Name -contains 'arrondi_necessaire') {
    if ($response2.arrondi_necessaire) {
        Write-Host "`nSUCCES: Arrondi detecte comme necessaire" -ForegroundColor Green
    } else {
        Write-Host "`nERREUR: Arrondi devrait etre detecte comme necessaire" -ForegroundColor Red
    }
}

Write-Host "`n===================================`n" -ForegroundColor Yellow
