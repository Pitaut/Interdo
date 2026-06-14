<?php
header('Content-Type: application/json');
require __DIR__ . '/../config.php';

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($id <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing or invalid id parameter, use ?id=20']);
    exit;
}

try {
    $pdo = getDBConnection();
    $stmt = $pdo->prepare('SELECT id, date_rdv, heure_debut, heure_fin, id_technicien FROM rendez_vous WHERE id = ?');
    $stmt->execute([$id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        http_response_code(404);
        echo json_encode(['error' => 'Not found']);
        exit;
    }
    echo json_encode(['row' => $row]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}

?>
