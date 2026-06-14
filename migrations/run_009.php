<?php
require_once 'config.php';

try {
    $pdo = getDBConnection();
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "=== Migration 009: techniciens_vehicules (many-to-many) ===\n\n";
    
    // 1. Créer la table
    echo "1. Création de la table techniciens_vehicules...\n";
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS techniciens_vehicules (
            id INT AUTO_INCREMENT PRIMARY KEY,
            id_technicien INT NOT NULL,
            id_vehicule INT NOT NULL,
            date_debut DATE NOT NULL,
            date_fin DATE NULL,
            principal TINYINT(1) DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_technicien (id_technicien),
            INDEX idx_vehicule (id_vehicule),
            INDEX idx_actif (date_fin)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    echo "   ✓ Table créée\n\n";
    
    // 2. Ajouter les contraintes FK
    echo "2. Ajout des contraintes FK...\n";
    try {
        $pdo->exec("
            ALTER TABLE techniciens_vehicules
            ADD CONSTRAINT fk_techveh_tech FOREIGN KEY (id_technicien) REFERENCES techniciens(id) ON DELETE CASCADE,
            ADD CONSTRAINT fk_techveh_veh FOREIGN KEY (id_vehicule) REFERENCES vehicules(id) ON DELETE CASCADE,
            ADD UNIQUE KEY unique_tech_veh_actif (id_technicien, id_vehicule, date_fin)
        ");
        echo "   ✓ Contraintes ajoutées\n\n";
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'Duplicate key name') !== false) {
            echo "   ⚠ Contraintes déjà existantes\n\n";
        } else {
            throw $e;
        }
    }
    
    // 3. Migrer les données existantes
    echo "3. Migration des données existantes...\n";
    $stmt = $pdo->query("SELECT COUNT(*) as nb FROM techniciens_vehicules");
    $count = $stmt->fetch()['nb'];
    
    if ($count == 0) {
        $stmt = $pdo->exec("
            INSERT INTO techniciens_vehicules (id_technicien, id_vehicule, date_debut, principal)
            SELECT id_technicien, id, CURDATE(), 1
            FROM vehicules
            WHERE id_technicien IS NOT NULL
        ");
        echo "   ✓ $stmt lignes migrées\n\n";
    } else {
        echo "   ⚠ Données déjà migrées ($count lignes)\n\n";
    }
    
    // 4. Vérifier si id_technicien existe encore dans vehicules
    echo "4. Suppression de la colonne obsolète vehicules.id_technicien...\n";
    $stmt = $pdo->query("SHOW COLUMNS FROM vehicules LIKE 'id_technicien'");
    if ($stmt->rowCount() > 0) {
        // Supprimer d'abord la FK
        try {
            $pdo->exec("ALTER TABLE vehicules DROP FOREIGN KEY vehicules_ibfk_1");
            echo "   ✓ FK supprimée\n";
        } catch (PDOException $e) {
            echo "   ⚠ FK déjà supprimée ou inexistante\n";
        }
        
        // Supprimer la colonne
        $pdo->exec("ALTER TABLE vehicules DROP COLUMN id_technicien");
        echo "   ✓ Colonne supprimée\n\n";
    } else {
        echo "   ⚠ Colonne déjà supprimée\n\n";
    }
    
    echo "=== Migration terminée avec succès ===\n";
    
} catch (PDOException $e) {
    echo "ERREUR: " . $e->getMessage() . "\n";
    exit(1);
}
