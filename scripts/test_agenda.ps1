Invoke-RestMethod -Uri 'http://localhost/_Interdo/add_client.php' -Method Post -Body (@{ prenom='Jean'; nom='Dupont'; tel_mobile='0606060606' } | ConvertTo-Json) -ContentType 'application/json' | ConvertTo-Json -Depth 5# Test automatisé des endpoints agenda/techniciens
# Exécuter depuis la machine qui sert l'app (WAMP).
# Vérifie : récupération events, détails d'un event, liste techniciens et champs attendus.

$base = 'http://localhost/_Interdo'
$start = (Get-Date).ToString('yyyy-MM-dd')
$end = (Get-Date).AddDays(30).ToString('yyyy-MM-dd')
$eventsUrl = "$base/agenda.php?action=get_events&start=$start&end=$end"

Write-Output "-> Récupération événements $eventsUrl"
try {
    $events = Invoke-RestMethod -Uri $eventsUrl -Method Get -ErrorAction Stop
} catch {
    Write-Error "Erreur HTTP lors de la récupération des événements : $_"
    exit 2
}

if (-not $events) {
    Write-Output "Aucun événement retourné (liste vide). Le test continue mais aucune vérification d'événement individuel ne sera faite.";
} else {
    $first = $events[0]
    Write-Output "-> Premier événement trouvé id=$($first.id) title='$($first.title)'"
    $detailsUrl = "$base/agenda.php?action=get_event_details&id=$($first.id)"
    try {
        $details = Invoke-RestMethod -Uri $detailsUrl -Method Get -ErrorAction Stop
    } catch {
        Write-Error "Erreur HTTP lors de la récupération des détails: $_"
        exit 3
    }
    # Vérifications simples
    $required = @('id','titre','date_rdv','heure_debut','heure_fin')
    $missing = @()
    foreach ($r in $required) { if (-not ($details.PSObject.Properties.Name -contains $r)) { $missing += $r } }
    if ($missing.Count -gt 0) {
        Write-Error "Détails manquants: $($missing -join ', ')"
        exit 4
    } else {
        Write-Output "Détails OK (champs présents)."
    }
}

# Charger techniciens
$techUrl = "$base/load_techniciens.php"
Write-Output "-> Récupération techniciens $techUrl"
try {
    $techs = Invoke-RestMethod -Uri $techUrl -Method Get -ErrorAction Stop
} catch {
    Write-Error "Erreur HTTP lors de la récupération des techniciens: $_"
    exit 5
}

if (-not $techs -or $techs.Count -eq 0) {
    Write-Output "Aucun technicien trouvé (liste vide). C'est valide, mais vérifiez les données si vous attendiez des enregistrements."
} else {
    # Vérifier prénom/nom
    $bad = $techs | Where-Object { -not ($_.prenom) -or -not ($_.nom) }
    if ($bad.Count -gt 0) {
        Write-Error "Certains techniciens n'ont pas de prénom/nom. Exemple id(s): $($bad | Select-Object -ExpandProperty id -First 5 -Join ', ')"
        exit 6
    } else {
        Write-Output "Techniciens OK (prénom + nom présents pour les enregistrements)."
    }
}

Write-Output "TESTS BASIQUES OK"
exit 0
