<?php
require_once 'config.php';
header('Content-Type: application/json');

$data = json_decode(file_get_contents('php://input'), true);
if (!$data || !isset($data['id'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid request']);
    exit;
}

$id = (int)$data['id'];
try {
    $pdo = getDBConnection();
    $stmt = $pdo->prepare('DELETE FROM techniciens WHERE id = ?');
    $stmt->execute([$id]);
    echo json_encode(['status' => 'deleted']);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
?>
