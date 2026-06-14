<?php
// Endpoint pour clôturer une intervention avec décompte des heures de forfait
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
    $heure_debut_custom = $input['heure_debut'] ?? null;
    $heure_fin_custom = $input['heure_fin'] ?? null;
    $appliquer_arrondi = isset($input['appliquer_arrondi']) ? (bool)$input['appliquer_arrondi'] : true;
    $force_cloture = isset($input['force_cloture']) ? (bool)$input['force_cloture'] : false;

    // Validation
    if ($rendez_vous_id <= 0) {
        http_response_code(400);
        echo json_encode(['error' => 'ID du rendez-vous requis']);
        exit;
    }

    // Créer les tables AVANT de démarrer la transaction
    // (CREATE TABLE fait un commit implicite en MySQL)
    
    // Créer la table type_forfait si elle n'existe pas
    $pdo->exec("CREATE TABLE IF NOT EXISTS type_forfait (
        id INT AUTO_INCREMENT PRIMARY KEY,
        nom VARCHAR(100) NOT NULL,
        nombre_heures DECIMAL(10,2) NOT NULL,
        prix DECIMAL(10,2) NOT NULL,
        description TEXT,
        actif BOOLEAN DEFAULT TRUE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_actif (actif)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    
    // Créer la table forfaits_vendus si elle n'existe pas
    $pdo->exec("CREATE TABLE IF NOT EXISTS forfaits_vendus (
        id INT AUTO_INCREMENT PRIMARY KEY,
        client_id INT NOT NULL,
        type_forfait_id INT NOT NULL,
        heures_total DECIMAL(10,2) NOT NULL,
        heures_restantes DECIMAL(10,2) NOT NULL,
        tarif DECIMAL(10,2),
        date_debut DATE,
        date_fin DATE,
        intervenant_id INT,
        signature_client BLOB,
        date_signature DATETIME,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_client (client_id),
        INDEX idx_type_forfait (type_forfait_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    
    // Créer la table historique_consommation si elle n'existe pas
    $pdo->exec("CREATE TABLE IF NOT EXISTS historique_consommation (
        id INT AUTO_INCREMENT PRIMARY KEY,
        rendez_vous_id INT NOT NULL,
        forfait_vendu_id INT NOT NULL,
        client_id INT NOT NULL,
        temps_reel DECIMAL(10,2) NOT NULL,
        temps_arrondi DECIMAL(10,2) NOT NULL,
        difference_arrondi DECIMAL(10,2) NOT NULL,
        heures_decomptes DECIMAL(10,2) NOT NULL,
        heures_avant DECIMAL(10,2) NOT NULL,
        heures_apres DECIMAL(10,2) NOT NULL,
        date_rdv DATE NOT NULL,
        heure_debut TIME NOT NULL,
        heure_fin TIME NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_forfait (forfait_vendu_id),
        INDEX idx_client (client_id),
        INDEX idx_rdv (rendez_vous_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    
    // Vérifier et ajouter la colonne heure_bonus si nécessaire
    $stmt = $pdo->query("SHOW COLUMNS FROM clients LIKE 'heure_bonus'");
    if ($stmt->rowCount() === 0) {
        error_log("Ajout colonne heure_bonus dans clients");
        $pdo->exec("ALTER TABLE clients ADD COLUMN heure_bonus DECIMAL(10,2) DEFAULT 0.00 COMMENT 'Cumul des différences d''arrondi (en heures décimales)'");
    }
    
    // DÉMARRER LA TRANSACTION maintenant que les tables existent
    $pdo->beginTransaction();
    
    // Vérifier si l'intervention a déjà été clôturée
    $stmt = $pdo->prepare("SELECT id FROM historique_consommation WHERE rendez_vous_id = ?");
    $stmt->execute([$rendez_vous_id]);
    if ($stmt->fetch()) {
        $pdo->rollBack();
        http_response_code(400);
        echo json_encode(['error' => 'Cette intervention a déjà été clôturée']);
        exit;
    }
    
    // Récupérer les détails du rendez-vous
    $stmt = $pdo->prepare("
        SELECT date_rdv, heure_debut, heure_fin, client_id, statut
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
    
    // Utiliser les heures personnalisées si fournies, sinon celles du RDV
    $heure_debut_calc = $heure_debut_custom ?? $rdv['heure_debut'];
    $heure_fin_calc = $heure_fin_custom ?? $rdv['heure_fin'];
    
    // Mettre à jour les heures du rendez-vous si personnalisées
    if ($heure_debut_custom && $heure_fin_custom) {
        $stmt = $pdo->prepare("UPDATE rendez_vous SET heure_debut = ?, heure_fin = ? WHERE id = ?");
        $stmt->execute([$heure_debut_custom, $heure_fin_custom, $rendez_vous_id]);
    }
    
    // Calculer le temps réel en heures
    $debut = new DateTime($rdv['date_rdv'] . ' ' . $heure_debut_calc);
    $fin = new DateTime($rdv['date_rdv'] . ' ' . $heure_fin_calc);
    $interval = $debut->diff($fin);
    
    // Convertir en heures décimales
    $temps_reel = $interval->h + ($interval->i / 60) + ($interval->s / 3600);
    
    // Arrondir au 30 minutes supérieures
    // Logique : si on a 1h15, on arrondit à 1h30 (1.5h)
    // si on a 1h31, on arrondit à 2h00
    $minutes_reel = ($temps_reel * 60);
    $tranches_30min_sup = ceil($minutes_reel / 30);
    $temps_arrondi_sup = ($tranches_30min_sup * 30) / 60; // Arrondi supérieur
    
    // Calculer aussi l'arrondi inférieur pour le bonus NON
    $tranches_30min_inf = floor($minutes_reel / 30);
    $temps_arrondi_inf = ($tranches_30min_inf * 30) / 60; // Arrondi inférieur
    
    // Différence entre temps réel et arrondi supérieur
    $difference_arrondi = $temps_arrondi_sup - $temps_reel;
    
    // Dépassement par rapport à l'arrondi inférieur (pour bonus NON)
    $depassement = $temps_reel - $temps_arrondi_inf;
    
    // Déterminer les heures à décompter selon le choix de l'utilisateur
    // OUI : facturer l'arrondi supérieur (ex: 1h08 → 1h30)
    // NON : facturer l'arrondi inférieur (ex: 1h08 → 1h00)
    $heures_decomptes = $appliquer_arrondi ? $temps_arrondi_sup : $temps_arrondi_inf;
    
    // OUI (arrondi sup) : bonus = différence pour compenser le surplus facturé
    // NON (arrondi inf) : bonus négatif = dépassement non facturé
    $bonus_a_ajouter = $appliquer_arrondi ? $difference_arrondi : -$depassement;
    
    // Récupérer TOUS les forfaits actifs du client (par ordre de création pour FIFO)
    $stmt = $pdo->prepare("
        SELECT id, heures_restantes, type_forfait_id
        FROM forfaits_vendus 
        WHERE client_id = ? AND heures_restantes > 0
        ORDER BY created_at ASC
    ");
    $stmt->execute([$rdv['client_id']]);
    $forfaits_disponibles = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Calculer le total d'heures disponibles
    $total_heures_disponibles = 0;
    foreach ($forfaits_disponibles as $f) {
        $total_heures_disponibles += floatval($f['heures_restantes']);
    }
    
    if (count($forfaits_disponibles) === 0 || $total_heures_disponibles <= 0) {
        if ($force_cloture) {
            // Créer un forfait virtuel avec solde négatif pour permettre la clôture
            $stmt = $pdo->prepare("
                INSERT INTO forfaits_vendus 
                (client_id, type_forfait_id, heures_total, heures_restantes, tarif, paye, created_at)
                VALUES (?, NULL, 0, 0, 0, 0, NOW())
            ");
            $stmt->execute([$rdv['client_id']]);
            $forfait_id = $pdo->lastInsertId();
            $forfaits_disponibles = [['id' => $forfait_id, 'heures_restantes' => 0, 'type_forfait_id' => null]];
            $total_heures_disponibles = 0;
        } else {
            $pdo->rollBack();
            http_response_code(400);
            echo json_encode([
                'error' => 'Aucun forfait actif trouvé pour ce client',
                'besoin_nouveau_forfait' => true,
                'client_id' => $rdv['client_id'],
                'temps_reel' => $temps_reel,
                'temps_arrondi' => $temps_arrondi_sup
            ]);
            exit;
        }
    }
    
    // Vérifier si le total des forfaits est suffisant
    if ($total_heures_disponibles < $heures_decomptes && !$force_cloture) {
        $pdo->rollBack();
        http_response_code(400);
        echo json_encode([
            'error' => 'Heures insuffisantes dans les forfaits',
            'besoin_nouveau_forfait' => true,
            'client_id' => $rdv['client_id'],
            'heures_restantes' => $total_heures_disponibles,
            'heures_necessaires' => $heures_decomptes,
            'heures_manquantes' => $heures_decomptes - $total_heures_disponibles,
            'temps_reel' => $temps_reel,
            'temps_arrondi' => $temps_arrondi_sup
        ]);
        exit;
    }
    
    // Décompter les heures en utilisant les forfaits dans l'ordre (FIFO)
    $heures_a_decompter = $heures_decomptes;
    $heures_avant_total = $total_heures_disponibles;
    $forfaits_utilises = [];
    
    foreach ($forfaits_disponibles as $forfait) {
        if ($heures_a_decompter <= 0) break;
        
        $heures_dispo = floatval($forfait['heures_restantes']);
        $heures_prises = min($heures_a_decompter, $heures_dispo);
        $nouvelles_heures = $heures_dispo - $heures_prises;
        
        // Mettre à jour le forfait
        $stmt = $pdo->prepare("
            UPDATE forfaits_vendus 
            SET heures_restantes = ?
            WHERE id = ?
        ");
        $stmt->execute([$nouvelles_heures, $forfait['id']]);
        
        $forfaits_utilises[] = [
            'forfait_id' => $forfait['id'],
            'heures_prises' => $heures_prises,
            'heures_avant' => $heures_dispo,
            'heures_apres' => $nouvelles_heures
        ];
        
        $heures_a_decompter -= $heures_prises;
    }
    
    // Utiliser le premier forfait pour l'historique (pour compatibilité)
    $forfait_principal = $forfaits_disponibles[0];
    $heures_avant = $heures_avant_total;
    $heures_apres = $total_heures_disponibles - $heures_decomptes;
    
    // Mettre à jour le champ heure_bonus du client (peut être négatif)
    if ($bonus_a_ajouter != 0) {
        $stmt = $pdo->prepare("
            UPDATE clients 
            SET heure_bonus = COALESCE(heure_bonus, 0) + ?
            WHERE id = ?
        ");
        $stmt->execute([$bonus_a_ajouter, $rdv['client_id']]);
    }
    
    // Enregistrer dans l'historique
    $stmt = $pdo->prepare("
        INSERT INTO historique_consommation 
        (rendez_vous_id, forfait_vendu_id, client_id, temps_reel, temps_arrondi, 
         difference_arrondi, heures_decomptes, heures_avant, heures_apres, 
         date_rdv, heure_debut, heure_fin)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([
        $rendez_vous_id,
        $forfait_principal['id'],
        $rdv['client_id'],
        $temps_reel,
        $temps_arrondi_sup,
        $bonus_a_ajouter, // Stocker le bonus réellement ajouté
        $heures_decomptes, // Stocker les heures réellement décomptées
        $heures_avant,
        $heures_apres,
        $rdv['date_rdv'],
        $heure_debut_calc,
        $heure_fin_calc
    ]);
    
    // Mettre à jour le statut du rendez-vous si ce n'est pas déjà fait
    if ($rdv['statut'] !== 'termine') {
        $stmt = $pdo->prepare("UPDATE rendez_vous SET statut = 'termine' WHERE id = ?");
        $stmt->execute([$rendez_vous_id]);
    }
    
    // Récupérer le heure_bonus mis à jour
    $stmt = $pdo->prepare("SELECT heure_bonus FROM clients WHERE id = ?");
    $stmt->execute([$rdv['client_id']]);
    $client = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $pdo->commit();
    
    echo json_encode([
        'status' => 'cloture',
        'rendez_vous_id' => $rendez_vous_id,
        'forfait_id' => $forfait['id'],
        'temps_reel' => round($temps_reel, 2),
        'temps_arrondi' => round($temps_arrondi_sup, 2),
        'difference_arrondi' => round($bonus_a_ajouter, 2),
        'heures_avant' => round($heures_avant, 2),
        'heures_decomptes' => round($heures_decomptes, 2),
        'heures_apres' => round($heures_apres, 2),
        'heure_bonus_client' => round($client['heure_bonus'] ?? 0, 2),
        'forfait_termine' => $heures_apres <= 0,
        'arrondi_applique' => $appliquer_arrondi
    ]);
    
} catch (PDOException $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log("Erreur PDO close_intervention.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Erreur serveur: ' . $e->getMessage()]);
} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log("Erreur close_intervention.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Erreur: ' . $e->getMessage()]);
}
