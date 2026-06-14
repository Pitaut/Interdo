<?php
/**
 * API pour gérer l'attribution des véhicules aux techniciens
 */
require_once '../config.php';

header('Content-Type: application/json; charset=utf-8');

try {
    $pdo = getDBConnection();
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    $action = $_GET['action'] ?? $_POST['action'] ?? '';
    
    switch ($action) {
        case 'get_technicien_vehicules':
            getTechnicienVehicules($pdo);
            break;
            
        case 'ajouter_vehicule':
            ajouterVehicule($pdo);
            break;
            
        case 'retirer_vehicule':
            retirerVehicule($pdo);
            break;
            
        case 'set_principal':
            setPrincipal($pdo);
            break;
            
        default:
            http_response_code(400);
            echo json_encode(['error' => 'Action inconnue']);
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}

/**
 * Récupère les véhicules attribués à un technicien
 */
function getTechnicienVehicules($pdo) {
    $id_technicien = $_GET['id_technicien'] ?? null;
    
    if (!$id_technicien) {
        throw new Exception('id_technicien requis');
    }
    
    // Véhicules actuellement attribués
    $stmt = $pdo->prepare("
        SELECT tv.id, tv.id_vehicule, tv.principal, tv.date_debut, tv.date_fin,
               v.nom, v.immatriculation, v.cout_carburant_km, v.cout_usure_km
        FROM techniciens_vehicules tv
        JOIN vehicules v ON tv.id_vehicule = v.id
        WHERE tv.id_technicien = ? AND tv.date_fin IS NULL
        ORDER BY tv.principal DESC, v.nom
    ");
    $stmt->execute([$id_technicien]);
    $attribues = $stmt->fetchAll();
    
    // Véhicules disponibles (non attribués ou actifs)
    $stmt = $pdo->prepare("
        SELECT v.id, v.nom, v.immatriculation
        FROM vehicules v
        WHERE v.actif = 1
        AND v.id NOT IN (
            SELECT id_vehicule 
            FROM techniciens_vehicules 
            WHERE id_technicien = ? AND date_fin IS NULL
        )
        ORDER BY v.nom
    ");
    $stmt->execute([$id_technicien]);
    $disponibles = $stmt->fetchAll();
    
    echo json_encode([
        'attribues' => $attribues,
        'disponibles' => $disponibles
    ]);
}

/**
 * Attribue un véhicule à un technicien
 */
function ajouterVehicule($pdo) {
    $data = json_decode(file_get_contents('php://input'), true);
    
    $id_technicien = $data['id_technicien'] ?? null;
    $id_vehicule = $data['id_vehicule'] ?? null;
    $principal = $data['principal'] ?? false;
    $date_debut = $data['date_debut'] ?? date('Y-m-d');
    
    if (!$id_technicien || !$id_vehicule) {
        throw new Exception('id_technicien et id_vehicule requis');
    }
    
    $pdo->beginTransaction();
    
    try {
        // Si principal, retirer le statut principal des autres véhicules
        if ($principal) {
            $stmt = $pdo->prepare("
                UPDATE techniciens_vehicules 
                SET principal = 0 
                WHERE id_technicien = ? AND date_fin IS NULL
            ");
            $stmt->execute([$id_technicien]);
        }
        
        // Ajouter l'attribution
        $stmt = $pdo->prepare("
            INSERT INTO techniciens_vehicules (id_technicien, id_vehicule, date_debut, principal)
            VALUES (?, ?, ?, ?)
        ");
        $stmt->execute([$id_technicien, $id_vehicule, $date_debut, $principal ? 1 : 0]);
        
        $pdo->commit();
        
        echo json_encode([
            'status' => 'success',
            'message' => 'Véhicule attribué',
            'id' => $pdo->lastInsertId()
        ]);
        
    } catch (Exception $e) {
        $pdo->rollBack();
        throw $e;
    }
}

/**
 * Retire un véhicule d'un technicien (définit date_fin)
 */
function retirerVehicule($pdo) {
    $data = json_decode(file_get_contents('php://input'), true);
    
    $id = $data['id'] ?? null;
    $date_fin = $data['date_fin'] ?? date('Y-m-d');
    
    if (!$id) {
        throw new Exception('id requis');
    }
    
    $stmt = $pdo->prepare("
        UPDATE techniciens_vehicules 
        SET date_fin = ? 
        WHERE id = ?
    ");
    $stmt->execute([$date_fin, $id]);
    
    echo json_encode([
        'status' => 'success',
        'message' => 'Attribution terminée'
    ]);
}

/**
 * Définit un véhicule comme principal pour un technicien
 */
function setPrincipal($pdo) {
    $data = json_decode(file_get_contents('php://input'), true);
    
    $id = $data['id'] ?? null;
    $id_technicien = $data['id_technicien'] ?? null;
    
    if (!$id || !$id_technicien) {
        throw new Exception('id et id_technicien requis');
    }
    
    $pdo->beginTransaction();
    
    try {
        // Retirer le statut principal des autres véhicules
        $stmt = $pdo->prepare("
            UPDATE techniciens_vehicules 
            SET principal = 0 
            WHERE id_technicien = ? AND date_fin IS NULL
        ");
        $stmt->execute([$id_technicien]);
        
        // Définir le nouveau principal
        $stmt = $pdo->prepare("
            UPDATE techniciens_vehicules 
            SET principal = 1 
            WHERE id = ?
        ");
        $stmt->execute([$id]);
        
        $pdo->commit();
        
        echo json_encode([
            'status' => 'success',
            'message' => 'Véhicule principal défini'
        ]);
        
    } catch (Exception $e) {
        $pdo->rollBack();
        throw $e;
    }
}
