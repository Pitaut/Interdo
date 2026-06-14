<?php
// Endpoint pour vendre un forfait à un client
require_once 'config.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Méthode non autorisée']);
    exit;
}

$pdo = getDBConnection();

$input = json_decode(file_get_contents('php://input'), true);

$client_id = intval($input['client_id'] ?? 0);
$type_forfait_id = intval($input['type_forfait_id'] ?? 0);
$intervenant_id = isset($input['intervenant_id']) ? intval($input['intervenant_id']) : null;
$date_debut = $input['date_debut'] ?? date('Y-m-d');
$date_fin = $input['date_fin'] ?? null;

// Validation
if ($client_id <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'Client ID requis']);
    exit;
}

if ($type_forfait_id <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'Type de forfait requis']);
    exit;
}

try {
    // Créer la table si elle n'existe pas
    $pdo->exec("CREATE TABLE IF NOT EXISTS forfaits_vendus (
        id INT AUTO_INCREMENT PRIMARY KEY,
        client_id INT NOT NULL,
        type_forfait_id INT NOT NULL,
        heures_total DECIMAL(10,2) NOT NULL,
        heures_restantes DECIMAL(10,2) NOT NULL,
        tarif DECIMAL(10,2) NOT NULL,
        intervenant_id INT DEFAULT NULL,
        signature_client BLOB,
        date_signature DATETIME DEFAULT NULL,
        date_debut DATE DEFAULT NULL,
        date_fin DATE DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_client (client_id),
        INDEX idx_type_forfait (type_forfait_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    // Récupérer les infos du type de forfait
    $stmt = $pdo->prepare("SELECT nbr_heure_forfait, prix_forfait FROM type_forfait WHERE id = ?");
    $stmt->execute([$type_forfait_id]);
    $type_forfait = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$type_forfait) {
        http_response_code(404);
        echo json_encode(['error' => 'Type de forfait non trouvé']);
        exit;
    }
    
    $heures_total = $type_forfait['nbr_heure_forfait'];
    // Utiliser le tarif fourni ou celui du type de forfait par défaut
    $tarif = isset($input['tarif']) ? floatval($input['tarif']) : $type_forfait['prix_forfait'];
    
    // Créer le forfait vendu
    $stmt = $pdo->prepare("
        INSERT INTO forfaits_vendus 
        (client_id, type_forfait_id, heures_total, heures_restantes, tarif, intervenant_id, date_debut, date_fin)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
    ");
    
    $stmt->execute([
        $client_id,
        $type_forfait_id,
        $heures_total,
        $heures_total, // heures_restantes = heures_total au départ
        $tarif,
        $intervenant_id,
        $date_debut,
        $date_fin
    ]);
    
    $id = $pdo->lastInsertId();
    
    echo json_encode([
        'status' => 'created',
        'id' => $id,
        'client_id' => $client_id,
        'heures_total' => $heures_total,
        'heures_restantes' => $heures_total,
        'tarif' => $tarif
    ]);
    
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Erreur serveur: ' . $e->getMessage()]);
}
