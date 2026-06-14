<?php
// Endpoint pour marquer un forfait comme payé
require_once 'config.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Méthode non autorisée']);
    exit;
}

$pdo = getDBConnection();

$input = json_decode(file_get_contents('php://input'), true);
$forfait_id = intval($input['forfait_id'] ?? 0);

if ($forfait_id <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'ID du forfait requis']);
    exit;
}

try {
    // Ajouter la colonne paye si elle n'existe pas
    $stmt = $pdo->query("SHOW COLUMNS FROM forfaits_vendus LIKE 'paye'");
    if ($stmt->rowCount() === 0) {
        $pdo->exec("ALTER TABLE forfaits_vendus ADD COLUMN paye BOOLEAN DEFAULT FALSE");
    }
    
    // Ajouter la colonne date_paiement si elle n'existe pas
    $stmt = $pdo->query("SHOW COLUMNS FROM forfaits_vendus LIKE 'date_paiement'");
    if ($stmt->rowCount() === 0) {
        $pdo->exec("ALTER TABLE forfaits_vendus ADD COLUMN date_paiement DATETIME DEFAULT NULL");
    }
    
    // Marquer le forfait comme payé
    $stmt = $pdo->prepare("
        UPDATE forfaits_vendus 
        SET paye = TRUE, date_paiement = NOW() 
        WHERE id = ?
    ");
    
    $stmt->execute([$forfait_id]);
    
    if ($stmt->rowCount() === 0) {
        http_response_code(404);
        echo json_encode(['error' => 'Forfait non trouvé']);
        exit;
    }
    
    echo json_encode([
        'status' => 'success',
        'id' => $forfait_id,
        'date_paiement' => date('Y-m-d H:i:s')
    ]);
    
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Erreur serveur: ' . $e->getMessage()]);
}
