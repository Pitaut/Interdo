<?php
/**
 * Endpoint pour clôturer une intervention en facturation hors forfait
 * Règle : 1ère heure toujours facturée 1h, puis par tranches de 30min
 */

require_once 'config.php';

header('Content-Type: application/json');

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['error' => 'Méthode non autorisée']);
        exit;
    }

    $pdo = getDBConnection();
    $input = json_decode(file_get_contents('php://input'), true);

    $rendez_vous_id = intval($input['rendez_vous_id'] ?? 0);
    $heure_debut = $input['heure_debut'] ?? null;
    $heure_fin = $input['heure_fin'] ?? null;
    $tarif_horaire = floatval($input['tarif_horaire'] ?? 0);

    // Validation
    if ($rendez_vous_id <= 0) {
        http_response_code(400);
        echo json_encode(['error' => 'ID du rendez-vous requis']);
        exit;
    }

    if (!$heure_debut || !$heure_fin) {
        http_response_code(400);
        echo json_encode(['error' => 'Heures de début et fin requises']);
        exit;
    }

    if ($tarif_horaire <= 0) {
        http_response_code(400);
        echo json_encode(['error' => 'Tarif horaire requis']);
        exit;
    }

    // Créer la table si nécessaire
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS facturation_hors_forfait (
            id INT AUTO_INCREMENT PRIMARY KEY,
            rendez_vous_id INT NOT NULL,
            client_id INT NOT NULL,
            date_intervention DATE NOT NULL,
            heure_debut TIME NOT NULL,
            heure_fin TIME NOT NULL,
            duree_reelle DECIMAL(10,2) NOT NULL,
            quantite DECIMAL(10,2) NOT NULL,
            tarif_horaire DECIMAL(10,2) NOT NULL,
            montant_total DECIMAL(10,2) NOT NULL,
            paye BOOLEAN DEFAULT 0,
            date_paiement DATETIME NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_client (client_id),
            INDEX idx_rdv (rendez_vous_id),
            INDEX idx_paye (paye)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    $pdo->beginTransaction();

    // Vérifier que l'intervention n'est pas déjà clôturée
    $stmt = $pdo->prepare("SELECT id FROM facturation_hors_forfait WHERE rendez_vous_id = ?");
    $stmt->execute([$rendez_vous_id]);
    if ($stmt->fetch()) {
        $pdo->rollBack();
        http_response_code(400);
        echo json_encode(['error' => 'Cette intervention a déjà été facturée']);
        exit;
    }

    // Récupérer les détails du rendez-vous
    $stmt = $pdo->prepare("
        SELECT date_rdv, client_id, statut
        FROM rendez_vous 
        WHERE id = ?
    ");
    $stmt->execute([$rendez_vous_id]);
    $rdv = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$rdv) {
        $pdo->rollBack();
        http_response_code(404);
        echo json_encode(['error' => 'Rendez-vous non trouvé']);
        exit;
    }

    if (!$rdv['client_id']) {
        $pdo->rollBack();
        http_response_code(400);
        echo json_encode(['error' => 'Aucun client associé à ce rendez-vous']);
        exit;
    }

    // Calculer la durée réelle
    list($hd, $md) = explode(':', $heure_debut);
    list($hf, $mf) = explode(':', $heure_fin);
    $minutes_debut = ($hd * 60) + $md;
    $minutes_fin = ($hf * 60) + $mf;
    $duree_minutes = $minutes_fin - $minutes_debut;

    if ($duree_minutes <= 0) {
        $pdo->rollBack();
        http_response_code(400);
        echo json_encode(['error' => 'Durée invalide']);
        exit;
    }

    $duree_reelle = $duree_minutes / 60; // En heures décimales

    // Calculer la quantité à facturer selon la règle :
    // - Première heure : toujours 1h
    // - Au-delà : par tranches de 30min
    
    if ($duree_reelle <= 1) {
        // Moins d'1h ou exactement 1h → facturer 1h
        $quantite = 1.0;
    } else {
        // Plus d'1h → 1h + tranches de 30min
        $au_dela = $duree_reelle - 1; // Ce qui dépasse 1h
        $tranches_30min = ceil(($au_dela * 60) / 30); // Arrondir au 30min sup
        $quantite = 1.0 + ($tranches_30min * 0.5);
    }

    $montant_total = $quantite * $tarif_horaire;

    // Enregistrer la facturation
    $stmt = $pdo->prepare("
        INSERT INTO facturation_hors_forfait 
        (rendez_vous_id, client_id, date_intervention, heure_debut, heure_fin, 
         duree_reelle, quantite, tarif_horaire, montant_total, paye)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 0)
    ");
    $stmt->execute([
        $rendez_vous_id,
        $rdv['client_id'],
        $rdv['date_rdv'],
        $heure_debut,
        $heure_fin,
        $duree_reelle,
        $quantite,
        $tarif_horaire,
        $montant_total
    ]);

    // Mettre à jour le statut du rendez-vous
    if ($rdv['statut'] !== 'termine') {
        $stmt = $pdo->prepare("UPDATE rendez_vous SET statut = 'termine' WHERE id = ?");
        $stmt->execute([$rendez_vous_id]);
    }

    $pdo->commit();

    echo json_encode([
        'status' => 'facture',
        'rendez_vous_id' => $rendez_vous_id,
        'duree_reelle' => round($duree_reelle, 2),
        'quantite' => round($quantite, 2),
        'tarif_horaire' => round($tarif_horaire, 2),
        'montant_total' => round($montant_total, 2),
        'detail' => $duree_reelle <= 1 
            ? "Moins d'1h → facturé 1h" 
            : sprintf("1h + %d tranches de 30min", ($quantite - 1) * 2)
    ]);

} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log("Erreur close_hors_forfait: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Erreur serveur : ' . $e->getMessage()]);
}
