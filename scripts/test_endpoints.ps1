<#
  scripts/test_endpoints.ps1

  Script PowerShell simple pour tester les endpoints CRUD du projet Agenda
  Usage : lancer depuis PowerShell sur la machine qui héberge WAMP (ou une machine ayant accès à http://localhost/_Interdo/)

  Attention : adapte `http://localhost/_Interdo/` si le projet est servi ailleurs.
#>

Write-Host "=== Test endpoints Agenda (FullCalendar) ===" -ForegroundColor Cyan

# 1) Importer la base (exécuter dans le client MySQL si besoin)
Write-Host "If needed: import database with:" -ForegroundColor Yellow
Write-Host "mysql -u root < database.sql" -ForegroundColor Green

try {
    function Invoke-Api {
        param(
            [string]$Method,
            [string]$Url,
            $Body = $null
        )

        Write-Host "\n-> $Method $Url" -ForegroundColor Cyan
        try {
                if ($Body -ne $null) {
                    $bodyJson = $Body | ConvertTo-Json -Depth 5
                    $resp = Invoke-WebRequest -Uri $Url -Method $Method -Body $bodyJson -ContentType "application/json" -UseBasicParsing -TimeoutSec 30
                } else {
                    $resp = Invoke-WebRequest -Uri $Url -Method $Method -UseBasicParsing -TimeoutSec 30
                }

            $status = $resp.StatusCode
            $content = $resp.Content
            Write-Host "Status: $status" -ForegroundColor Green
            Write-Host "Content (raw):" -ForegroundColor Green
            Write-Host $content

            # Try to parse JSON if possible
            try {
                $json = $content | ConvertFrom-Json -ErrorAction Stop
                return $json
            } catch {
                return @{ raw = $content; status = $status }
            }
        } catch {
            Write-Host "HTTP request failed: $($_.Exception.Message)" -ForegroundColor Red
            if ($_.Exception.Response -ne $null) {
                try { Write-Host ($_.Exception.Response | Select-Object -ExpandProperty Content) } catch {}
            }
            throw $_
        }
    }

    # 2) CREATE (POST JSON -> add_event.php)
    $createBody = @{ title = "Test RDV PS"; start = "2025-11-22T09:00:00" }
    $createResp = Invoke-Api -Method "POST" -Url "http://localhost/_Interdo/add_event.php" -Body $createBody
    Write-Host "Create response (parsed):" -ForegroundColor Green
    $createResp | ConvertTo-Json -Depth 5 | Write-Host

    # Retrieve created id (if returned)
    if ($createResp -and $createResp.id) {
        $id = $createResp.id
        Write-Host "Created ID: $id" -ForegroundColor Green
    } else {
        Write-Host "No ID returned by add_event.php - check response and PHP logs." -ForegroundColor Red
        if ($createResp -and $createResp.error) { Write-Host "Error: $($createResp.error)" -ForegroundColor Red }
        return
    }

    # 3) READ (GET -> agenda.php?action=get_events)
    $events = Invoke-Api -Method "GET" -Url "http://localhost/_Interdo/agenda.php?action=get_events&start=2025-11-01&end=2025-11-30"
    if ($events -is [System.Array]) { Write-Host "Number of events: $($events.Count)" -ForegroundColor Green } else { Write-Host "Non-array response received" -ForegroundColor Yellow }

    # 4) UPDATE (POST JSON -> update_event.php)
    Write-Host "\n-> Update (POST update_event.php)" -ForegroundColor Cyan
    $updateBody = @{ id = $id; title = "Test RDV modified PS"; start = "2025-11-22T10:00:00"; end = "2025-11-22T11:00:00" }
    $updateResp = Invoke-Api -Method "POST" -Url "http://localhost/_Interdo/update_event.php" -Body $updateBody
    Write-Host "Update response (parsed):" -ForegroundColor Green
    $updateResp | ConvertTo-Json -Depth 5 | Write-Host

    # 5) DELETE (POST JSON -> delete_event.php)
    Write-Host "\n-> Deleting (POST delete_event.php)" -ForegroundColor Cyan
    $deleteBody = @{ id = $id }
    $deleteResp = Invoke-Api -Method "POST" -Url "http://localhost/_Interdo/delete_event.php" -Body $deleteBody
    Write-Host "Delete response (parsed):" -ForegroundColor Green
    $deleteResp | ConvertTo-Json -Depth 5 | Write-Host

    Write-Host "\n=== Tests finished ===" -ForegroundColor Cyan
}
catch {
    Write-Host "Error during tests:" -ForegroundColor Red
    Write-Host $_.Exception.Message -ForegroundColor Red
}
