<?php
/**
 * API Forfaits
 * Gère toutes les opérations sur les types de forfaits et les forfaits vendus
 * 
 * Routes Types de Forfaits:
 * - GET  ?action=list_types              → Liste tous les types de forfaits
 * - POST ?action=create_type             → Créer un type de forfait
 * - POST ?action=update_type             → Modifier un type de forfait
 * - POST ?action=delete_type             → Supprimer un type de forfait
 * - POST ?action=toggle_type&id=X        → Activer/désactiver un type
 * 
 * Routes Forfaits Vendus:
 * - GET  ?action=list&client_id=X        → Liste les forfaits d'un client
 * - POST ?action=vendre                  → Vendre un forfait à un client
 * - POST ?action=marquer_paye&id=X       → Marquer un forfait comme payé
 */

require_once __DIR__ . '/../config.php';

header('Content-Type: application/json');

$action = $_GET['action'] ?? $_POST['action'] ?? 'list_types';
$method = $_SERVER['REQUEST_METHOD'];

try {
    $pdo = getDBConnection();
    
    // Créer les tables si nécessaire
    $pdo->exec("CREATE TABLE IF NOT EXISTS type_forfait (
        id INT AUTO_INCREMENT PRIMARY KEY,
        type_forfait VARCHAR(100) NOT NULL,
        prix_forfait DECIMAL(10,2) NOT NULL,
        nbr_heure_forfait DECIMAL(10,2) NOT NULL,
        date_creation TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        actif BOOLEAN DEFAULT TRUE,
        INDEX idx_actif (actif)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    
    $pdo->exec("CREATE TABLE IF NOT EXISTS forfaits_vendus (
        id INT AUTO_INCREMENT PRIMARY KEY,
        client_id INT NOT NULL,
        type_forfait_id INT NOT NULL,
        heures_total DECIMAL(10,2) NOT NULL,
        heures_restantes DECIMAL(10,2) NOT NULL,
        tarif DECIMAL(10,2) NOT NULL,
        date_debut DATE DEFAULT NULL,
        date_fin DATE DEFAULT NULL,
        date_vente DATE DEFAULT NULL,
        paye BOOLEAN DEFAULT FALSE,
        mode_reglement VARCHAR(50) DEFAULT NULL,
        date_paiement DATE DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_client (client_id),
        INDEX idx_paye (paye)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    
    // Ajouter les colonnes mode_reglement et date_paiement si elles n'existent pas
    try {
        $pdo->exec("ALTER TABLE forfaits_vendus ADD COLUMN mode_reglement VARCHAR(50) DEFAULT NULL");
    } catch (PDOException $e) {
        // Colonne existe déjà
    }
    try {
        $pdo->exec("ALTER TABLE forfaits_vendus ADD COLUMN date_vente DATE DEFAULT NULL");
    } catch (PDOException $e) {
        // Colonne existe déjà
    }
    try {
        $pdo->exec("ALTER TABLE forfaits_vendus ADD COLUMN date_paiement DATE DEFAULT NULL");
    } catch (PDOException $e) {
        // Colonne existe déjà
    }
    
    switch ($action) {
        // Types de forfaits
        case 'list_types':
        case 'get_types': // Compatibilité
            handleListTypes($pdo);
            break;
            
        case 'create_type':
            if ($method !== 'POST') {
                http_response_code(405);
                echo json_encode(['error' => 'Méthode non autorisée']);
                exit;
            }
            handleCreateType($pdo);
            break;
            
        case 'update_type':
            if ($method !== 'POST') {
                http_response_code(405);
                echo json_encode(['error' => 'Méthode non autorisée']);
                exit;
            }
            handleUpdateType($pdo);
            break;
            
        case 'delete_type':
            if ($method !== 'POST') {
                http_response_code(405);
                echo json_encode(['error' => 'Méthode non autorisée']);
                exit;
            }
            handleDeleteType($pdo);
            break;
            
        case 'toggle_type':
            if ($method !== 'POST') {
                http_response_code(405);
                echo json_encode(['error' => 'Méthode non autorisée']);
                exit;
            }
            handleToggleType($pdo);
            break;
            
        // Forfaits vendus
        case 'list':
            handleListForfaits($pdo);
            break;
            
        case 'vendre':
            if ($method !== 'POST') {
                http_response_code(405);
                echo json_encode(['error' => 'Méthode non autorisée']);
                exit;
            }
            handleVendre($pdo);
            break;
            
        case 'marquer_paye':
            if ($method !== 'POST') {
                http_response_code(405);
                echo json_encode(['error' => 'Méthode non autorisée']);
                exit;
            }
            handleMarquerPaye($pdo);
            break;
            
        case 'dernier_mode_reglement':
            handleDernierModeReglement($pdo);
            break;
            
        default:
            http_response_code(400);
            echo json_encode(['error' => 'Action inconnue']);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}

// ========== TYPES DE FORFAITS ==========

function handleListTypes($pdo) {
    $stmt = $pdo->query("SELECT * FROM type_forfait ORDER BY type_forfait ASC");
    $types = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode($types);
}

function handleCreateType($pdo) {
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (!$data) {
        http_response_code(400);
        echo json_encode(['error' => 'Données JSON invalides']);
        exit;
    }
    
    $type_forfait = trim($data['type_forfait'] ?? '');
    $prix_forfait = floatval($data['prix_forfait'] ?? 0);
    $nbr_heure_forfait = floatval($data['nbr_heure_forfait'] ?? 0);
    
    if (empty($type_forfait) || $prix_forfait <= 0 || $nbr_heure_forfait <= 0) {
        http_response_code(400);
        echo json_encode(['error' => 'Tous les champs sont requis']);
        exit;
    }
    
    $stmt = $pdo->prepare("
        INSERT INTO type_forfait (type_forfait, prix_forfait, nbr_heure_forfait, actif)
        VALUES (?, ?, ?, TRUE)
    ");
    
    $stmt->execute([$type_forfait, $prix_forfait, $nbr_heure_forfait]);
    
    $id = $pdo->lastInsertId();
    
    http_response_code(201);
    echo json_encode(['status' => 'created', 'id' => $id]);
}

function handleUpdateType($pdo) {
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($data['id'])) {
        http_response_code(400);
        echo json_encode(['error' => 'ID requis']);
        exit;
    }
    
    $id = intval($data['id']);
    $type_forfait = trim($data['type_forfait'] ?? '');
    $prix_forfait = floatval($data['prix_forfait'] ?? 0);
    $nbr_heure_forfait = floatval($data['nbr_heure_forfait'] ?? 0);
    
    if (empty($type_forfait) || $prix_forfait <= 0 || $nbr_heure_forfait <= 0) {
        http_response_code(400);
        echo json_encode(['error' => 'Tous les champs sont requis']);
        exit;
    }
    
    $stmt = $pdo->prepare("
        UPDATE type_forfait 
        SET type_forfait = ?, prix_forfait = ?, nbr_heure_forfait = ?
        WHERE id = ?
    ");
    
    $stmt->execute([$type_forfait, $prix_forfait, $nbr_heure_forfait, $id]);
    
    echo json_encode(['status' => 'updated']);
}

function handleDeleteType($pdo) {
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($data['id'])) {
        http_response_code(400);
        echo json_encode(['error' => 'ID requis']);
        exit;
    }
    
    $id = intval($data['id']);
    
    // Vérifier si le type est utilisé
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM forfaits_vendus WHERE type_forfait_id = ?");
    $stmt->execute([$id]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($result['count'] > 0) {
        http_response_code(400);
        echo json_encode(['error' => 'Ce type de forfait est utilisé et ne peut être supprimé']);
        exit;
    }
    
    $stmt = $pdo->prepare("DELETE FROM type_forfait WHERE id = ?");
    $stmt->execute([$id]);
    
    echo json_encode(['status' => 'deleted']);
}

function handleToggleType($pdo) {
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($data['id'])) {
        http_response_code(400);
        echo json_encode(['error' => 'ID requis']);
        exit;
    }
    
    $id = intval($data['id']);
    $actif = isset($data['actif']) ? (bool)$data['actif'] : true;
    
    $stmt = $pdo->prepare("UPDATE type_forfait SET actif = ? WHERE id = ?");
    $stmt->execute([$actif, $id]);
    
    echo json_encode(['status' => 'updated', 'actif' => $actif]);
}

// ========== FORFAITS VENDUS ==========

function handleListForfaits($pdo) {
    $client_id = intval($_GET['client_id'] ?? 0);
    
    if ($client_id <= 0) {
        http_response_code(400);
        echo json_encode(['error' => 'Client ID requis']);
        exit;
    }
    
    // Récupérer tous les forfaits du client avec les détails du type de forfait
    $stmt = $pdo->prepare("
        SELECT 
            fv.id,
            fv.client_id,
            fv.type_forfait_id,
            fv.heures_total,
            fv.heures_restantes,
            fv.tarif,
            fv.date_debut,
            fv.date_fin,
            COALESCE(fv.date_vente, DATE(fv.created_at)) AS date_vente,
            fv.created_at,
            tf.type_forfait AS type_forfait_nom,
            tf.nbr_heure_forfait AS type_forfait_heures,
            tf.prix_forfait AS type_forfait_prix
        FROM forfaits_vendus fv
        LEFT JOIN type_forfait tf ON fv.type_forfait_id = tf.id
        WHERE fv.client_id = ? AND fv.heures_restantes > 0
        ORDER BY COALESCE(fv.date_vente, fv.created_at) DESC, fv.id DESC
    ");
    
    $stmt->execute([$client_id]);
    $forfaits = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Calculer les totaux
    $total_heures_restantes = 0;
    $total_heures_consommees = 0;
    $forfait_actif = null;
    
    foreach ($forfaits as $f) {
        $total_heures_restantes += floatval($f['heures_restantes']);
        if (!$forfait_actif && floatval($f['heures_restantes']) > 0) {
            $forfait_actif = $f;
        }
        $total_heures_consommees += floatval($f['heures_total']) - floatval($f['heures_restantes']);
    }
    
    // Récupérer le heure_bonus du client
    $stmt = $pdo->prepare("SELECT heure_bonus FROM clients WHERE id = ?");
    $stmt->execute([$client_id]);
    $client = $stmt->fetch(PDO::FETCH_ASSOC);
    $heure_bonus = $client ? floatval($client['heure_bonus'] ?? 0) : 0;
    
    // Récupérer le dernier forfait acheté (même s'il est épuisé) pour info
    $stmt = $pdo->prepare("
        SELECT 
            fv.id,
            fv.heures_total,
            fv.heures_restantes,
            COALESCE(fv.date_vente, DATE(fv.created_at)) AS date_vente,
            fv.created_at,
            tf.type_forfait AS type_forfait_nom
        FROM forfaits_vendus fv
        LEFT JOIN type_forfait tf ON fv.type_forfait_id = tf.id
        WHERE fv.client_id = ?
        ORDER BY COALESCE(fv.date_vente, fv.created_at) DESC, fv.id DESC
        LIMIT 1
    ");
    $stmt->execute([$client_id]);
    $dernier_forfait = $stmt->fetch(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'forfaits' => $forfaits,
        'forfait_actif' => $forfait_actif,
        'dernier_forfait' => $dernier_forfait,
        'total_heures_restantes' => round($total_heures_restantes, 2),
        'total_heures_consommees' => round($total_heures_consommees, 2),
        'heure_bonus' => round($heure_bonus, 2),
        'heure_bonus_minutes' => round($heure_bonus * 60, 0)
    ]);
}

function handleVendre($pdo) {
    $data = json_decode(file_get_contents('php://input'), true);
    
    $client_id = intval($data['client_id'] ?? 0);
    $type_forfait_id = intval($data['type_forfait_id'] ?? 0);
    $date_debut = $data['date_debut'] ?? date('Y-m-d');
    $date_fin = $data['date_fin'] ?? null;
    $date_vente = $data['date_vente'] ?? date('Y-m-d');
    $signature_client = $data['signature_client'] ?? null;
    
    if ($client_id <= 0 || $type_forfait_id <= 0) {
        http_response_code(400);
        echo json_encode(['error' => 'Client ID et Type forfait ID requis']);
        exit;
    }
    
    // Récupérer les infos du type de forfait
    $stmt = $pdo->prepare("SELECT * FROM type_forfait WHERE id = ? AND actif = TRUE");
    $stmt->execute([$type_forfait_id]);
    $type = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$type) {
        http_response_code(404);
        echo json_encode(['error' => 'Type de forfait introuvable ou inactif']);
        exit;
    }
    
    // S'assurer que les heures sont un multiple de 0.5h (30 minutes)
    $heures_forfait = round($type['nbr_heure_forfait'] * 2) / 2;
    
    // Créer le forfait vendu avec signature
    $stmt = $pdo->prepare("
        INSERT INTO forfaits_vendus (client_id, type_forfait_id, heures_total, heures_restantes, tarif, date_debut, date_fin, date_vente, signature_client, date_signature)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
    ");
    
    $stmt->execute([
        $client_id,
        $type_forfait_id,
        $heures_forfait,
        $heures_forfait,
        $type['prix_forfait'],
        $date_debut,
        $date_fin,
        $date_vente,
        $signature_client
    ]);
    
    $id = $pdo->lastInsertId();
    
    http_response_code(201);
    echo json_encode(['status' => 'created', 'id' => $id]);
}

function handleMarquerPaye($pdo) {
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($data['forfait_id'])) {
        http_response_code(400);
        echo json_encode(['error' => 'forfait_id requis']);
        exit;
    }
    
    $forfait_id = intval($data['forfait_id']);
    $client_id = isset($data['client_id']) ? intval($data['client_id']) : 0;
    $mode_reglement = trim($data['mode_reglement'] ?? '');
    $date_paiement = $data['date_paiement'] ?? date('Y-m-d');
    $date_vente = $data['date_vente'] ?? null;
    
    if (empty($mode_reglement)) {
        http_response_code(400);
        echo json_encode(['error' => 'Mode de règlement requis']);
        exit;
    }
    
    // Mettre à jour le forfait
    $stmt = $pdo->prepare("
        UPDATE forfaits_vendus 
        SET paye = TRUE, mode_reglement = ?, date_paiement = ?, date_vente = COALESCE(?, date_vente) 
        WHERE id = ?
    ");
    $stmt->execute([$mode_reglement, $date_paiement, $date_vente, $forfait_id]);
    
    // Si le mode de règlement est 'avance_immediate', mettre à jour le champ avance_imme du client
    if ($mode_reglement === 'avance_immediate' && $client_id > 0) {
        $stmt = $pdo->prepare("
            UPDATE clients 
            SET avance_imme = 1, mode_paiement = 'avance_immediate'
            WHERE id = ?
        ");
        $stmt->execute([$client_id]);
    }
    
    echo json_encode(['success' => true]);
}

function handleDernierModeReglement($pdo) {
    $client_id = intval($_GET['client_id'] ?? 0);
    
    if ($client_id <= 0) {
        http_response_code(400);
        echo json_encode(['error' => 'Client ID requis']);
        exit;
    }
    
    // Récupérer le dernier forfait payé de ce client avec un mode de règlement
    $stmt = $pdo->prepare("
        SELECT mode_reglement, date_paiement
        FROM forfaits_vendus
        WHERE client_id = ? AND paye = 1 AND mode_reglement IS NOT NULL
        ORDER BY date_paiement DESC, id DESC
        LIMIT 1
    ");
    $stmt->execute([$client_id]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($result) {
        echo json_encode([
            'mode_reglement' => $result['mode_reglement'],
            'date_paiement' => $result['date_paiement']
        ]);
    } else {
        // Aucun historique de règlement pour ce client
        echo json_encode([
            'mode_reglement' => null,
            'date_paiement' => null
        ]);
    }
}
