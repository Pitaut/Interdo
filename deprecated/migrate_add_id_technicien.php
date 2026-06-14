<?php
require_once 'config.php';

try {
    $sql = file_get_contents(__DIR__ . '/migrations/001_add_id_technicien.sql');
    if ($sql === false) throw new Exception('Fichier de migration introuvable');

    $pdo = getDBConnection();
    // split statements by semicolon and execute each to support multiple statements in SQL file
    $statements = array_filter(array_map('trim', preg_split('/;\s*\n/', $sql)));
    foreach ($statements as $statement) {
        if ($statement === '' ) continue;
        // use exec for DDL statements
        $pdo->exec($statement);
    }

    echo "Migration exécutée avec succès.\n";
} catch (Exception $e) {
    echo "Erreur lors de la migration: " . $e->getMessage() . "\n";
}
