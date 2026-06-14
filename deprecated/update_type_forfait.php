<?php
// Endpoint pour mettre à jour un type de forfait
require_once 'config.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Méthode non autorisée']);
    exit;
}

$pdo = getDBConnection();

$input = json_decode(file_get_contents('php://input'), true);

$id = intval($input['id'] ?? 0);

if ($id <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'ID requis']);
    exit;
}

try {
    // Construire la requête de mise à jour dynamiquement
    $updates = [];
    $params = [];
    
    if (isset($input['type_forfait'])) {
        $updates[] = "type_forfait = ?";
        $params[] = $input['type_forfait'];
    }
    
    if (isset($input['nbr_heure_forfait'])) {
        $updates[] = "nbr_heure_forfait = ?";
        $params[] = floatval($input['nbr_heure_forfait']);
    }
    
    if (isset($input['prix_forfait'])) {
        $updates[] = "prix_forfait = ?";
        $params[] = floatval($input['prix_forfait']);
    }
    
    if (isset($input['actif'])) {
        $updates[] = "actif = ?";
        $params[] = $input['actif'] ? 1 : 0;
    }
    
    if (empty($updates)) {
        http_response_code(400);
        echo json_encode(['error' => 'Aucune donnée à mettre à jour']);
        exit;
    }
    
    $params[] = $id;
    
    $sql = "UPDATE type_forfait SET " . implode(', ', $updates) . " WHERE id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    
    echo json_encode([
        'status' => 'updated',
        'id' => $id
    ]);
    
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Erreur serveur: ' . $e->getMessage()]);
}
