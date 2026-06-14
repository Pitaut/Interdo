# Script de vérification des distances calculées
# Usage: .\verifier_distances.ps1

Write-Host "`n=== État du calcul des distances ===" -ForegroundColor Cyan

# Se placer dans le bon répertoire
Set-Location "C:\wamp64\bin\mysql\mysql9.1.0\bin"

# Interventions terminées
$total = (.\mysql -u root agenda_db -e "SELECT COUNT(*) FROM rendez_vous WHERE statut = 'termine';" -N)
Write-Host "Total interventions terminées: $total" -ForegroundColor White

# Avec distance
$avec = (.\mysql -u root agenda_db -e "SELECT COUNT(*) FROM rendez_vous WHERE distance_km > 0 AND statut = 'termine';" -N)
Write-Host "Avec distance calculée: $avec" -ForegroundColor Green

# Sans distance
$sans = (.\mysql -u root agenda_db -e "SELECT COUNT(*) FROM rendez_vous WHERE (distance_km IS NULL OR distance_km = 0) AND statut = 'termine';" -N)
Write-Host "Sans distance: $sans" -ForegroundColor $(if ($sans -eq 0) { 'Green' } else { 'Yellow' })

# Pourcentage
$pct = [math]::Round(($avec / $total) * 100, 1)
Write-Host "`nTaux de complétion: $pct%" -ForegroundColor $(if ($pct -eq 100) { 'Green' } else { 'Yellow' })

if ($sans -gt 0) {
    Write-Host "`n=== Interventions sans distance (échantillon) ===" -ForegroundColor Yellow
    .\mysql -u root agenda_db -e "SELECT r.id, r.date_rdv, r.titre, c.nom AS client, t.nom AS tech FROM rendez_vous r LEFT JOIN clients c ON r.client_id = c.id LEFT JOIN techniciens t ON r.id_technicien = t.id WHERE (r.distance_km IS NULL OR r.distance_km = 0) AND r.statut = 'termine' ORDER BY r.date_rdv DESC LIMIT 5;"
}

Write-Host "`n=== Dernières distances calculées ===" -ForegroundColor Green
.\mysql -u root agenda_db -e "SELECT r.id, r.date_rdv, r.titre, r.distance_km, r.temps_trajet_minutes FROM rendez_vous r WHERE r.distance_km > 0 AND r.statut = 'termine' ORDER BY r.id DESC LIMIT 5;"

Write-Host "`n"
