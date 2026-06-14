<?php
// Endpoint pour créer un nouveau type de forfait
require_once 'config.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Méthode non autorisée']);
    exit;
}

$pdo = getDBConnection();

$input = json_decode(file_get_contents('php://input'), true);

$type_forfait = trim($input['type_forfait'] ?? $input['nom'] ?? '');
$nbr_heure_forfait = floatval($input['nbr_heure_forfait'] ?? $input['nombre_heures'] ?? 0);
$prix_forfait = floatval($input['prix_forfait'] ?? $input['prix'] ?? 0);
$actif = isset($input['actif']) ? (bool)$input['actif'] : true;

// Validation
if (empty($type_forfait)) {
    http_response_code(400);
    echo json_encode(['error' => 'Le nom du forfait est requis']);
    exit;
}

if ($nbr_heure_forfait <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'Le nombre d\'heures doit être supérieur à 0']);
    exit;
}

if ($prix_forfait <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'Le prix doit être supérieur à 0']);
    exit;
}

try {
    // Créer la table si elle n'existe pas
    $pdo->exec("CREATE TABLE IF NOT EXISTS type_forfait (
        id INT AUTO_INCREMENT PRIMARY KEY,
        type_forfait VARCHAR(100) NOT NULL,
        nbr_heure_forfait DECIMAL(10,2) NOT NULL,
        prix_forfait DECIMAL(10,2) NOT NULL,
        actif BOOLEAN DEFAULT TRUE,
        date_creation TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $stmt = $pdo->prepare("
        INSERT INTO type_forfait (type_forfait, nbr_heure_forfait, prix_forfait, actif)
        VALUES (?, ?, ?, ?)
    ");
    
    $stmt->execute([$type_forfait, $nbr_heure_forfait, $prix_forfait, $actif ? 1 : 0]);
    
    $id = $pdo->lastInsertId();
    
    echo json_encode([
        'status' => 'created',
        'id' => $id,
        'type_forfait' => $type_forfait,
        'nbr_heure_forfait' => $nbr_heure_forfait,
        'prix_forfait' => $prix_forfait
    ]);
    
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Erreur serveur: ' . $e->getMessage()]);
}
