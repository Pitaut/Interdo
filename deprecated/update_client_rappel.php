<?php
require_once 'config.php';

header('Content-Type: application/json');

try {
    $input = file_get_contents("php://input");
    $data = json_decode($input, true);
    
    if (!isset($data['client_id'])) {
        throw new Exception("Client ID manquant");
    }
    
    $client_id = intval($data['client_id']);
    $commentaire = $data['commentaire'] ?? '';
    
    $pdo = getDBConnection();
    
    // Mettre à jour la date de dernier rappel
    $stmt = $pdo->prepare("
        UPDATE clients 
        SET date_dernier_rappel = NOW(),
            commentaire_rappel = ?
        WHERE id = ?
    ");
    
    $stmt->execute([$commentaire, $client_id]);
    
    echo json_encode(['success' => true]);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['error' => $e->getMessage()]);
}
