<?php
require_once __DIR__ . '/../config.php';

header('Content-Type: application/json');

try {
    $pdo = getDBConnection();
    
    $action = $_POST['action'] ?? $_GET['action'] ?? '';
    
    switch ($action) {
        case 'duplicate_year':
            // Dupliquer une année complète avec ajustement optionnel
            $annee_source = (int)$_POST['annee_source'];
            $annee_cible = (int)$_POST['annee_cible'];
            $coefficient = (float)str_replace(',', '.', $_POST['coefficient'] ?? '1.0'); // Support virgule ou point
            
            if (!$annee_source || !$annee_cible) {
                throw new Exception('Année source et cible requises');
            }
            
            if ($annee_cible <= $annee_source) {
                throw new Exception('L\'année cible doit être postérieure à l\'année source');
            }
            
            // Vérifier si l'année cible existe déjà
            $check = $pdo->prepare("SELECT COUNT(*) FROM bareme_kilometrique WHERE annee_fiscale = ?");
            $check->execute([$annee_cible]);
            if ($check->fetchColumn() > 0) {
                throw new Exception("L'année $annee_cible existe déjà");
            }
            
            // Dupliquer avec ajustement
            $stmt = $pdo->prepare("
                INSERT INTO bareme_kilometrique 
                (annee_fiscale, type_vehicule, puissance_min, puissance_max, 
                 distance_min, distance_max, formule_calcul, cout_fixe, cout_variable)
                SELECT 
                    ?, 
                    type_vehicule, puissance_min, puissance_max,
                    distance_min, distance_max, formule_calcul,
                    ROUND(cout_fixe * ?, 2),
                    ROUND(cout_variable * ?, 4)
                FROM bareme_kilometrique
                WHERE annee_fiscale = ?
            ");
            $stmt->execute([$annee_cible, $coefficient, $coefficient, $annee_source]);
            
            $nb_copies = $stmt->rowCount();
            
            $coefficient_pct = round($coefficient * 100, 1);
            
            echo json_encode([
                'status' => 'success',
                'message' => "$nb_copies barèmes copiés de $annee_source vers $annee_cible avec coefficient {$coefficient_pct}%",
                'annee_cible' => $annee_cible,
                'nb_baremes' => $nb_copies
            ]);
            break;
            
        case 'update_bareme':
            // Mettre à jour un barème existant
            $id = (int)$_POST['id'];
            $cout_fixe = (float)$_POST['cout_fixe'];
            $cout_variable = (float)$_POST['cout_variable'];
            $formule = $_POST['formule_calcul'] ?? '';
            
            if (!$id) {
                throw new Exception('ID requis');
            }
            
            $stmt = $pdo->prepare("
                UPDATE bareme_kilometrique 
                SET cout_fixe = ?, cout_variable = ?, formule_calcul = ?
                WHERE id = ?
            ");
            $stmt->execute([$cout_fixe, $cout_variable, $formule, $id]);
            
            echo json_encode([
                'status' => 'success',
                'message' => 'Barème mis à jour'
            ]);
            break;
            
        case 'add_bareme':
            // Ajouter un nouveau barème
            $annee = (int)$_POST['annee_fiscale'];
            $type = $_POST['type_vehicule'];
            $puissance_min = (int)$_POST['puissance_min'];
            $puissance_max = (int)$_POST['puissance_max'];
            $distance_min = (int)$_POST['distance_min'];
            $distance_max = (int)$_POST['distance_max'];
            $formule = $_POST['formule_calcul'];
            $cout_fixe = (float)$_POST['cout_fixe'];
            $cout_variable = (float)$_POST['cout_variable'];
            
            if (!$annee || !$type || !$formule) {
                throw new Exception('Champs requis manquants');
            }
            
            $stmt = $pdo->prepare("
                INSERT INTO bareme_kilometrique 
                (annee_fiscale, type_vehicule, puissance_min, puissance_max,
                 distance_min, distance_max, formule_calcul, cout_fixe, cout_variable)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $annee, $type, $puissance_min, $puissance_max,
                $distance_min, $distance_max, $formule, $cout_fixe, $cout_variable
            ]);
            
            echo json_encode([
                'status' => 'success',
                'message' => 'Barème ajouté',
                'id' => $pdo->lastInsertId()
            ]);
            break;
            
        case 'delete_bareme':
            // Supprimer un barème
            $id = (int)$_POST['id'];
            
            if (!$id) {
                throw new Exception('ID requis');
            }
            
            // Vérifier si utilisé
            $check = $pdo->prepare("
                SELECT COUNT(*) FROM rendez_vous WHERE bareme_km_utilise_id = ?
            ");
            $check->execute([$id]);
            
            if ($check->fetchColumn() > 0) {
                throw new Exception('Ce barème est utilisé dans des interventions et ne peut être supprimé');
            }
            
            $stmt = $pdo->prepare("DELETE FROM bareme_kilometrique WHERE id = ?");
            $stmt->execute([$id]);
            
            echo json_encode([
                'status' => 'success',
                'message' => 'Barème supprimé'
            ]);
            break;
            
        case 'delete_year':
            // Supprimer une année complète
            $annee = (int)$_POST['annee'];
            
            if (!$annee) {
                throw new Exception('Année requise');
            }
            
            // Vérifier si utilisée
            $check = $pdo->prepare("
                SELECT COUNT(*) 
                FROM rendez_vous r
                JOIN bareme_kilometrique b ON r.bareme_km_utilise_id = b.id
                WHERE b.annee_fiscale = ?
            ");
            $check->execute([$annee]);
            
            if ($check->fetchColumn() > 0) {
                throw new Exception("L'année $annee est utilisée dans des interventions et ne peut être supprimée");
            }
            
            $stmt = $pdo->prepare("DELETE FROM bareme_kilometrique WHERE annee_fiscale = ?");
            $stmt->execute([$annee]);
            $nb_deleted = $stmt->rowCount();
            
            echo json_encode([
                'status' => 'success',
                'message' => "$nb_deleted barèmes supprimés pour l'année $annee"
            ]);
            break;
            
        default:
            throw new Exception('Action non reconnue');
    }
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'error' => $e->getMessage()
    ]);
}
