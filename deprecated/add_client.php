<?php
header('Content-Type: application/json');
require_once 'config.php';

// Accept JSON POST to create a client
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed, use POST']);
    exit;
}

$content = file_get_contents('php://input');
$data = json_decode($content, true);
if (!$data) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid JSON']);
    exit;
}

$prenom = isset($data['prenom']) ? trim($data['prenom']) : '';
$nom = isset($data['nom']) ? trim($data['nom']) : '';
$email = isset($data['email']) ? trim($data['email']) : '';
$adresse = isset($data['adresse']) ? trim($data['adresse']) : '';
$code_postal = isset($data['code_postal']) ? trim($data['code_postal']) : '';
$ville = isset($data['ville']) ? trim($data['ville']) : '';
$pays = isset($data['pays']) ? trim($data['pays']) : '';
$etage = isset($data['etage']) ? trim($data['etage']) : '';
$code_entree = isset($data['code_entree']) ? trim($data['code_entree']) : '';
$telephone_fixe = isset($data['telephone_fixe']) ? trim($data['telephone_fixe']) : '';
$telephone_mobile = isset($data['telephone_mobile']) ? trim($data['telephone_mobile']) : '';

if ($prenom === '' && $nom === '') {
    http_response_code(400);
    echo json_encode(['error' => 'Prénom ou nom requis']);
    exit;
}

try {
    $pdo = getDBConnection();
    // ensure table exists (safe to run multiple times)
    $pdo->exec("CREATE TABLE IF NOT EXISTS clients (
        id INT AUTO_INCREMENT PRIMARY KEY,
        nom VARCHAR(100) NOT NULL,
        prenom VARCHAR(100) NOT NULL,
        email VARCHAR(100) DEFAULT NULL,
        adresse TEXT,
        code_postal VARCHAR(20) DEFAULT NULL,
        ville VARCHAR(100) DEFAULT NULL,
        pays VARCHAR(100) DEFAULT NULL,
        etage VARCHAR(20) DEFAULT NULL,
        code_entree VARCHAR(50) DEFAULT NULL,
        telephone_fixe VARCHAR(20) DEFAULT NULL,
        telephone_mobile VARCHAR(20) DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $stmt = $pdo->prepare('INSERT INTO clients (nom, prenom, email, adresse, code_postal, ville, pays, etage, code_entree, telephone_fixe, telephone_mobile) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
    $stmt->execute([$nom, $prenom, $email, $adresse, $code_postal, $ville, $pays, $etage, $code_entree, $telephone_fixe, $telephone_mobile]);
    $newId = $pdo->lastInsertId();
    echo json_encode(['status' => 'created', 'id' => $newId]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}

?>
