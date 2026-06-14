<?php
header('Content-Type: application/json');
require_once 'config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
if (!$data || !isset($data['id'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing id']);
    exit;
}

$id = intval($data['id']);
try {
    $pdo = getDBConnection();
    $stmt = $pdo->prepare('SELECT id FROM clients WHERE id = ?');
    $stmt->execute([$id]);
    if (!$stmt->fetch()) {
        http_response_code(404);
        echo json_encode(['error' => 'Client not found']);
        exit;
    }

    // delete the client; if FK exists with ON DELETE SET NULL it will be handled
    $del = $pdo->prepare('DELETE FROM clients WHERE id = ?');
    $del->execute([$id]);
    echo json_encode(['status' => 'deleted']);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}

?>
