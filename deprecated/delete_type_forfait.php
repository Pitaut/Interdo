<?php
// Endpoint pour supprimer un type de forfait
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
    // Vérifier si le type de forfait est utilisé
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM forfaits_vendus WHERE type_forfait_id = ?");
    $stmt->execute([$id]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($result['count'] > 0) {
        http_response_code(400);
        echo json_encode([
            'error' => 'Ce type de forfait ne peut pas être supprimé car il est utilisé',
            'usage_count' => $result['count']
        ]);
        exit;
    }
    
    // Supprimer le type de forfait
    $stmt = $pdo->prepare("DELETE FROM type_forfait WHERE id = ?");
    $stmt->execute([$id]);
    
    echo json_encode([
        'status' => 'deleted',
        'id' => $id
    ]);
    
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Erreur serveur: ' . $e->getMessage()]);
}
