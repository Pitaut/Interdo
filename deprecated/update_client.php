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
$nom = isset($data['nom']) ? trim($data['nom']) : '';
$prenom = isset($data['prenom']) ? trim($data['prenom']) : '';
$email = isset($data['email']) ? trim($data['email']) : '';
$adresse = isset($data['adresse']) ? trim($data['adresse']) : '';
$code_postal = isset($data['code_postal']) ? trim($data['code_postal']) : '';
$ville = isset($data['ville']) ? trim($data['ville']) : '';
$pays = isset($data['pays']) ? trim($data['pays']) : '';
$etage = isset($data['etage']) ? trim($data['etage']) : '';
$code_entree = isset($data['code_entree']) ? trim($data['code_entree']) : '';
$telephone_fixe = isset($data['telephone_fixe']) ? trim($data['telephone_fixe']) : '';
$telephone_mobile = isset($data['telephone_mobile']) ? trim($data['telephone_mobile']) : '';

try {
    $pdo = getDBConnection();
    $stmt = $pdo->prepare('SELECT id FROM clients WHERE id = ?');
    $stmt->execute([$id]);
    if (!$stmt->fetch()) {
        http_response_code(404);
        echo json_encode(['error' => 'Client not found']);
        exit;
    }

    $up = $pdo->prepare('UPDATE clients SET nom = ?, prenom = ?, email = ?, adresse = ?, code_postal = ?, ville = ?, pays = ?, etage = ?, code_entree = ?, telephone_fixe = ?, telephone_mobile = ? WHERE id = ?');
    $up->execute([$nom, $prenom, $email, $adresse, $code_postal, $ville, $pays, $etage, $code_entree, $telephone_fixe, $telephone_mobile, $id]);
    echo json_encode(['status' => 'updated']);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}

?>
