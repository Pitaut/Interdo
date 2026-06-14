<?php
header("Content-Type: application/json");
require "config.php";

$pdo = getDBConnection();

try {
        $sql = "SELECT rv.id, rv.titre AS title,
            CONCAT(rv.date_rdv, ' ', rv.heure_debut) AS start,
            CONCAT(rv.date_rdv, ' ', rv.heure_fin) AS end,
            rv.statut,
            rv.lieu,
            rv.description,
            rv.id_technicien,
            t.couleur AS tech_couleur
            FROM rendez_vous rv LEFT JOIN techniciens t ON rv.id_technicien = t.id";
    
    $stmt = $pdo->query($sql);
    $events = $stmt->fetchAll();

    echo json_encode($events);
} catch (Exception $e) {
    echo json_encode(["error" => $e->getMessage()]);
    http_response_code(500);
}
?>
