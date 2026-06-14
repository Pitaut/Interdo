<?php

/*
FullCalendar fournit :
id
start
end
title (si modifié)
*/

header("Content-Type: application/json");
require "config.php";

$data = json_decode(file_get_contents("php://input"), true);

if (!isset($data["id"])) {
    echo json_encode(["error" => "Missing ID"]);
    exit;
}

$id    = $data["id"];
$title = $data["title"] ?? null;
$start = $data["start"] ?? null;
$end   = $data["end"] ?? null;
$lieu = $data["lieu"] ?? null;
$description = $data["description"] ?? null;
$statut = $data["statut"] ?? null;
 $id_technicien = isset($data['id_technicien']) ? ($data['id_technicien'] === '' ? null : intval($data['id_technicien'])) : null;
 $client_id = isset($data['client_id']) ? ($data['client_id'] === '' ? null : intval($data['client_id'])) : null;

// --- Validation serveur ---
// Max lengths according to DB schema
if ($title !== null && mb_strlen($title) > 255) {
    http_response_code(400);
    echo json_encode(["error" => "Le titre est trop long (max 255 caractères)"]);
    exit;
}
if ($lieu !== null && mb_strlen($lieu) > 255) {
    http_response_code(400);
    echo json_encode(["error" => "Le lieu est trop long (max 255 caractères)"]);
    exit;
}
// description is TEXT in DB, but we enforce a reasonable limit
if ($description !== null && mb_strlen($description) > 2000) {
    http_response_code(400);
    echo json_encode(["error" => "La description est trop longue (max 2000 caractères)"]);
    exit;
}

// statut must be one of allowed keys from STATUTS_RDV
if ($statut !== null) {
    $allowed = array_keys(STATUTS_RDV);
    if (!in_array($statut, $allowed, true)) {
        http_response_code(400);
        echo json_encode(["error" => "Statut invalide" ]);
        exit;
    }
}

// Validate start/end format if provided. Accepts local ISO (YYYY-MM-DDTHH:MM:SS),
// full ISO with timezone (e.g. YYYY-MM-DDTHH:MM:SSZ or with +hh:mm offset),
// and optional fractional seconds (milliseconds) like .000
$isoPatternFlexible = '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(?:\.\d+)?(?:Z|[+\-]\d{2}:?\d{2})?$/';
if ($start !== null && !preg_match($isoPatternFlexible, $start)) {
    http_response_code(400);
    echo json_encode(["error" => "Format de 'start' invalide. Attendu YYYY-MM-DDTHH:MM:SS ou ISO avec timezone" ]);
    exit;
}
if ($end !== null && !preg_match($isoPatternFlexible, $end)) {
    http_response_code(400);
    echo json_encode(["error" => "Format de 'end' invalide. Attendu YYYY-MM-DDTHH:MM:SS ou ISO avec timezone" ]);
    exit;
}
if ($start !== null && $end !== null) {
    // Use strtotime which understands timezone suffixes; strtotime returns false on failure
    $tsStart = strtotime($start);
    $tsEnd = strtotime($end);
    if ($tsStart === false || $tsEnd === false) {
        http_response_code(400);
        echo json_encode(["error" => "Impossible d'analyser les dates fournies" ]);
        exit;
    }
    if ($tsEnd <= $tsStart) {
        http_response_code(400);
        echo json_encode(["error" => "L'heure de fin doit être après l'heure de début" ]);
        exit;
    }
}

try {
    $pdo = getDBConnection();
    $sql = "UPDATE rendez_vous SET ";
    $vals = [];

    if ($title !== null) {
        $sql .= "titre=?, ";
        $vals[] = $title;
    }

    if ($start !== null) {
        // Parse start respecting timezone information. If the string contains an explicit
        // timezone (Z or +hh:mm/-hh:mm) use DateTime which will handle it. If no timezone
        // is present, interpret the datetime in the server timezone defined by TIMEZONE
        // to avoid off-by-one-hour issues.
        $dtStart = false;
        if (preg_match('/Z$|[+\-]\d{2}:?\d{2}$/', $start)) {
            try {
                $dt = new DateTime($start);
                // Normalize to server timezone to ensure stored local hour is Europe/Paris
                $dt->setTimezone(new DateTimeZone(TIMEZONE));
                $dtStart = $dt;
            } catch (Exception $e) { $dtStart = false; }
        } else {
            // try parsing with fractional seconds then without
            $formats = ['Y-m-d\TH:i:s.u', 'Y-m-d\TH:i:s'];
            foreach ($formats as $fmt) {
                $dt = DateTime::createFromFormat($fmt, $start, new DateTimeZone(TIMEZONE));
                if ($dt !== false) { $dtStart = $dt; break; }
            }
        }

        if ($dtStart !== false) {
            $date_rdv = $dtStart->format('Y-m-d');
            $heure_debut = $dtStart->format('H:i:s');
        } else {
            // fallback to substring (legacy)
            $date_rdv = substr($start, 0, 10);
            $heure_debut = substr($start, 11, 5) . ":00";
        }

        $sql .= "date_rdv=?, heure_debut=?, ";
        $vals[] = $date_rdv;
        $vals[] = $heure_debut;
    }

    if ($end !== null) {
        $dtEnd = false;
        if (preg_match('/Z$|[+\-]\d{2}:?\d{2}$/', $end)) {
            try {
                $dt2 = new DateTime($end);
                $dt2->setTimezone(new DateTimeZone(TIMEZONE));
                $dtEnd = $dt2;
            } catch (Exception $e) { $dtEnd = false; }
        } else {
            $formats = ['Y-m-d\TH:i:s.u', 'Y-m-d\TH:i:s'];
            foreach ($formats as $fmt) {
                $dt2 = DateTime::createFromFormat($fmt, $end, new DateTimeZone(TIMEZONE));
                if ($dt2 !== false) { $dtEnd = $dt2; break; }
            }
        }
        if ($dtEnd !== false) {
            $heure_fin = $dtEnd->format('H:i:s');
        } else {
            $heure_fin = substr($end, 11, 5) . ":00";
        }
        $sql .= "heure_fin=?, ";
        $vals[] = $heure_fin;
    }

    if ($lieu !== null) {
        $sql .= "lieu=?, ";
        $vals[] = $lieu;
    }

    if ($description !== null) {
        $sql .= "description=?, ";
        $vals[] = $description;
    }

    if ($statut !== null) {
        $sql .= "statut=?, ";
        $vals[] = $statut;
    }

    if ($id_technicien !== null) {
        // validate technicien exists
        $chk = $pdo->prepare('SELECT id FROM techniciens WHERE id = ?');
        $chk->execute([$id_technicien]);
        if (!$chk->fetch()) {
            http_response_code(400);
            echo json_encode(["error"=>"Technicien introuvable"]);
            exit;
        }
        $sql .= "id_technicien=?, ";
        $vals[] = $id_technicien;
    } else if (array_key_exists('id_technicien', $data) && $data['id_technicien'] === null) {
        // explicit null -> clear association
        $sql .= "id_technicien=NULL, ";
    }

    if ($client_id !== null) {
        // validate client exists
        $chkc = $pdo->prepare('SELECT id FROM clients WHERE id = ?');
        $chkc->execute([$client_id]);
        if (!$chkc->fetch()) {
            http_response_code(400);
            echo json_encode(["error"=>"Client introuvable"]);
            exit;
        }
        $sql .= "client_id=?, ";
        $vals[] = $client_id;
    } else if (array_key_exists('client_id', $data) && $data['client_id'] === null) {
        // explicit null -> clear association
        $sql .= "client_id=NULL, ";
    }

    $sql = rtrim($sql, ", ") . " WHERE id=?";
    $vals[] = $id;

    $stmt = $pdo->prepare($sql);
    $stmt->execute($vals);

    // Si le statut passe à 'termine', clôturer automatiquement l'intervention
    $cloture_info = null;
    if ($statut === 'termine') {
        // Vérifier que le rendez-vous a un client_id
        $checkStmt = $pdo->prepare("SELECT client_id FROM rendez_vous WHERE id = ?");
        $checkStmt->execute([$id]);
        $rdvData = $checkStmt->fetch(PDO::FETCH_ASSOC);
        
        if ($rdvData && $rdvData['client_id']) {
            // Appeler close_intervention via une requête interne
            $close_data = json_encode(['rendez_vous_id' => intval($id)]);
            
            // Utiliser cURL pour appeler close_intervention.php
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, 'http://localhost/_Interdo/close_intervention.php');
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $close_data);
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            
            $close_response = curl_exec($ch);
            $close_http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            if ($close_http_code === 200) {
                $cloture_info = json_decode($close_response, true);
            } else {
                // Clôture échouée mais on ne bloque pas la mise à jour du statut
                $cloture_error = json_decode($close_response, true);
                $cloture_info = [
                    'cloture_status' => 'error',
                    'cloture_message' => $cloture_error['error'] ?? 'Erreur inconnue',
                    'besoin_nouveau_forfait' => $cloture_error['besoin_nouveau_forfait'] ?? false
                ];
            }
        }
    }

    // Build detailed debug information to help diagnose timezone issues
    $result = ["status" => "updated"];
    if (isset($date_rdv)) $result['date_rdv'] = $date_rdv;
    if (isset($heure_debut)) $result['heure_debut'] = $heure_debut;
    if (isset($heure_fin)) $result['heure_fin'] = $heure_fin;

    // Ajouter les infos de clôture si disponibles
    if ($cloture_info) {
        $result['cloture'] = $cloture_info;
    }

    // echo back what we received
    $result['received_start'] = $start;
    $result['received_end'] = $end;

    // provide computed unix timestamps (if parsable)
    $tsStartVar = null;
    $tsEndVar = null;
    if (isset($dtStart) && $dtStart !== false) {
        $tsStartVar = $dtStart->getTimestamp();
    } else {
        $tmp = $start !== null ? strtotime($start) : false;
        $tsStartVar = ($tmp === false ? null : $tmp);
    }
    if (isset($dtEnd) && $dtEnd !== false) {
        $tsEndVar = $dtEnd->getTimestamp();
    } else {
        $tmp2 = $end !== null ? strtotime($end) : false;
        $tsEndVar = ($tmp2 === false ? null : $tmp2);
    }
    $result['ts_start'] = $tsStartVar;
    $result['ts_end'] = $tsEndVar;
    $result['server_timezone'] = date_default_timezone_get();

    echo json_encode($result);

} catch (Exception $e) {
    echo json_encode(["error" => $e->getMessage()]);
    http_response_code(500);
}
?>
