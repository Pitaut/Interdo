<?php
/**
 * Calculateur de distances avec OpenRouteService
 */

// Log des erreurs de calcul
$GLOBALS['distance_errors'] = [];

function logDistanceError($message) {
    $GLOBALS['distance_errors'][] = $message;
    error_log($message);
}

/**
 * Géocode une adresse en coordonnées GPS (lat, lon)
 * @param string $adresse Adresse complète
 * @return array|null ['lat' => float, 'lon' => float] ou null si échec
 */
function geocodeAddress($adresse) {
    if (empty($adresse)) {
        return null;
    }
    
    // Vérifier que CURL est disponible
    if (!function_exists('curl_init')) {
        logDistanceError("CURL n'est pas installé ou activé dans PHP");
        return null;
    }
    
    $url = 'https://api.openrouteservice.org/geocode/search';
    $params = [
        'api_key' => OPENROUTE_API_KEY,
        'text' => $adresse,
        'size' => 1
    ];
    
    $url .= '?' . http_build_query($params);
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // Désactiver vérification SSL en local
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);
    
    if ($httpCode !== 200 || !$response) {
        logDistanceError("Geocoding failed for address: $adresse (HTTP $httpCode" . ($curlError ? ", CURL Error: $curlError" : "") . ")");
        return null;
    }
    
    $data = json_decode($response, true);
    
    if (isset($data['features'][0]['geometry']['coordinates'])) {
        $coords = $data['features'][0]['geometry']['coordinates'];
        return [
            'lon' => $coords[0],
            'lat' => $coords[1]
        ];
    }
    
    logDistanceError("No coordinates found for address: $adresse");
    return null;
}

/**
 * Calcule la distance de route entre deux coordonnées GPS
 * @param array $from ['lat' => float, 'lon' => float]
 * @param array $to ['lat' => float, 'lon' => float]
 * @return array|null ['distance_km' => float, 'duration_minutes' => int] ou null si échec
 */
function calculateRouteDistance($from, $to) {
    if (empty($from) || empty($to)) {
        return null;
    }
    
    // Vérifier si les coordonnées sont identiques (même lieu)
    $latDiff = abs($from['lat'] - $to['lat']);
    $lonDiff = abs($from['lon'] - $to['lon']);
    
    if ($latDiff < 0.0001 && $lonDiff < 0.0001) {
        // Même lieu, distance = 0
        return [
            'distance_km' => 0,
            'duration_minutes' => 0
        ];
    }
    
    $url = 'https://api.openrouteservice.org/v2/directions/driving-car';
    
    $postData = [
        'coordinates' => [
            [$from['lon'], $from['lat']],
            [$to['lon'], $to['lat']]
        ]
    ];
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($postData));
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: ' . OPENROUTE_API_KEY
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);
    
    if ($httpCode !== 200 || !$response) {
        logDistanceError("Route calculation failed (HTTP $httpCode" . ($curlError ? ", CURL Error: $curlError" : "") . ")");
        return null;
    }
    
    $data = json_decode($response, true);
    
    // Vérifier si on a une réponse valide
    if (!isset($data['routes'][0]['summary'])) {
        $errorMsg = $data['error'] ?? 'Réponse API invalide';
        logDistanceError("Route calculation failed: " . json_encode($errorMsg));
        return null;
    }
    
    $summary = $data['routes'][0]['summary'];
    
    // Vérifier que les données essentielles sont présentes
    if (!isset($summary['distance']) || !isset($summary['duration'])) {
        logDistanceError("Missing distance or duration in API response");
        return null;
    }
    
    return [
        'distance_km' => round($summary['distance'] / 1000, 2),
        'duration_minutes' => round($summary['duration'] / 60)
    ];
}

/**
 * Calcule et met à jour les distances pour les interventions terminées d'une période
 * @param PDO $pdo Connexion à la base
 * @param string $dateDebut Date de début (Y-m-d)
 * @param string $dateFin Date de fin (Y-m-d)
 * @return array Statistiques ['calculated' => int, 'errors' => int, 'skipped' => int]
 */
function calculerDistancesInterventions($pdo, $dateDebut, $dateFin) {
    $stats = [
        'calculated' => 0,
        'errors' => 0,
        'skipped' => 0
    ];
    
    // Réinitialiser les erreurs
    $GLOBALS['distance_errors'] = [];
    
    // Récupérer les interventions terminées sans distance calculée
    $sql = "SELECT r.id, r.date_rdv, r.heure_debut, r.lieu, r.id_technicien, r.distance_km,
                   t.adresse AS tech_adresse, t.code_postal AS tech_cp, t.ville AS tech_ville,
                   c.adresse AS client_adresse, c.code_postal AS client_cp, c.ville AS client_ville
            FROM rendez_vous r
            LEFT JOIN techniciens t ON r.id_technicien = t.id
            LEFT JOIN clients c ON r.client_id = c.id
            WHERE r.statut = 'termine'
              AND r.date_rdv BETWEEN ? AND ?
              AND (r.distance_km IS NULL OR r.distance_km = 0)
              AND r.id_technicien IS NOT NULL
            ORDER BY r.date_rdv ASC, r.heure_debut ASC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$dateDebut, $dateFin]);
    $interventions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($interventions)) {
        return $stats;
    }
    
    // Regrouper par technicien et date
    $interventionsParTechnicien = [];
    foreach ($interventions as $inter) {
        $key = $inter['id_technicien'] . '_' . $inter['date_rdv'];
        if (!isset($interventionsParTechnicien[$key])) {
            $interventionsParTechnicien[$key] = [
                'technicien_id' => $inter['id_technicien'],
                'date' => $inter['date_rdv'],
                'adresse_depart' => trim(($inter['tech_adresse'] ?? '') . ' ' . ($inter['tech_cp'] ?? '') . ' ' . ($inter['tech_ville'] ?? '')),
                'interventions' => []
            ];
        }
        $interventionsParTechnicien[$key]['interventions'][] = $inter;
    }
    
    // Pour chaque journée de chaque technicien
    foreach ($interventionsParTechnicien as $journee) {
        $adresseDepart = $journee['adresse_depart'];
        
        // Géocoder l'adresse de départ du technicien
        $coordsDepart = geocodeAddress($adresseDepart);
        if (!$coordsDepart) {
            logDistanceError("Impossible de géocoder l'adresse du technicien: $adresseDepart");
            $stats['errors'] += count($journee['interventions']);
            continue;
        }
        
        $coordsPrecedentes = $coordsDepart;
        
        // Pour chaque intervention de la journée
        foreach ($journee['interventions'] as $inter) {
            // Construire l'adresse de destination
            $adresseDestination = $inter['lieu'];
            if (empty($adresseDestination)) {
                $adresseDestination = trim(($inter['client_adresse'] ?? '') . ' ' . ($inter['client_cp'] ?? '') . ' ' . ($inter['client_ville'] ?? ''));
            }
            
            if (empty($adresseDestination)) {
                logDistanceError("Pas d'adresse pour l'intervention ID " . $inter['id']);
                $stats['skipped']++;
                continue;
            }
            
            // Géocoder la destination
            $coordsDestination = geocodeAddress($adresseDestination);
            if (!$coordsDestination) {
                logDistanceError("Impossible de géocoder l'adresse: $adresseDestination (Intervention ID: {$inter['id']})");
                $stats['errors']++;
                continue;
            }
            
            // Calculer la distance
            $route = calculateRouteDistance($coordsPrecedentes, $coordsDestination);
            if (!$route) {
                logDistanceError("Impossible de calculer la route pour l'intervention ID " . $inter['id']);
                $stats['errors']++;
                continue;
            }
            
            // Mettre à jour l'intervention
            $updateSql = "UPDATE rendez_vous 
                         SET distance_km = ?, temps_trajet_minutes = ?
                         WHERE id = ?";
            $updateStmt = $pdo->prepare($updateSql);
            $updateStmt->execute([
                $route['distance_km'],
                $route['duration_minutes'],
                $inter['id']
            ]);
            
            $stats['calculated']++;
            
            // La prochaine intervention partira de cette destination
            $coordsPrecedentes = $coordsDestination;
            
            // Petite pause pour respecter les limites de l'API
            usleep(100000); // 100ms
        }
    }
    
    // Ajouter les erreurs détaillées aux stats
    $stats['errors_details'] = $GLOBALS['distance_errors'];
    
    return $stats;
}
