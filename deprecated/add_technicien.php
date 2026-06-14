<?php
require_once 'config.php';
header('Content-Type: application/json');

$data = json_decode(file_get_contents('php://input'), true);
if (!$data) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid JSON']);
    exit;
}

// Basic validation / sanitization
$nom = trim($data['nom'] ?? '');
$prenom = trim($data['prenom'] ?? '');
$email = trim($data['email'] ?? '');
$adresse = trim($data['adresse'] ?? '');
$code_postal = trim($data['code_postal'] ?? '');
$ville = trim($data['ville'] ?? '');
$pays = trim($data['pays'] ?? '');
$telephone_fixe = trim($data['telephone_fixe'] ?? '');
$telephone_mobile = trim($data['telephone_mobile'] ?? '');
$date_entree = isset($data['date_entree']) && $data['date_entree'] !== null ? trim($data['date_entree']) : null;
$date_sortie = isset($data['date_sortie']) && $data['date_sortie'] !== null ? trim($data['date_sortie']) : null;
$actif = isset($data['actif']) ? (int)$data['actif'] : 1;
$couleur = trim($data['couleur'] ?? '');
$salaire_horaire = isset($data['salaire_horaire']) && $data['salaire_horaire'] !== '' ? (float)$data['salaire_horaire'] : null;

if ($nom === '' || $prenom === '') {
    http_response_code(400);
    echo json_encode(['error' => 'Nom et prenom obligatoires']);
    exit;
}
if (mb_strlen($nom) > 100 || mb_strlen($prenom) > 100) {
    http_response_code(400);
    echo json_encode(['error' => 'Nom/prenom trop long']);
    exit;
}

try {
    $pdo = getDBConnection();
    $stmt = $pdo->prepare("INSERT INTO techniciens (nom, prenom, email, adresse, code_postal, ville, pays, telephone_fixe, telephone_mobile, date_entree, date_sortie, actif, couleur, salaire_horaire) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([$nom, $prenom, $email, $adresse, $code_postal, $ville, $pays, $telephone_fixe, $telephone_mobile, $date_entree ?: null, $date_sortie ?: null, $actif, $couleur, $salaire_horaire]);
    $id = $pdo->lastInsertId();
    echo json_encode(['status' => 'created', 'id' => $id]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
?>
