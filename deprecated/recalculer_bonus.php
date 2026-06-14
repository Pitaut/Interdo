<?php
/**
 * Script de recalcul des bonus clients et heures restantes
 * 
 * Ce script recalcule les bonus et heures restantes en fonction de l'historique
 * avec la nouvelle logique d'arrondi :
 * - OUI (arrondi sup) : décompte arrondi sup, bonus = différence
 * - NON (arrondi inf) : décompte arrondi inf, bonus = -dépassement
 */

require_once 'config.php';

header('Content-Type: text/html; charset=utf-8');

echo "<h1>Recalcul des bonus clients</h1>";
echo "<pre>";

try {
    $pdo = getDBConnection();
    $pdo->beginTransaction();
    
    // 1. Réinitialiser tous les bonus clients à 0
    echo "Réinitialisation des bonus clients...\n";
    $pdo->exec("UPDATE clients SET heure_bonus = 0");
    
    // 2. Réinitialiser toutes les heures restantes des forfaits
    echo "Réinitialisation des heures restantes des forfaits...\n";
    $pdo->exec("UPDATE forfaits_vendus SET heures_restantes = heures_total");
    
    // 3. Récupérer tout l'historique trié par date
    echo "\nRécupération de l'historique des consommations...\n";
    $stmt = $pdo->query("
        SELECT 
            id,
            rendez_vous_id,
            forfait_vendu_id,
            client_id,
            temps_reel,
            temps_arrondi as temps_arrondi_sup_original,
            difference_arrondi as bonus_original,
            heures_decomptes as heures_decomptes_original,
            created_at
        FROM historique_consommation
        ORDER BY created_at ASC, id ASC
    ");
    
    $historique = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "Total interventions à recalculer : " . count($historique) . "\n\n";
    
    // 4. Pour chaque ligne d'historique, recalculer
    foreach ($historique as $h) {
        echo "---\n";
        echo "ID {$h['id']} - RDV {$h['rendez_vous_id']} - Client {$h['client_id']}\n";
        echo "Temps réel : {$h['temps_reel']}h\n";
        
        $temps_reel = floatval($h['temps_reel']);
        $minutes_reel = $temps_reel * 60;
        
        // Calculer les arrondis
        $tranches_30min_sup = ceil($minutes_reel / 30);
        $temps_arrondi_sup = ($tranches_30min_sup * 30) / 60;
        
        $tranches_30min_inf = floor($minutes_reel / 30);
        $temps_arrondi_inf = ($tranches_30min_inf * 30) / 60;
        
        $difference_arrondi = $temps_arrondi_sup - $temps_reel;
        $depassement = $temps_reel - $temps_arrondi_inf;
        
        // Déterminer si c'était un OUI ou NON basé sur heures_decomptes_original
        $heures_decomptes_original = floatval($h['heures_decomptes_original']);
        
        // Si décompte original = arrondi sup → c'était OUI
        // Si décompte original = temps réel → c'était NON (ancienne logique)
        // Si décompte original = arrondi inf → c'était NON (nouvelle logique)
        
        $etait_arrondi_sup = abs($heures_decomptes_original - $temps_arrondi_sup) < 0.01;
        $etait_arrondi_inf = abs($heures_decomptes_original - $temps_arrondi_inf) < 0.01;
        $etait_temps_reel = abs($heures_decomptes_original - $temps_reel) < 0.01;
        
        if ($etait_arrondi_sup) {
            // OUI : décompter arrondi sup, bonus = différence
            $heures_a_decompter = $temps_arrondi_sup;
            $bonus_a_ajouter = $difference_arrondi;
            $choix = "OUI (arrondi sup)";
        } else if ($etait_arrondi_inf || $etait_temps_reel) {
            // NON : décompter arrondi inf, bonus = -dépassement
            $heures_a_decompter = $temps_arrondi_inf;
            $bonus_a_ajouter = -$depassement;
            $choix = "NON (arrondi inf)";
        } else {
            // Cas bizarre, on garde l'original
            $heures_a_decompter = $heures_decomptes_original;
            $bonus_a_ajouter = floatval($h['bonus_original']);
            $choix = "INCONNU (conservé)";
        }
        
        echo "Choix détecté : $choix\n";
        echo "Arrondi sup : {$temps_arrondi_sup}h, Arrondi inf : {$temps_arrondi_inf}h\n";
        echo "Décompte : {$heures_a_decompter}h, Bonus : " . ($bonus_a_ajouter >= 0 ? '+' : '') . round($bonus_a_ajouter * 60) . "min\n";
        
        // Mettre à jour le forfait
        if ($h['forfait_vendu_id']) {
            $stmt = $pdo->prepare("
                UPDATE forfaits_vendus 
                SET heures_restantes = heures_restantes - ?
                WHERE id = ?
            ");
            $stmt->execute([$heures_a_decompter, $h['forfait_vendu_id']]);
            echo "Forfait {$h['forfait_vendu_id']} : -{$heures_a_decompter}h\n";
        }
        
        // Mettre à jour le bonus client
        if ($bonus_a_ajouter != 0) {
            $stmt = $pdo->prepare("
                UPDATE clients 
                SET heure_bonus = heure_bonus + ?
                WHERE id = ?
            ");
            $stmt->execute([$bonus_a_ajouter, $h['client_id']]);
            echo "Client {$h['client_id']} : bonus " . ($bonus_a_ajouter >= 0 ? '+' : '') . round($bonus_a_ajouter * 60) . "min\n";
        }
        
        // Mettre à jour l'historique avec les nouvelles valeurs
        $stmt = $pdo->prepare("
            UPDATE historique_consommation
            SET temps_arrondi = ?,
                difference_arrondi = ?,
                heures_decomptes = ?
            WHERE id = ?
        ");
        $stmt->execute([
            $temps_arrondi_sup,
            $bonus_a_ajouter,
            $heures_a_decompter,
            $h['id']
        ]);
    }
    
    // 4.5. Mettre à jour les statuts des rendez-vous clôturés
    echo "\n\nMise à jour des statuts des rendez-vous clôturés...\n";
    $stmt = $pdo->exec("
        UPDATE rendez_vous r 
        INNER JOIN historique_consommation hc ON r.id = hc.rendez_vous_id 
        SET r.statut = 'termine' 
        WHERE r.statut != 'termine'
    ");
    echo "Statuts mis à jour : $stmt interventions\n";
    
    // 5. Afficher le résumé final
    echo "\n\n=== RÉSUMÉ FINAL ===\n\n";
    
    $stmt = $pdo->query("
        SELECT 
            c.id,
            c.prenom,
            c.nom,
            c.heure_bonus,
            COUNT(fv.id) as nb_forfaits,
            SUM(fv.heures_total) as total_heures,
            SUM(fv.heures_restantes) as heures_restantes
        FROM clients c
        LEFT JOIN forfaits_vendus fv ON c.id = fv.client_id
        WHERE c.heure_bonus != 0 OR fv.id IS NOT NULL
        GROUP BY c.id
        ORDER BY c.id
    ");
    
    $clients = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($clients as $client) {
        $bonus_min = round($client['heure_bonus'] * 60);
        echo "Client #{$client['id']} - {$client['prenom']} {$client['nom']}\n";
        echo "  Bonus : " . ($client['heure_bonus'] >= 0 ? '+' : '') . "{$bonus_min} minutes ({$client['heure_bonus']}h)\n";
        echo "  Forfaits : {$client['nb_forfaits']} - Total: {$client['total_heures']}h - Restantes: {$client['heures_restantes']}h\n";
        echo "\n";
    }
    
    $pdo->commit();
    echo "\n✓ Recalcul terminé avec succès !\n";
    
} catch (Exception $e) {
    if (isset($pdo)) {
        $pdo->rollBack();
    }
    echo "\n✗ ERREUR : " . $e->getMessage() . "\n";
    echo $e->getTraceAsString();
}

echo "</pre>";
echo '<br><a href="agenda.php">← Retour à l\'agenda</a>';
