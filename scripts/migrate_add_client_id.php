<?php
require_once __DIR__ . '/../config.php';
try {
    $pdo = getDBConnection();
    // check if column exists
    $stmt = $pdo->prepare("SELECT COUNT(*) AS cnt FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = 'rendez_vous' AND COLUMN_NAME = 'client_id'");
    $stmt->execute([DB_NAME]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row && intval($row['cnt']) > 0) {
        echo "Column client_id already exists\n";
        exit(0);
    }

    $pdo->exec("ALTER TABLE rendez_vous ADD COLUMN client_id INT NULL AFTER id_technicien");
    echo "Column client_id added to rendez_vous\n";
} catch (Exception $e) {
    echo "Migration failed: " . $e->getMessage() . "\n";
    exit(1);
}

?>
