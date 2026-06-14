<?php
/**
 * API Clients
 * Gère toutes les opérations CRUD sur les clients
 * 
 * Routes:
 * - GET  ?action=list         → Liste tous les clients (avec recherche optionnelle)
 * - GET  ?action=get&id=X     → Détails d'un client
 * - POST ?action=create       → Créer un client
 * - POST ?action=update       → Modifier un client
 * - POST ?action=delete       → Supprimer un client
 * - POST ?action=update_rappel → Mettre à jour le rappel d'un client
 */

require_once __DIR__ . '/../config.php';

header('Content-Type: application/json');

$action = $_GET['action'] ?? $_POST['action'] ?? 'list';
$method = $_SERVER['REQUEST_METHOD'];

try {
    $pdo = getDBConnection();
    
    switch ($action) {
        case 'list':
            handleList($pdo);
            break;
            
        case 'get':
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
            
        case 'update_rappel':
            if ($method !== 'POST') {
                http_response_code(405);
                echo json_encode(['error' => 'Méthode non autorisée']);
                exit;
            }
            handleUpdateRappel($pdo);
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
    $q = isset($_GET['q']) ? trim($_GET['q']) : '';
    $id = isset($_GET['id']) ? intval($_GET['id']) : 0;
    $limit = isset($_GET['limit']) ? intval($_GET['limit']) : 20;
    
    // Créer la table si nécessaire
    $pdo->exec("CREATE TABLE IF NOT EXISTS clients (
        id INT AUTO_INCREMENT PRIMARY KEY,
        nom VARCHAR(100) NOT NULL,
        prenom VARCHAR(100) NOT NULL,
        email VARCHAR(100) DEFAULT NULL,
        adresse TEXT,
        code_postal VARCHAR(20) DEFAULT NULL,
        ville VARCHAR(100) DEFAULT NULL,
        pays VARCHAR(100) DEFAULT NULL,
        etage VARCHAR(20) DEFAULT NULL,
        code_entree VARCHAR(50) DEFAULT NULL,
        telephone_fixe VARCHAR(20) DEFAULT NULL,
        telephone_mobile VARCHAR(20) DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        source_acquisition VARCHAR(50) DEFAULT NULL,
        mode_paiement VARCHAR(50) DEFAULT NULL,
        date_dernier_rappel DATETIME DEFAULT NULL,
        commentaire_rappel TEXT DEFAULT NULL,
        heure_bonus DECIMAL(10,2) DEFAULT 0.00 COMMENT 'Cumul des différences d arrondi (en heures décimales)',
        avance_imme TINYINT(1) DEFAULT 0 COMMENT 'Client en avance immédiate'
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    
    // Ajouter les colonnes si elles n'existent pas (migration)
    try {
        $pdo->exec("ALTER TABLE clients ADD COLUMN heure_bonus DECIMAL(10,2) DEFAULT 0.00 COMMENT 'Cumul des différences d arrondi (en heures décimales)'");
    } catch (PDOException $e) {}
    try {
        $pdo->exec("ALTER TABLE clients ADD COLUMN avance_imme TINYINT(1) DEFAULT 0 COMMENT 'Client en avance immédiate'");
    } catch (PDOException $e) {}

    
    if ($id > 0) {
        $stmt = $pdo->prepare("SELECT * FROM clients WHERE id = ?");
        $stmt->execute([$id]);
        $client = $stmt->fetch(PDO::FETCH_ASSOC);
        echo json_encode($client ?: null);
        exit;
    }
    
    if ($q !== '') {
        $searchPattern = "%{$q}%";
        $stmt = $pdo->prepare("
            SELECT * FROM clients 
            WHERE nom LIKE ? OR prenom LIKE ? OR telephone_mobile LIKE ? OR telephone_fixe LIKE ? OR email LIKE ?
            ORDER BY nom ASC, prenom ASC
            LIMIT ?
        ");
        $stmt->execute([$searchPattern, $searchPattern, $searchPattern, $searchPattern, $searchPattern, $limit]);
    } else {
        $stmt = $pdo->prepare("SELECT * FROM clients ORDER BY nom ASC, prenom ASC LIMIT ?");
        $stmt->execute([$limit]);
    }
    
    $clients = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Ajouter le champ 'display' pour la compatibilité avec le frontend
    foreach ($clients as &$client) {
        $client['display'] = trim(($client['prenom'] ?? '') . ' ' . ($client['nom'] ?? ''));
    }
    
    echo json_encode(['clients' => $clients]);
}

function handleGet($pdo) {
    $id = intval($_GET['id'] ?? 0);
    
    if ($id <= 0) {
        http_response_code(400);
        echo json_encode(['error' => 'ID requis']);
        exit;
    }
    
    $stmt = $pdo->prepare("SELECT * FROM clients WHERE id = ?");
    $stmt->execute([$id]);
    $client = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$client) {
        http_response_code(404);
        echo json_encode(['error' => 'Client introuvable']);
        exit;
    }
    
    echo json_encode($client);
}

function handleCreate($pdo) {
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (!$data) {
        http_response_code(400);
        echo json_encode(['error' => 'Données JSON invalides']);
        exit;
    }
    
    $prenom = trim($data['prenom'] ?? '');
    $nom = trim($data['nom'] ?? '');
    $email = trim($data['email'] ?? '');
    $adresse = trim($data['adresse'] ?? '');
    $code_postal = trim($data['code_postal'] ?? '');
    $ville = trim($data['ville'] ?? '');
    $pays = trim($data['pays'] ?? '');
    $etage = trim($data['etage'] ?? '');
    $code_entree = trim($data['code_entree'] ?? '');
    $telephone_fixe = trim($data['telephone_fixe'] ?? '');
    $telephone_mobile = trim($data['telephone_mobile'] ?? '');
    $source_acquisition = trim($data['source_acquisition'] ?? '');
    $mode_paiement = trim($data['mode_paiement'] ?? '');
    $avance_imme = isset($data['avance_imme']) ? intval($data['avance_imme']) : 0;
    
    if (empty($nom) || empty($prenom)) {
        http_response_code(400);
        echo json_encode(['error' => 'Nom et prénom requis']);
        exit;
    }
    
    $stmt = $pdo->prepare("
        INSERT INTO clients (nom, prenom, email, adresse, code_postal, ville, pays, etage, code_entree, 
                           telephone_fixe, telephone_mobile, source_acquisition, mode_paiement, avance_imme)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    
    $stmt->execute([
        $nom, $prenom, $email, $adresse, $code_postal, $ville, $pays, 
        $etage, $code_entree, $telephone_fixe, $telephone_mobile,
        $source_acquisition, $mode_paiement, $avance_imme
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
    $nom = trim($data['nom'] ?? '');
    $prenom = trim($data['prenom'] ?? '');
    
    if (empty($nom) || empty($prenom)) {
        http_response_code(400);
        echo json_encode(['error' => 'Nom et prénom requis']);
        exit;
    }
    
    $avance_imme = isset($data['avance_imme']) ? intval($data['avance_imme']) : 0;
    
    $stmt = $pdo->prepare("
        UPDATE clients SET 
            nom = ?, prenom = ?, email = ?, adresse = ?, code_postal = ?, ville = ?, pays = ?,
            etage = ?, code_entree = ?, telephone_fixe = ?, telephone_mobile = ?,
            source_acquisition = ?, mode_paiement = ?, avance_imme = ?
        WHERE id = ?
    ");
    
    $stmt->execute([
        $nom,
        $prenom,
        trim($data['email'] ?? ''),
        trim($data['adresse'] ?? ''),
        trim($data['code_postal'] ?? ''),
        trim($data['ville'] ?? ''),
        trim($data['pays'] ?? ''),
        trim($data['etage'] ?? ''),
        trim($data['code_entree'] ?? ''),
        trim($data['telephone_fixe'] ?? ''),
        trim($data['telephone_mobile'] ?? ''),
        trim($data['source_acquisition'] ?? ''),
        trim($data['mode_paiement'] ?? ''),
        $avance_imme,
        $id
    ]);
    
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
    
    $stmt = $pdo->prepare("DELETE FROM clients WHERE id = ?");
    $stmt->execute([$id]);
    
    echo json_encode(['status' => 'deleted']);
}

function handleUpdateRappel($pdo) {
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($data['client_id'])) {
        http_response_code(400);
        echo json_encode(['error' => 'client_id requis']);
        exit;
    }
    
    $client_id = intval($data['client_id']);
    $commentaire = trim($data['commentaire'] ?? '');
    
    $stmt = $pdo->prepare("
        UPDATE clients 
        SET date_dernier_rappel = NOW(), commentaire_rappel = ?
        WHERE id = ?
    ");
    
    $stmt->execute([$commentaire, $client_id]);
    
    echo json_encode(['success' => true]);
}
