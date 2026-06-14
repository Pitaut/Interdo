<?php
// Endpoint pour charger les forfaits d'un client
require_once 'config.php';

header('Content-Type: application/json');

try {
    $pdo = getDBConnection();

    $client_id = intval($_GET['client_id'] ?? 0);

    if ($client_id <= 0) {
        http_response_code(400);
        echo json_encode(['error' => 'Client ID requis']);
        exit;
    }

    // S'assurer que la table type_forfait existe
    $pdo->exec("CREATE TABLE IF NOT EXISTS type_forfait (
        id INT AUTO_INCREMENT PRIMARY KEY,
        type_forfait VARCHAR(100) NOT NULL,
        nbr_heure_forfait DECIMAL(10,2) NOT NULL,
        prix_forfait DECIMAL(10,2) NOT NULL,
        actif BOOLEAN DEFAULT TRUE,
        date_creation TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    // S'assurer que la table forfaits_vendus existe
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

    // S'assurer que le champ heure_bonus existe dans la table clients
    $stmt = $pdo->query("SHOW COLUMNS FROM clients LIKE 'heure_bonus'");
    if ($stmt->rowCount() === 0) {
        $pdo->exec("ALTER TABLE clients ADD COLUMN heure_bonus DECIMAL(10,2) DEFAULT 0.00");
    }

    // Récupérer tous les forfaits du client avec les détails du type de forfait
    $stmt = $pdo->prepare("
        SELECT 
            fv.id,
            fv.client_id,
            fv.type_forfait_id,
            fv.heures_total,
            fv.heures_restantes,
            fv.tarif,
            fv.date_debut,
            fv.date_fin,
            fv.created_at,
            tf.type_forfait AS type_forfait_nom,
            tf.nbr_heure_forfait AS type_forfait_heures,
            tf.prix_forfait AS type_forfait_prix
        FROM forfaits_vendus fv
        LEFT JOIN type_forfait tf ON fv.type_forfait_id = tf.id
        WHERE fv.client_id = ? AND fv.heures_restantes > 0
        ORDER BY fv.created_at DESC
    ");
    
    $stmt->execute([$client_id]);
    $forfaits = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Calculer les totaux
    $total_heures_restantes = 0;
    $total_heures_consommees = 0;
    $forfait_actif = null;
    
    foreach ($forfaits as $f) {
        $total_heures_restantes += floatval($f['heures_restantes']);
        if (!$forfait_actif && floatval($f['heures_restantes']) > 0) {
            $forfait_actif = $f;
        }
        $total_heures_consommees += floatval($f['heures_total']) - floatval($f['heures_restantes']);
    }
    
    // Récupérer le heure_bonus du client
    $stmt = $pdo->prepare("SELECT heure_bonus FROM clients WHERE id = ?");
    $stmt->execute([$client_id]);
    $client = $stmt->fetch(PDO::FETCH_ASSOC);
    $heure_bonus = $client ? floatval($client['heure_bonus'] ?? 0) : 0;
    
    echo json_encode([
        'forfaits' => $forfaits,
        'forfait_actif' => $forfait_actif,
        'total_heures_restantes' => round($total_heures_restantes, 2),
        'total_heures_consommees' => round($total_heures_consommees, 2),
        'heure_bonus' => round($heure_bonus, 2),
        'heure_bonus_minutes' => round($heure_bonus * 60, 0)
    ]);
    
} catch (PDOException $e) {
    error_log("Erreur load_forfaits.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Erreur serveur: ' . $e->getMessage()]);
} catch (Exception $e) {
    error_log("Erreur load_forfaits.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Erreur: ' . $e->getMessage()]);
}
