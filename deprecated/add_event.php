<?php
require_once 'config.php';

$contentType = isset($_SERVER["CONTENT_TYPE"]) ? $_SERVER["CONTENT_TYPE"] : '';

if (strpos($contentType, 'application/json') !== false) {
    header("Content-Type: application/json");
    
    try {
        $input = file_get_contents("php://input");
        $data = json_decode($input, true);
        
        if (!$data || empty($data["start"])) {
            throw new Exception("Date obligatoires");
        }

        // --- Validation serveur (JSON) ---
        if (mb_strlen($data["title"]) > 255) {
            http_response_code(400);
            echo json_encode(["error" => "Le titre est trop long (max 255 caractères)"]);
            exit;
        }
        if (isset($data["lieu"]) && mb_strlen($data["lieu"]) > 255) {
            http_response_code(400);
            echo json_encode(["error" => "Le lieu est trop long (max 255 caractères)"]);
            exit;
        }
        if (isset($data["description"]) && mb_strlen($data["description"]) > 2000) {
            http_response_code(400);
            echo json_encode(["error" => "La description est trop longue (max 2000 caractères)"]);
            exit;
        }
        if (isset($data["statut"]) ) {
            $allowed = array_keys(STATUTS_RDV);
            if (!in_array($data["statut"], $allowed, true)) {
                http_response_code(400);
                echo json_encode(["error" => "Statut invalide"]);
                exit;
            }
        }

        $isoPattern = '/^\\d{4}-\\d{2}-\\d{2}T\\d{2}:\\d{2}:\\d{2}$/';
        $dateTime = $data["start"];
        if (!preg_match($isoPattern, $dateTime)) {
            http_response_code(400);
            echo json_encode(["error" => "Format de 'start' invalide. Attendu YYYY-MM-DDTHH:MM:SS"]);
            exit;
        }
        
        $pdo = getDBConnection();
        
        $date_rdv = substr($dateTime, 0, 10);
        $heure_debut = substr($dateTime, 11, 5) . ":00";
        
        $heure_fin = date('H:i:s', strtotime($heure_debut . ' +1 hour'));
        
        $statut = isset($data["statut"]) ? $data["statut"] : 'planifie';
        $lieu = isset($data["lieu"]) ? $data["lieu"] : '';
        $description = isset($data["description"]) ? $data["description"] : '';
        $id_technicien = isset($data["id_technicien"]) && $data["id_technicien"] !== '' ? intval($data["id_technicien"]) : null;
        $client_id = isset($data["client_id"]) && $data["client_id"] !== '' ? intval($data["client_id"]) : null;

        // If client_id provided, fetch client and use its name as title if no title provided
        if ($client_id !== null) {
            $stmtClient = $pdo->prepare('SELECT prenom, nom FROM clients WHERE id = ?');
            $stmtClient->execute([$client_id]);
            $clientRow = $stmtClient->fetch(PDO::FETCH_ASSOC);
            if (!$clientRow) {
                http_response_code(400);
                echo json_encode(["error" => "Client introuvable"]);
                exit;
            }
            // Build title from prenom + nom
            $clientTitle = trim($clientRow['prenom'] . ' ' . $clientRow['nom']);
        }

        // if technician provided, validate existence
        if ($id_technicien !== null) {
            $stmtChk = $pdo->prepare("SELECT id FROM techniciens WHERE id = ?");
            $stmtChk->execute([$id_technicien]);
            if (!$stmtChk->fetch()) {
                http_response_code(400);
                echo json_encode(["error" => "Technicien introuvable"]);
                exit;
            }
        }

        $stmt = $pdo->prepare("INSERT INTO rendez_vous (titre, date_rdv, heure_debut, heure_fin, statut, description, lieu, id_technicien, client_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");

        $titleToInsert = isset($clientTitle) && $clientTitle !== '' ? $clientTitle : $data["title"];

        $stmt->execute([
            $titleToInsert,
            $date_rdv,
            $heure_debut,
            $heure_fin,
            $statut,
            $description,
            $lieu,
            $id_technicien,
            $client_id
        ]);
        
        $newId = $pdo->lastInsertId();
        
        echo json_encode([
            "status" => "created",
            "id" => $newId,
            "message" => "Rendez-vous cree"
        ]);
        
    } catch (Exception $e) {
        http_response_code(400);
        echo json_encode(["error" => $e->getMessage()]);
    }
    exit;
}

$date = isset($_GET['date']) ? $_GET['date'] : date('Y-m-d');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // --- Validation serveur (formulaire) ---
        if (mb_strlen($_POST['titre']) > 255) {
            throw new Exception('Le titre est trop long (max 255 caractères)');
        }
        if (!preg_match('/^\\d{4}-\\d{2}-\\d{2}$/', $_POST['date_rdv'])) {
            throw new Exception('Date invalide');
        }
        if (!preg_match('/^\\d{2}:\\d{2}$/', $_POST['heure_debut']) || !preg_match('/^\\d{2}:\\d{2}$/', $_POST['heure_fin'])) {
            throw new Exception('Format d\'heure invalide');
        }
        if (strtotime($_POST['date_rdv'] . ' ' . $_POST['heure_debut']) >= strtotime($_POST['date_rdv'] . ' ' . $_POST['heure_fin'])) {
            throw new Exception('L\'heure de fin doit être après l\'heure de début');
        }
        if (isset($_POST['lieu']) && mb_strlen($_POST['lieu']) > 255) {
            throw new Exception('Le lieu est trop long (max 255 caractères)');
        }
        if (isset($_POST['description']) && mb_strlen($_POST['description']) > 2000) {
            throw new Exception('La description est trop longue (max 2000 caractères)');
        }

        $pdo = getDBConnection();
        
        $stmt = $pdo->prepare("INSERT INTO rendez_vous (titre, date_rdv, heure_debut, heure_fin, lieu, description, statut) VALUES (?, ?, ?, ?, ?, ?, 'planifie')");
        
        $stmt->execute([
            $_POST['titre'],
            $_POST['date_rdv'],
            $_POST['heure_debut'] . ':00',
            $_POST['heure_fin'] . ':00',
            isset($_POST['lieu']) ? $_POST['lieu'] : '',
            isset($_POST['description']) ? $_POST['description'] : ''
        ]);
        
        header('Location: agenda.php');
        exit;
        
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nouveau rendez-vous</title>
    <style>
        body { font-family: Arial, sans-serif; background: #f5f5f5; }
        .form-container { max-width: 600px; margin: 50px auto; padding: 30px; background: white; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; margin-bottom: 5px; font-weight: 600; }
        .form-group input, .form-group textarea { width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 5px; box-sizing: border-box; }
        .form-actions { display: flex; gap: 10px; }
        .btn { padding: 10px 20px; border: none; border-radius: 5px; cursor: pointer; text-decoration: none; display: inline-block; }
        .btn-primary { background: #667eea; color: white; }
        .btn-secondary { background: #ddd; color: #333; }
        .error { color: red; margin-bottom: 15px; }
    </style>
</head>
<body>
    <div class="form-container">
        <h2>📅 Nouveau rendez-vous</h2>
        
        <?php if (isset($error)): ?>
            <div class="error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        
        <form method="POST">
            <div class="form-group">
                <label>Titre *</label>
                <input type="text" name="titre" required>
            </div>
            
            <div class="form-group">
                <label>Date *</label>
                <input type="date" name="date_rdv" value="<?php echo htmlspecialchars($date); ?>" required>
            </div>
            
            <div class="form-group">
                <label>Heure debut *</label>
                <input type="time" name="heure_debut" value="09:00" required>
            </div>
            
            <div class="form-group">
                <label>Heure fin *</label>
                <input type="time" name="heure_fin" value="10:00" required>
            </div>
            
            <div class="form-group">
                <label>Lieu</label>
                <input type="text" name="lieu">
            </div>
            
            <div class="form-group">
                <label>Description</label>
                <textarea name="description" rows="4"></textarea>
            </div>
            
            <div class="form-actions">
                <button type="submit" class="btn btn-primary">Enregistrer</button>
                <a href="agenda.php" class="btn btn-secondary">Annuler</a>
            </div>
        </form>
    </div>
</body>
</html>
