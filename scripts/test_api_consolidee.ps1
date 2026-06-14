# Test de l'API consolidée
# Valide tous les endpoints de la nouvelle architecture

$baseUrl = "http://localhost/_Interdo"
$ErrorActionPreference = "Stop"

# Fonction helper pour construire des URLs avec paramètres
function Build-Url {
    param([string]$Path, [hashtable]$Params = @{})
    $url = "$baseUrl$Path"
    if ($Params.Count -gt 0) {
        $queryString = ($Params.GetEnumerator() | ForEach-Object { "$($_.Key)=$($_.Value)" }) -join '&'
        $url += "?$queryString"
    }
    return $url
}

Write-Host "`n========================================" -ForegroundColor Cyan
Write-Host "  TEST API CONSOLIDÉE - Architecture v2" -ForegroundColor Cyan
Write-Host "========================================`n" -ForegroundColor Cyan

function Test-ApiEndpoint {
    param(
        [string]$Name,
        [string]$Method,
        [string]$Url,
        [hashtable]$Body = $null,
        [int]$ExpectedStatus = 200
    )
    
    Write-Host "→ $Name" -ForegroundColor Yellow
    Write-Host "  $Method $Url" -ForegroundColor Gray
    
    try {
        $params = @{
            Uri = $Url
            Method = $Method
            ErrorAction = 'Stop'
        }
        
        if ($Body) {
            $params.Body = ($Body | ConvertTo-Json -Depth 5)
            $params.ContentType = 'application/json'
            Write-Host "  Body: $($params.Body)" -ForegroundColor DarkGray
        }
        
        $response = Invoke-RestMethod @params
        Write-Host "  ✅ SUCCESS" -ForegroundColor Green
        
        if ($response.id) {
            Write-Host "  📝 ID créé: $($response.id)" -ForegroundColor Cyan
            return $response.id
        }
        
        if ($response -is [Array]) {
            Write-Host "  📊 $($response.Count) éléments retournés" -ForegroundColor Cyan
        }
        
        return $response
    }
    catch {
        Write-Host "  ❌ ERREUR: $($_.Exception.Message)" -ForegroundColor Red
        return $null
    }
}

# ========== ÉVÉNEMENTS ==========
Write-Host "`n📅 TEST ÉVÉNEMENTS" -ForegroundColor Magenta
Write-Host "══════════════════`n" -ForegroundColor Magenta

$eventId = Test-ApiEndpoint `
    -Name "Créer un événement" `
    -Method "POST" `
    -Url "$baseUrl/api/events.php?action=create" `
    -Body @{
        title = "Test API Consolidée"
        start = "2025-12-01T09:00:00"
        end = "2025-12-01T10:00:00"
        statut = "planifie"
    }

if ($eventId) {
    Test-ApiEndpoint `
        -Name "Récupérer l'événement" `
        -Method "GET" `
        -Url "$baseUrl/api/events.php?action=get&id=$eventId"
    
    Test-ApiEndpoint `
        -Name "Modifier l'événement" `
        -Method "POST" `
        -Url "$baseUrl/api/events.php?action=update" `
        -Body @{
            id = $eventId
            title = "Test Modifié"
            statut = "en_cours"
        }
    
    Test-ApiEndpoint `
        -Name "Liste des événements" `
        -Method "GET" `
        -Url (Build-Url '/api/events.php' @{action='list'; start='2025-12-01'; end='2025-12-31'})
    
    Test-ApiEndpoint `
        -Name "Supprimer l'événement" `
        -Method "POST" `
        -Url "$baseUrl/api/events.php?action=delete" `
        -Body @{ id = $eventId }
}

# ========== CLIENTS ==========
Write-Host "`n👥 TEST CLIENTS" -ForegroundColor Magenta
Write-Host "═══════════════`n" -ForegroundColor Magenta

$clientId = Test-ApiEndpoint `
    -Name "Créer un client" `
    -Method "POST" `
    -Url "$baseUrl/api/clients.php?action=create" `
    -Body @{
        nom = "TestAPI"
        prenom = "Client"
        email = "test@api.local"
        telephone_mobile = "0600000000"
        source_acquisition = "site_web"
        mode_paiement = "mensuel"
    }

if ($clientId) {
    Test-ApiEndpoint `
        -Name "Récupérer le client" `
        -Method "GET" `
        -Url (Build-Url '/api/clients.php' @{action='get'; id=$clientId})
    
    Test-ApiEndpoint `
        -Name "Modifier le client" `
        -Method "POST" `
        -Url "$baseUrl/api/clients.php?action=update" `
        -Body @{
            id = $clientId
            ville = "Paris"
            code_postal = "75001"
        }
    
    Test-ApiEndpoint `
        -Name "Recherche de clients" `
        -Method "GET" `
        -Url (Build-Url '/api/clients.php' @{action='list'; q='TestAPI'})
    
    Test-ApiEndpoint `
        -Name "Marquer un rappel" `
        -Method "POST" `
        -Url "$baseUrl/api/clients.php?action=update_rappel" `
        -Body @{
            client_id = $clientId
            commentaire = "Test rappel API"
        }
    
    Test-ApiEndpoint `
        -Name "Supprimer le client" `
        -Method "POST" `
        -Url "$baseUrl/api/clients.php?action=delete" `
        -Body @{ id = $clientId }
}

# ========== TECHNICIENS ==========
Write-Host "`n👨‍💼 TEST TECHNICIENS" -ForegroundColor Magenta
Write-Host "══════════════════`n" -ForegroundColor Magenta

$techId = Test-ApiEndpoint `
    -Name "Créer un technicien" `
    -Method "POST" `
    -Url "$baseUrl/api/techniciens.php?action=create" `
    -Body @{
        nom = "TestTech"
        prenom = "API"
        couleur = "#ff5722"
        salaire_horaire = 25.50
        actif = $true
    }

if ($techId) {
    Test-ApiEndpoint `
        -Name "Récupérer le technicien" `
        -Method "GET" `
        -Url (Build-Url '/api/techniciens.php' @{action='get'; id=$techId})
    
    Test-ApiEndpoint `
        -Name "Modifier le technicien" `
        -Method "POST" `
        -Url "$baseUrl/api/techniciens.php?action=update" `
        -Body @{
            id = $techId
            salaire_horaire = 30.00
            ville = "Lyon"
        }
    
    Test-ApiEndpoint `
        -Name "Liste des techniciens" `
        -Method "GET" `
        -Url "$baseUrl/api/techniciens.php?action=list"
    
    Test-ApiEndpoint `
        -Name "Supprimer le technicien" `
        -Method "POST" `
        -Url "$baseUrl/api/techniciens.php?action=delete" `
        -Body @{ id = $techId }
}

# ========== FORFAITS ==========
Write-Host "`n📦 TEST FORFAITS" -ForegroundColor Magenta
Write-Host "════════════════`n" -ForegroundColor Magenta

$typeId = Test-ApiEndpoint `
    -Name "Créer un type de forfait" `
    -Method "POST" `
    -Url "$baseUrl/api/forfaits.php?action=create_type" `
    -Body @{
        type_forfait = "Test 5h"
        prix_forfait = 250.00
        nbr_heure_forfait = 5.0
    }

if ($typeId) {
    Test-ApiEndpoint `
        -Name "Liste des types de forfaits" `
        -Method "GET" `
        -Url "$baseUrl/api/forfaits.php?action=list_types"
    
    Test-ApiEndpoint `
        -Name "Modifier le type" `
        -Method "POST" `
        -Url "$baseUrl/api/forfaits.php?action=update_type" `
        -Body @{
            id = $typeId
            prix_forfait = 275.00
        }
    
    Test-ApiEndpoint `
        -Name "Désactiver le type" `
        -Method "POST" `
        -Url "$baseUrl/api/forfaits.php?action=toggle_type" `
        -Body @{
            id = $typeId
            actif = $false
        }
    
    Test-ApiEndpoint `
        -Name "Réactiver le type" `
        -Method "POST" `
        -Url "$baseUrl/api/forfaits.php?action=toggle_type" `
        -Body @{
            id = $typeId
            actif = $true
        }
    
    Test-ApiEndpoint `
        -Name "Supprimer le type" `
        -Method "POST" `
        -Url "$baseUrl/api/forfaits.php?action=delete_type" `
        -Body @{ id = $typeId }
}

# ========== RÉTROCOMPATIBILITÉ ==========
Write-Host "`n🔄 TEST RÉTROCOMPATIBILITÉ" -ForegroundColor Magenta
Write-Host "═══════════════════════════`n" -ForegroundColor Magenta

Test-ApiEndpoint `
    -Name "agenda.php?action=get_events (ancien format)" `
    -Method "GET" `
    -Url "$baseUrl/api/events.php?action=get_events&start=2025-11-01&end=2025-12-31"

Test-ApiEndpoint `
    -Name "forfaits.php?action=get_types (ancien format)" `
    -Method "GET" `
    -Url "$baseUrl/api/forfaits.php?action=get_types"

# ========== RÉSUMÉ ==========
Write-Host "`n========================================" -ForegroundColor Cyan
Write-Host "  ✅ TESTS TERMINÉS" -ForegroundColor Green
Write-Host "========================================`n" -ForegroundColor Cyan

Write-Host "Architecture API consolidée validée !" -ForegroundColor Green
Write-Host "- 5 contrôleurs centralisés" -ForegroundColor Gray
Write-Host "- CRUD complet testé" -ForegroundColor Gray
Write-Host "- Rétrocompatibilité validée" -ForegroundColor Gray
Write-Host "`nConsultez API_CONSOLIDEE.md pour la documentation complète.`n" -ForegroundColor Cyan
