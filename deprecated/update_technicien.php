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
$fields = [];
$vals = [];

$allowedFields = ['nom','prenom','email','adresse','code_postal','ville','pays','telephone_fixe','telephone_mobile','date_entree','date_sortie','actif','couleur','salaire_horaire'];
foreach ($allowedFields as $f) {
    if (array_key_exists($f, $data)) {
        $fields[] = "$f = ?";
        $vals[] = $data[$f] === '' ? null : $data[$f];
    }
}

if (empty($fields)) {
    echo json_encode(['status' => 'no_changes']);
    exit;
}

try {
    $pdo = getDBConnection();
    $sql = "UPDATE techniciens SET " . implode(', ', $fields) . " WHERE id = ?";
    $vals[] = $id;
    $stmt = $pdo->prepare($sql);
    $stmt->execute($vals);
    echo json_encode(['status' => 'updated']);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
?>
