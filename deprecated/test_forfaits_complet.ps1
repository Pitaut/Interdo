# Script de test complet du systeme de forfaits
Write-Host "=== TEST COMPLET SYSTEME FORFAITS ===" -ForegroundColor Cyan
Write-Host ""

$baseUrl = "http://localhost/_Interdo"

# Etape 1 : Creer un type de forfait
Write-Host "1. Creation d''un type de forfait..." -ForegroundColor Yellow
$typeForfait = Invoke-RestMethod -Uri "$baseUrl/add_type_forfait.php" -Method Post -Body (@{ nom = "Forfait Test 10h"; nombre_heures = 10; prix = 500; description = "Test"; actif = $true } | ConvertTo-Json) -ContentType 'application/json'
Write-Host "OK Type forfait cree - ID: $($typeForfait.id)" -ForegroundColor Green
$typeForfaitId = $typeForfait.id
Write-Host ""

# Etape 2 : Vendre le forfait
Write-Host "2. Vente du forfait au client..." -ForegroundColor Yellow
$venteResp = Invoke-RestMethod -Uri "$baseUrl/vendre_forfait.php" -Method Post -Body (@{ client_id = 1; type_forfait_id = $typeForfaitId; date_debut = (Get-Date -Format "yyyy-MM-dd"); date_fin = (Get-Date).AddMonths(6).ToString("yyyy-MM-dd") } | ConvertTo-Json) -ContentType 'application/json'
Write-Host "OK Forfait vendu - ID: $($venteResp.id)" -ForegroundColor Green
Write-Host "  Heures totales: $($venteResp.heures_total)h" -ForegroundColor Gray
$forfaitVenduId = $venteResp.id
Write-Host ""

# Etape 3 : Creer un rendez-vous
Write-Host "3. Creation d''un rendez-vous..." -ForegroundColor Yellow
$dateRdv = Get-Date -Format "yyyy-MM-dd"
$rdvResp = Invoke-RestMethod -Uri "$baseUrl/add_event.php" -Method Post -Body (@{ title = "Test Forfait"; start = "${dateRdv}T09:00:00"; end = "${dateRdv}T10:15:00"; client_id = 1; statut = "planifie" } | ConvertTo-Json) -ContentType 'application/json'
Write-Host "OK Rendez-vous cree - ID: $($rdvResp.id)" -ForegroundColor Green
Write-Host "  Duree: 1h15 reel -> arrondi a 1h30" -ForegroundColor Gray
$rdvId = $rdvResp.id
Write-Host ""

# Etape 4 : Verifier forfait avant
Write-Host "4. Forfait avant cloture..." -ForegroundColor Yellow
$forfaitAvant = Invoke-RestMethod -Uri "$baseUrl/load_forfaits.php?client_id=1" -Method Get
Write-Host "  Heures restantes: $($forfaitAvant.total_heures_restantes)h" -ForegroundColor Gray
Write-Host ""

# Etape 5 : Cloturer
Write-Host "5. Cloture de l''intervention..." -ForegroundColor Yellow
$clotureResp = Invoke-RestMethod -Uri "$baseUrl/close_intervention.php" -Method Post -Body (@{ rendez_vous_id = $rdvId } | ConvertTo-Json) -ContentType 'application/json'
Write-Host "OK Intervention cloturee!" -ForegroundColor Green
Write-Host ""
Write-Host "  DETAILS:" -ForegroundColor Cyan
Write-Host "  Temps reel: $($clotureResp.temps_reel)h" -ForegroundColor White
Write-Host "  Temps arrondi: $($clotureResp.temps_arrondi)h" -ForegroundColor White
Write-Host "  Heures avant: $($clotureResp.heures_avant)h" -ForegroundColor White
Write-Host "  Heures apres: $($clotureResp.heures_apres)h" -ForegroundColor White
Write-Host "  Bonus client: $($clotureResp.heure_bonus_client)h" -ForegroundColor White
Write-Host ""

# Etape 6 : Verifier apres
Write-Host "6. Forfait apres cloture..." -ForegroundColor Yellow
$forfaitApres = Invoke-RestMethod -Uri "$baseUrl/load_forfaits.php?client_id=1" -Method Get
Write-Host "  Heures restantes: $($forfaitApres.total_heures_restantes)h" -ForegroundColor Gray
Write-Host "  Bonus total: $($forfaitApres.heure_bonus_minutes) minutes" -ForegroundColor Gray
Write-Host ""
Write-Host "=== TEST TERMINE AVEC SUCCES ===" -ForegroundColor Green
