<?php
/**
 * API Événements (Rendez-vous)
 * Gère toutes les opérations CRUD sur les événements
 * 
 * Routes:
 * - GET  ?action=list         → Liste tous les événements (avec filtres optionnels)
 * - GET  ?action=get&id=X     → Détails d'un événement
 * - POST ?action=create       → Créer un événement
 * - POST ?action=update       → Modifier un événement
 * - POST ?action=delete       → Supprimer un événement
 */

require_once __DIR__ . '/../config.php';

header('Content-Type: application/json');

$action = $_GET['action'] ?? $_POST['action'] ?? 'list';
$method = $_SERVER['REQUEST_METHOD'];

try {
    $pdo = getDBConnection();
    
    switch ($action) {
        case 'list':
        case 'get_events': // Compatibilité FullCalendar
            handleList($pdo);
            break;
            
        case 'get':
        case 'get_event_details': // Compatibilité
            handleGet($pdo);
            break;
            
        case 'create':
            if ($method !== 'POST') {
                http_response_code(405);
                echo json_encode(['error' => 'Méthode non autorisée']);
                exit;
            }
            handleCreate($pdo);
            break;
            
        case 'update':
            if ($method !== 'POST') {
                http_response_code(405);
                echo json_encode(['error' => 'Méthode non autorisée']);
                exit;
            }
            handleUpdate($pdo);
            break;
            
        case 'delete':
            if ($method !== 'POST') {
                http_response_code(405);
                echo json_encode(['error' => 'Méthode non autorisée']);
                exit;
            }
            handleDelete($pdo);
            break;
            
        default:
            http_response_code(400);
            echo json_encode(['error' => 'Action inconnue']);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}

function handleList($pdo) {
    $start = $_GET['start'] ?? null;
    $end = $_GET['end'] ?? null;
    
    // Créer la table clients si nécessaire
    $pdo->exec("CREATE TABLE IF NOT EXISTS clients (
        id INT AUTO_INCREMENT PRIMARY KEY,
        prenom VARCHAR(100) DEFAULT '',
        nom VARCHAR(100) DEFAULT '',
        tel_mobile VARCHAR(30) DEFAULT '',
        tel_fixe VARCHAR(30) DEFAULT '',
        adresse TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    
    $sql = "SELECT rv.*, t.couleur AS tech_couleur, t.nom AS tech_nom, t.prenom AS tech_prenom, 
            c.prenom AS client_prenom, c.nom AS client_nom, c.mode_paiement AS client_mode_paiement, 
            c.avance_imme AS client_avance_imme 
            FROM rendez_vous rv 
            LEFT JOIN techniciens t ON rv.id_technicien = t.id 
            LEFT JOIN clients c ON rv.client_id = c.id";
    
    $params = [];
    if ($start && $end) {
        $sql .= " WHERE date_rdv BETWEEN ? AND ?";
        $params = [$start, $end];
    }
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rendez_vous = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Format pour FullCalendar
    $events = [];
    foreach ($rendez_vous as $rdv) {
        $bg = $rdv['tech_couleur'] ?: (COULEURS_STATUT[$rdv['statut']] ?? '#667eea');
        $title = $rdv['titre'];
        
        // Indicateur avance immédiate (emoji uniquement, pas de changement de couleur)
        if ($rdv['client_avance_imme'] == 1) {
            $title = '💚 ' . $title;
        } elseif ($rdv['client_mode_paiement'] === 'avance_immediate') {
            $title = '💰 ' . $title;
        }
        
        $events[] = [
            'id' => $rdv['id'],
            'title' => $title,
            'start' => $rdv['date_rdv'] . 'T' . $rdv['heure_debut'],
            'end' => $rdv['date_rdv'] . 'T' . $rdv['heure_fin'],
            'backgroundColor' => $bg,
            'borderColor' => $bg,
            'extendedProps' => [
                'statut' => $rdv['statut'],
                'lieu' => $rdv['lieu'],
                'description' => $rdv['description'],
                'id_technicien' => $rdv['id_technicien'],
                'client_id' => $rdv['client_id'],
                'client_avance_imme' => $rdv['client_avance_imme']
            ]
        ];
    }
    
    echo json_encode($events);
}

function handleGet($pdo) {
    $id = intval($_GET['id'] ?? 0);
    
    if ($id <= 0) {
        http_response_code(400);
        echo json_encode(['error' => 'ID requis']);
        exit;
    }
    
    $stmt = $pdo->prepare("
        SELECT rv.*, 
            c.prenom AS client_prenom, c.nom AS client_nom, 
            c.email AS client_email, c.telephone_mobile, c.telephone_fixe,
            c.adresse AS client_adresse, c.ville, c.code_postal,
            c.avance_imme AS client_avance_imme,
            t.prenom AS tech_prenom, t.nom AS tech_nom
        FROM rendez_vous rv
        LEFT JOIN clients c ON rv.client_id = c.id
        LEFT JOIN techniciens t ON rv.id_technicien = t.id
        WHERE rv.id = ?
    ");
    $stmt->execute([$id]);
    $event = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$event) {
        http_response_code(404);
        echo json_encode(['error' => 'Événement introuvable']);
        exit;
    }
    
    echo json_encode($event);
}

function handleCreate($pdo) {
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (!$data || empty($data['start'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Date obligatoires']);
        exit;
    }
    
    // Validation
    if (mb_strlen($data['title'] ?? '') > 255) {
        http_response_code(400);
        echo json_encode(['error' => 'Le titre est trop long (max 255 caractères)']);
        exit;
    }
    
    // Parsing date/heure
    $start = $data['start'];
    $end = $data['end'] ?? null;
    
    if (strlen($start) === 10) {
        $date_rdv = $start;
        $heure_debut = '09:00:00';
        $heure_fin = '10:00:00';
    } else {
        $parts = explode('T', $start);
        $date_rdv = $parts[0];
        $heure_debut = (isset($parts[1]) ? substr($parts[1], 0, 8) : '09:00:00');
        
        if ($end && strpos($end, 'T') !== false) {
            $endParts = explode('T', $end);
            $heure_fin = substr($endParts[1], 0, 8);
        } else {
            $heure_fin = $heure_debut;
        }
    }
    
    if (strlen($heure_debut) === 5) $heure_debut .= ':00';
    if (strlen($heure_fin) === 5) $heure_fin .= ':00';
    
    $titre = $data['title'] ?? 'Sans titre';
    $description = $data['description'] ?? '';
    $lieu = $data['lieu'] ?? '';
    $statut = $data['statut'] ?? 'planifie';
    $id_technicien = isset($data['id_technicien']) ? intval($data['id_technicien']) : null;
    $client_id = isset($data['client_id']) ? intval($data['client_id']) : null;
    $distance_km = isset($data['distance_km']) ? floatval($data['distance_km']) : null;
    $temps_trajet_minutes = isset($data['temps_trajet_minutes']) ? intval($data['temps_trajet_minutes']) : null;
    
    // Auto-détecter le véhicule du technicien
    $vehicule_id = null;
    if ($id_technicien) {
        $stmt_vehicle = $pdo->prepare("
            SELECT v.id 
            FROM vehicules v
            INNER JOIN techniciens_vehicules tv ON v.id = tv.id_vehicule
            WHERE tv.id_technicien = ? AND v.actif = 1 AND tv.date_fin IS NULL AND tv.principal = 1
            LIMIT 1
        ");
        $stmt_vehicle->execute([$id_technicien]);
        $vehicle = $stmt_vehicle->fetch(PDO::FETCH_ASSOC);
        if ($vehicle) {
            $vehicule_id = $vehicle['id'];
        }
    }
    
    $stmt = $pdo->prepare("
        INSERT INTO rendez_vous (titre, date_rdv, heure_debut, heure_fin, description, lieu, statut, id_technicien, client_id, distance_km, temps_trajet_minutes, vehicule_id)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    
    $stmt->execute([
        $titre, $date_rdv, $heure_debut, $heure_fin, 
        $description, $lieu, $statut, $id_technicien, $client_id,
        $distance_km, $temps_trajet_minutes, $vehicule_id
    ]);
    
    $id = $pdo->lastInsertId();
    
    http_response_code(201);
    echo json_encode(['status' => 'created', 'id' => $id]);
}

function handleUpdate($pdo) {
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($data['id'])) {
        http_response_code(400);
        echo json_encode(['error' => 'ID requis']);
        exit;
    }
    
    $id = intval($data['id']);
    
    // Construction dynamique de la requête
    $fields = [];
    $values = [];
    
    if (isset($data['title'])) {
        $fields[] = 'titre = ?';
        $values[] = $data['title'];
    }
    
    if (isset($data['start'])) {
        $start = $data['start'];
        if (strpos($start, 'T') !== false) {
            $parts = explode('T', $start);
            $fields[] = 'date_rdv = ?';
            $values[] = $parts[0];
            $fields[] = 'heure_debut = ?';
            $heure = substr($parts[1], 0, 8);
            if (strlen($heure) === 5) $heure .= ':00';
            $values[] = $heure;
        }
    }
    
    if (isset($data['end'])) {
        $end = $data['end'];
        if (strpos($end, 'T') !== false) {
            $parts = explode('T', $end);
            $fields[] = 'heure_fin = ?';
            $heure = substr($parts[1], 0, 8);
            if (strlen($heure) === 5) $heure .= ':00';
            $values[] = $heure;
        }
    }
    
    if (isset($data['description'])) {
        $fields[] = 'description = ?';
        $values[] = $data['description'];
    }
    
    if (isset($data['lieu'])) {
        $fields[] = 'lieu = ?';
        $values[] = $data['lieu'];
    }
    
    if (isset($data['statut'])) {
        $fields[] = 'statut = ?';
        $values[] = $data['statut'];
    }
    
    if (isset($data['id_technicien'])) {
        $fields[] = 'id_technicien = ?';
        $values[] = $data['id_technicien'] ? intval($data['id_technicien']) : null;
    }
    
    if (isset($data['client_id'])) {
        $fields[] = 'client_id = ?';
        $values[] = $data['client_id'] ? intval($data['client_id']) : null;
    }
    
    if (isset($data['distance_km'])) {
        $fields[] = 'distance_km = ?';
        $values[] = $data['distance_km'] ? floatval($data['distance_km']) : null;
    }
    
    if (isset($data['temps_trajet_minutes'])) {
        $fields[] = 'temps_trajet_minutes = ?';
        $values[] = $data['temps_trajet_minutes'] ? intval($data['temps_trajet_minutes']) : null;
    }
    
    // Auto-détecter le véhicule du technicien si un technicien est défini
    if (isset($data['id_technicien']) && $data['id_technicien']) {
        $stmt_vehicle = $pdo->prepare("
            SELECT v.id 
            FROM vehicules v
            INNER JOIN techniciens_vehicules tv ON v.id = tv.id_vehicule
            WHERE tv.id_technicien = ? AND v.actif = 1 AND tv.date_fin IS NULL AND tv.principal = 1
            LIMIT 1
        ");
        $stmt_vehicle->execute([intval($data['id_technicien'])]);
        $vehicle = $stmt_vehicle->fetch(PDO::FETCH_ASSOC);
        
        if ($vehicle) {
            $fields[] = 'vehicule_id = ?';
            $values[] = $vehicle['id'];
        }
    }
    
    if (empty($fields)) {
        echo json_encode(['status' => 'unchanged']);
        exit;
    }
    
    $values[] = $id;
    $sql = "UPDATE rendez_vous SET " . implode(', ', $fields) . " WHERE id = ?";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($values);
    
    echo json_encode(['status' => 'updated']);
}

function handleDelete($pdo) {
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($data['id'])) {
        http_response_code(400);
        echo json_encode(['error' => 'ID requis']);
        exit;
    }
    
    $id = intval($data['id']);
    
    $stmt = $pdo->prepare("DELETE FROM rendez_vous WHERE id = ?");
    $stmt->execute([$id]);
    
    echo json_encode(['status' => 'deleted']);
}
