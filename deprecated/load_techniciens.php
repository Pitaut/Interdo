<?php
require_once 'config.php';
header('Content-Type: application/json');

try {
    $pdo = getDBConnection();
    
    // Récupérer le paramètre pour filtrer actifs uniquement
    $actifs_only = isset($_GET['actifs_only']) && $_GET['actifs_only'] === '1';
    
    if ($actifs_only) {
        // Pour l'agenda : seulement les techniciens actifs et dans leur période
        $stmt = $pdo->prepare("
            SELECT * FROM techniciens 
            WHERE actif = 1 
            AND (date_entree IS NULL OR date_entree <= CURDATE())
            AND (date_sortie IS NULL OR date_sortie > CURDATE())
            ORDER BY nom, prenom
        ");
        $stmt->execute();
    } else {
        // Pour la liste complète : tous les techniciens
        $stmt = $pdo->query('SELECT * FROM techniciens ORDER BY actif DESC, nom, prenom');
    }
    
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Enrichir avec le statut calculé
    foreach ($rows as &$tech) {
        $today = date('Y-m-d');
        $entree = $tech['date_entree'];
        $sortie = $tech['date_sortie'];
        
        $tech['est_actif_periode'] = true;
        
        if ($entree && $entree > $today) {
            $tech['est_actif_periode'] = false;
            $tech['statut_label'] = 'Pas encore entré';
        } elseif ($sortie && $sortie <= $today) {
            $tech['est_actif_periode'] = false;
            $tech['statut_label'] = 'Sorti le ' . date('d/m/Y', strtotime($sortie));
        } elseif (!$tech['actif']) {
            $tech['est_actif_periode'] = false;
            $tech['statut_label'] = 'Désactivé';
        } else {
            $tech['statut_label'] = 'Actif';
        }
    }
    
    echo json_encode($rows);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
?>
