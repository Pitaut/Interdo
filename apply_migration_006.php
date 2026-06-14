<?php
require_once 'config.php';
$pdo = getDBConnection();

echo "=== APPLICATION MIGRATION ===\n\n";

try {
    $pdo->exec("ALTER TABLE historique_consommation MODIFY COLUMN forfait_vendu_id INT NULL");
    echo "✓ Migration appliquée avec succès\n";
    echo "  forfait_vendu_id peut maintenant être NULL\n";
} catch (PDOException $e) {
    echo "✗ Erreur: " . $e->getMessage() . "\n";
}
