<#
 scripts/test_techniciens.ps1
 Simple PowerShell script to test technicians CRUD endpoints.
 Usage: run from project root on the machine hosting WAMP.
#>

$baseUrl = "http://localhost/_Interdo"
Write-Host "=== Test technicians CRUD ===" -ForegroundColor Cyan

function Invoke-ApiJson {
    param(
        [string]$Method,
        [string]$Url,
        $Body = $null
    )
    try {
        if ($Body -ne $null) {
            $bodyJson = $Body | ConvertTo-Json -Depth 6
            Write-Host "-> $Method $Url`n  Body: $bodyJson" -ForegroundColor Cyan
            $resp = Invoke-RestMethod -Uri $Url -Method $Method -Body $bodyJson -ContentType 'application/json' -ErrorAction Stop
        } else {
            Write-Host "-> $Method $Url" -ForegroundColor Cyan
            $resp = Invoke-RestMethod -Uri $Url -Method $Method -ErrorAction Stop
        }
        Write-Host "Response:" -ForegroundColor Green
        $resp | ConvertTo-Json -Depth 6 | Write-Host
        return $resp
    } catch {
        Write-Host "HTTP error: $($_.Exception.Message)" -ForegroundColor Red
        if ($_.Exception.Response) {
            try { $body = $_.Exception.Response.GetResponseStream(); Write-Host $body } catch {}
        }
        throw $_
    }
}

try {
    # CREATE
    $createBody = @{
        nom = "TestNom"
        prenom = "TestPrenom"
        email = "test.tech@example.com"
        adresse = "1 rue de Test"
        code_postal = "00000"
        ville = "VilleTest"
        pays = "France"
        telephone_fixe = "0102030405"
        telephone_mobile = "0612345678"
        date_entree = (Get-Date).ToString('yyyy-MM-dd')
        date_sortie = $null
        actif = 1
        couleur = "#667eea"
        salaire_horaire = 15.50
    }

    $createResp = Invoke-ApiJson -Method 'POST' -Url "$baseUrl/add_technicien.php" -Body $createBody
    if (-not $createResp.id) { throw "No id returned from add_technicien.php" }
    $id = $createResp.id
    Write-Host "Created technician id: $id" -ForegroundColor Green

    # READ (list)
    $list = Invoke-ApiJson -Method 'GET' -Url "$baseUrl/load_techniciens.php"
    if (($list | Measure-Object).Count -eq 0) { Write-Host "Warning: technicians list is empty" -ForegroundColor Yellow }

    # UPDATE
    $updateBody = @{
        id = $id
        ville = "VilleModifiee"
        salaire_horaire = 20.00
        couleur = "#4caf50"
    }
    $updateResp = Invoke-ApiJson -Method 'POST' -Url "$baseUrl/update_technicien.php" -Body $updateBody
    if ($updateResp.status -ne 'updated') { Write-Host "Update response unexpected" -ForegroundColor Yellow }

    # VERIFY single load (re-list and find)
    $list2 = Invoke-ApiJson -Method 'GET' -Url "$baseUrl/load_techniciens.php"
    $found = $false
    foreach ($item in $list2) { if ($item.id -eq [int]$id) { $found = $true; Write-Host "Found updated item:`n"; $item | ConvertTo-Json -Depth 6 | Write-Host; break } }
    if (-not $found) { Write-Host "Updated item not found in list" -ForegroundColor Yellow }

    # DELETE
    $deleteBody = @{ id = $id }
    $deleteResp = Invoke-ApiJson -Method 'POST' -Url "$baseUrl/delete_technicien.php" -Body $deleteBody
    if ($deleteResp.status -ne 'deleted') { Write-Host "Delete response unexpected" -ForegroundColor Yellow }

    Write-Host "=== Technicians CRUD test completed successfully ===" -ForegroundColor Cyan
}
catch {
    Write-Host "Test failed: $($_.Exception.Message)" -ForegroundColor Red
    exit 1
}
