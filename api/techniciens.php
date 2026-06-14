<?php
/**
 * API Techniciens
 * Gère toutes les opérations CRUD sur les techniciens
 * 
 * Routes:
 * - GET  ?action=list         → Liste tous les techniciens
 * - GET  ?action=get&id=X     → Détails d'un technicien
 * - POST ?action=create       → Créer un technicien
 * - POST ?action=update       → Modifier un technicien
 * - POST ?action=delete       → Supprimer un technicien
 */

require_once __DIR__ . '/../config.php';

header('Content-Type: application/json');

$action = $_GET['action'] ?? $_POST['action'] ?? 'list';
$method = $_SERVER['REQUEST_METHOD'];

try {
    $pdo = getDBConnection();
    
    switch ($action) {
        case 'list':
            handleList($pdo);
            break;
            
        case 'get':
            handleGet($pdo);
            break;
            
        case 'get_vehicle':
            handleGetVehicle($pdo);
            break;
            
        case 'create':
            if ($method !== 'POST') {
                http_response_code(405);
                echo json_encode(['error' => 'Méthode non autorisée']);
                exit;
            }
            handleCreate($pdo);
            break;
            
        case 'update':
            if ($method !== 'POST') {
                http_response_code(405);
                echo json_encode(['error' => 'Méthode non autorisée']);
                exit;
            }
            handleUpdate($pdo);
            break;
            
        case 'delete':
            if ($method !== 'POST') {
                http_response_code(405);
                echo json_encode(['error' => 'Méthode non autorisée']);
                exit;
            }
            handleDelete($pdo);
            break;
            
        default:
            http_response_code(400);
            echo json_encode(['error' => 'Action inconnue']);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}

function handleList($pdo) {
    $actifsOnly = isset($_GET['actifs_only']) && $_GET['actifs_only'] === '1';

    if ($actifsOnly) {
                $stmt = $pdo->prepare("
            SELECT * FROM techniciens
            WHERE actif = 1
              AND (date_entree IS NULL OR date_entree <= CURDATE())
              AND (date_sortie IS NULL OR date_sortie > CURDATE())
            ORDER BY nom ASC, prenom ASC
        ");
        $stmt->execute();
    } else {
        $stmt = $pdo->query("SELECT * FROM techniciens ORDER BY nom ASC, prenom ASC");
    }

    $techniciens = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $today = date('Y-m-d');
    foreach ($techniciens as &$tech) {
        $entree = $tech['date_entree'] ?? null;
        $sortie = $tech['date_sortie'] ?? null;
        $actif = !empty($tech['actif']);

        $tech['est_actif_periode'] = true;
        $tech['statut_label'] = 'Actif';

        if ($entree && $entree > $today) {
            $tech['est_actif_periode'] = false;
            $tech['statut_label'] = 'Pas encore entré';
        } elseif ($sortie && $sortie <= $today) {
            $tech['est_actif_periode'] = false;
            $tech['statut_label'] = 'Sorti le ' . date('d/m/Y', strtotime($sortie));
        } elseif (!$actif) {
            $tech['est_actif_periode'] = false;
            $tech['statut_label'] = 'Désactivé';
        }
    }
    unset($tech);

    echo json_encode($techniciens);
}

function handleGet($pdo) {
    $id = intval($_GET['id'] ?? 0);
    
    if ($id <= 0) {
        http_response_code(400);
        echo json_encode(['error' => 'ID requis']);
        exit;
    }
    
    $stmt = $pdo->prepare("SELECT * FROM techniciens WHERE id = ?");
    $stmt->execute([$id]);
    $technicien = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$technicien) {
        http_response_code(404);
        echo json_encode(['error' => 'Technicien introuvable']);
        exit;
    }
    
    echo json_encode($technicien);
}

function handleCreate($pdo) {
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (!$data) {
        http_response_code(400);
        echo json_encode(['error' => 'Données JSON invalides']);
        exit;
    }
    
    $nom = trim($data['nom'] ?? '');
    $prenom = trim($data['prenom'] ?? '');
    $email = trim($data['email'] ?? '');
    $adresse = trim($data['adresse'] ?? '');
    $code_postal = trim($data['code_postal'] ?? '');
    $ville = trim($data['ville'] ?? '');
    $pays = trim($data['pays'] ?? '');
    $telephone_fixe = trim($data['telephone_fixe'] ?? '');
    $telephone_mobile = trim($data['telephone_mobile'] ?? '');
    $date_entree = isset($data['date_entree']) && $data['date_entree'] ? trim($data['date_entree']) : null;
    $date_sortie = isset($data['date_sortie']) && $data['date_sortie'] ? trim($data['date_sortie']) : null;
    $salaire_horaire = isset($data['salaire_horaire']) ? floatval($data['salaire_horaire']) : null;
    $couleur = trim($data['couleur'] ?? '#667eea');
    $actif = array_key_exists('actif', $data) ? parseBooleanInput($data['actif'], true) : true;

    if (!$actif && empty($date_sortie)) {
        $date_sortie = date('Y-m-d');
    }
    
    if (empty($nom) || empty($prenom)) {
        http_response_code(400);
        echo json_encode(['error' => 'Nom et prénom requis']);
        exit;
    }
    
    $stmt = $pdo->prepare("
        INSERT INTO techniciens (nom, prenom, email, adresse, code_postal, ville, pays,
                               telephone_fixe, telephone_mobile, date_entree, date_sortie, salaire_horaire, couleur, actif)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    
    $stmt->execute([
        $nom, $prenom, $email, $adresse, $code_postal, $ville, $pays,
        $telephone_fixe, $telephone_mobile, $date_entree, $date_sortie, $salaire_horaire, $couleur, $actif
    ]);
    
    $id = $pdo->lastInsertId();
    
    http_response_code(201);
    echo json_encode(['status' => 'created', 'id' => $id]);
}

function handleUpdate($pdo) {
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($data['id'])) {
        http_response_code(400);
        echo json_encode(['error' => 'ID requis']);
        exit;
    }
    
    $id = intval($data['id']);
    
    // Construction dynamique de la requête
    $fields = [];
    $values = [];
    
    $allowedFields = [
        'nom', 'prenom', 'email', 'adresse', 'code_postal', 'ville', 'pays',
        'telephone_fixe', 'telephone_mobile', 'date_entree', 'date_sortie', 'salaire_horaire', 'couleur', 'actif'
    ];

    $actifInputPresent = isset($data['actif']);
    $dateSortieInputPresent = isset($data['date_sortie']);
    $normalizedActif = $actifInputPresent ? parseBooleanInput($data['actif'], true) : null;
    
    foreach ($allowedFields as $field) {
        if (isset($data[$field])) {
            $fields[] = "$field = ?";
            if ($field === 'actif') {
                $values[] = $normalizedActif;
            } elseif ($field === 'salaire_horaire') {
                $values[] = $data[$field] ? floatval($data[$field]) : null;
            } elseif ($field === 'date_entree' || $field === 'date_sortie') {
                $values[] = $data[$field] ?: null;
            } else {
                $values[] = trim($data[$field]);
            }
        }
    }

    // Si le technicien est désactivé sans date de sortie explicite, on pose la date du jour.
    if ($actifInputPresent && $normalizedActif === false && !$dateSortieInputPresent) {
        $fields[] = 'date_sortie = ?';
        $values[] = date('Y-m-d');
    }
    
    if (empty($fields)) {
        echo json_encode(['status' => 'unchanged']);
        exit;
    }
    
    $values[] = $id;
    $sql = "UPDATE techniciens SET " . implode(', ', $fields) . " WHERE id = ?";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($values);
    
    echo json_encode(['status' => 'updated']);
}

function handleDelete($pdo) {
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($data['id'])) {
        http_response_code(400);
        echo json_encode(['error' => 'ID requis']);
        exit;
    }
    
    $id = intval($data['id']);
    
    $stmt = $pdo->prepare("DELETE FROM techniciens WHERE id = ?");
    $stmt->execute([$id]);
    
    echo json_encode(['status' => 'deleted']);
}

function handleGetVehicle($pdo) {
    $id_technicien = intval($_GET['id_technicien'] ?? 0);
    
    if ($id_technicien <= 0) {
        http_response_code(400);
        echo json_encode(['error' => 'ID technicien requis']);
        exit;
    }
    
    $stmt = $pdo->prepare("
        SELECT v.*, CONCAT(t.prenom, ' ', t.nom) as technicien_nom
        FROM vehicules v
        INNER JOIN techniciens_vehicules tv ON v.id = tv.id_vehicule
        INNER JOIN techniciens t ON tv.id_technicien = t.id
        WHERE tv.id_technicien = ? AND v.actif = 1 AND tv.date_fin IS NULL AND tv.principal = 1
        LIMIT 1
    ");
    $stmt->execute([$id_technicien]);
    $vehicule = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$vehicule) {
        echo json_encode(['vehicle' => null]);
    } else {
        echo json_encode(['vehicle' => $vehicule]);
    }
}

function parseBooleanInput($value, $default = false) {
    if ($value === null) {
        return $default;
    }

    if (is_bool($value)) {
        return $value;
    }

    if (is_int($value) || is_float($value)) {
        return intval($value) !== 0;
    }

    if (is_string($value)) {
        $normalized = strtolower(trim($value));
        if ($normalized === '1' || $normalized === 'true' || $normalized === 'on' || $normalized === 'yes') {
            return true;
        }
        if ($normalized === '0' || $normalized === 'false' || $normalized === 'off' || $normalized === 'no' || $normalized === '') {
            return false;
        }
    }

    return (bool)$value;
}
