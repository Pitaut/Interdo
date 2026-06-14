<?php
require_once __DIR__ . '/../config.php';
try {
    $pdo = getDBConnection();

    // ensure column exists
    $stmt = $pdo->prepare("SELECT COUNT(*) AS cnt FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = 'rendez_vous' AND COLUMN_NAME = 'client_id'");
    $stmt->execute([DB_NAME]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row || intval($row['cnt']) === 0) {
        echo "Column client_id does not exist in rendez_vous. Run migrate_add_client_id.php first.\n";
        exit(1);
    }

    // Add index if missing
    $stmt = $pdo->prepare("SELECT COUNT(*) AS cnt FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = 'rendez_vous' AND COLUMN_NAME = 'client_id'");
    $stmt->execute([DB_NAME]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row || intval($row['cnt']) === 0) {
        $pdo->exec("ALTER TABLE rendez_vous ADD INDEX idx_rendez_vous_client_id (client_id)");
        echo "Index idx_rendez_vous_client_id added.\n";
    } else {
        echo "Index on client_id already exists.\n";
    }

    // Add foreign key if missing
    $stmt = $pdo->prepare("SELECT COUNT(*) AS cnt FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA = ? AND TABLE_NAME = 'rendez_vous' AND COLUMN_NAME = 'client_id' AND REFERENCED_TABLE_NAME = 'clients'");
    $stmt->execute([DB_NAME]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row || intval($row['cnt']) === 0) {
        $pdo->exec("ALTER TABLE rendez_vous ADD CONSTRAINT fk_rendez_vous_client_id FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE SET NULL ON UPDATE CASCADE");
        echo "Foreign key fk_rendez_vous_client_id added.\n";
    } else {
        echo "Foreign key for client_id already exists.\n";
    }

    echo "Migration completed.\n";

} catch (Exception $e) {
    echo "Migration failed: " . $e->getMessage() . "\n";
    exit(1);
}

?>
