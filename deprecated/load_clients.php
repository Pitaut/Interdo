<?php
header('Content-Type: application/json');
require_once 'config.php';

$q = isset($_GET['q']) ? trim($_GET['q']) : '';
$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$limit = isset($_GET['limit']) ? intval($_GET['limit']) : 20;

try {
    $pdo = getDBConnection();
    // ensure table exists to avoid errors if not yet created
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

    if ($id > 0) {
        $stmt = $pdo->prepare('SELECT id, nom, prenom, email, ville, telephone_mobile, telephone_fixe, adresse, code_postal, pays, etage, code_entree FROM clients WHERE id = ?');
        $stmt->execute([$id]);
    } elseif ($q === '') {
        $stmt = $pdo->prepare('SELECT id, nom, prenom, email, ville, telephone_mobile, telephone_fixe, adresse, code_postal FROM clients ORDER BY nom, prenom LIMIT ?');
        $stmt->bindValue(1, $limit, PDO::PARAM_INT);
        $stmt->execute();
    } else {
        $like = '%' . str_replace(' ', '%', $q) . '%';
        $stmt = $pdo->prepare('SELECT id, nom, prenom, email, ville, telephone_mobile, telephone_fixe, adresse, code_postal FROM clients WHERE prenom LIKE ? OR nom LIKE ? OR telephone_mobile LIKE ? OR telephone_fixe LIKE ? OR email LIKE ? OR ville LIKE ? ORDER BY nom, prenom LIMIT ?');
        $stmt->bindValue(1, $like, PDO::PARAM_STR);
        $stmt->bindValue(2, $like, PDO::PARAM_STR);
        $stmt->bindValue(3, $like, PDO::PARAM_STR);
        $stmt->bindValue(4, $like, PDO::PARAM_STR);
        $stmt->bindValue(5, $like, PDO::PARAM_STR);
        $stmt->bindValue(6, $like, PDO::PARAM_STR);
        $stmt->bindValue(7, $limit, PDO::PARAM_INT);
        $stmt->execute();
    }

    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    // normalize display name
    foreach ($rows as &$r) {
        $r['display'] = trim($r['prenom'] . ' ' . $r['nom']);
    }

    echo json_encode(['clients' => $rows]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}

?>
