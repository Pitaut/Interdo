<?php
/**
 * Migration automatique : Ajout des colonnes signature_client
 * Ce fichier est inclus dans config.php pour exécuter les migrations automatiquement
 */

function executeMigrationSignatures($pdo) {
    try {
        // Vérifier si la colonne existe déjà dans rendez_vous
        $stmt = $pdo->query("SHOW COLUMNS FROM rendez_vous LIKE 'signature_client'");
        if ($stmt->rowCount() === 0) {
            // Ajouter la colonne
            $pdo->exec("ALTER TABLE rendez_vous ADD COLUMN signature_client LONGTEXT DEFAULT NULL COMMENT 'Signature client en base64 (clôture intervention)'");
            error_log("Migration: Colonne signature_client ajoutée à la table rendez_vous");
        }
        
        // Vérifier pour forfaits_vendus (normalement déjà présente depuis structure.sql)
        $stmt = $pdo->query("SHOW COLUMNS FROM forfaits_vendus LIKE 'signature_client'");
        if ($stmt->rowCount() === 0) {
            $pdo->exec("ALTER TABLE forfaits_vendus ADD COLUMN signature_client LONGTEXT DEFAULT NULL COMMENT 'Signature client en base64 (vente forfait)'");
            error_log("Migration: Colonne signature_client ajoutée à la table forfaits_vendus");
        }
        
        // Vérifier la colonne date_signature
        $stmt = $pdo->query("SHOW COLUMNS FROM forfaits_vendus LIKE 'date_signature'");
        if ($stmt->rowCount() === 0) {
            $pdo->exec("ALTER TABLE forfaits_vendus ADD COLUMN date_signature DATETIME DEFAULT NULL COMMENT 'Date de la signature client'");
            error_log("Migration: Colonne date_signature ajoutée à la table forfaits_vendus");
        }
        
        return true;
    } catch (PDOException $e) {
        error_log("Erreur migration signatures: " . $e->getMessage());
        return false;
    }
}

// Auto-exécution si ce fichier est inclus
if (isset($pdo) && $pdo instanceof PDO) {
    executeMigrationSignatures($pdo);
}
