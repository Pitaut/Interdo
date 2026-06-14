<?php
// Désactiver le cache pour forcer le rechargement du JavaScript
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Cache-Control: post-check=0, pre-check=0', false);
header('Pragma: no-cache');
header('Expires: 0');

// Inclusion du fichier de configuration
require_once 'config.php';

// Connexion à la base de données
$pdo = getDBConnection();

// Récupération des rendez-vous pour l'API
if (isset($_GET['action']) && $_GET['action'] === 'get_events') {
    header('Content-Type: application/json');

    $start = $_GET['start'] ?? null;
    $end = $_GET['end'] ?? null;

    try {
        // ensure clients table exists to avoid SQL errors when not yet migrated
        $pdo->exec("CREATE TABLE IF NOT EXISTS clients (
            id INT AUTO_INCREMENT PRIMARY KEY,
            prenom VARCHAR(100) DEFAULT '',
            nom VARCHAR(100) DEFAULT '',
            tel_mobile VARCHAR(30) DEFAULT '',
            tel_fixe VARCHAR(30) DEFAULT '',
            adresse TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        // left join techniciens to get technician color when associated
        $sql = "SELECT rv.*, t.couleur AS tech_couleur, t.nom AS tech_nom, t.prenom AS tech_prenom, c.prenom AS client_prenom, c.nom AS client_nom, c.mode_paiement AS client_mode_paiement FROM rendez_vous rv LEFT JOIN techniciens t ON rv.id_technicien = t.id LEFT JOIN clients c ON rv.client_id = c.id";
        $params = [];

        if ($start && $end) {
            $sql .= " WHERE date_rdv BETWEEN ? AND ?";
            $params = [$start, $end];
        }

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $rendez_vous = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Formater les événements pour FullCalendar
        $events = [];
        foreach ($rendez_vous as $rdv) {
            $bg = $rdv['tech_couleur'] ?: (COULEURS_STATUT[$rdv['statut']] ?? '#667eea');
            $title = $rdv['titre'];
            
            // Ajouter l'icône 💰 si le client paie en avance immédiate
            if ($rdv['client_mode_paiement'] === 'avance_immediate') {
                $title = '💰 ' . $title;
            }
            
            $events[] = [
                'id' => $rdv['id'],
                'title' => $title,
                'start' => $rdv['date_rdv'] . 'T' . $rdv['heure_debut'],
                'end' => $rdv['date_rdv'] . 'T' . $rdv['heure_fin'],
                'description' => $rdv['description'],
                'lieu' => $rdv['lieu'],
                'statut' => $rdv['statut'],
                'backgroundColor' => $bg,
                'borderColor' => $bg,
                'id_technicien' => $rdv['id_technicien'] ?? null,
                'technicien' => $rdv['tech_nom'] ? ($rdv['tech_prenom'].' '.$rdv['tech_nom']) : null,
                'client_id' => $rdv['client_id'] ?? null,
                'client' => $rdv['client_nom'] ? (trim(($rdv['client_prenom'] ? $rdv['client_prenom'].' ' : '').$rdv['client_nom'])) : null,
                'client_mode_paiement' => $rdv['client_mode_paiement'] ?? null
            ];
        }

        echo json_encode($events);
        exit;
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Erreur serveur: ' . $e->getMessage()]);
        exit;
    }
}

// Récupération des détails d'un rendez-vous
if (isset($_GET['action']) && $_GET['action'] === 'get_event_details' && isset($_GET['id'])) {
    header('Content-Type: application/json');
    
    $stmt = $pdo->prepare("SELECT rv.*, t.id AS tech_id, t.nom AS tech_nom, t.prenom AS tech_prenom, t.couleur AS tech_couleur, c.id AS client_id, c.prenom AS client_prenom, c.nom AS client_nom, c.telephone_mobile AS client_telephone_mobile, c.telephone_fixe AS client_telephone_fixe, c.adresse AS client_adresse, c.code_postal AS client_code_postal, c.ville AS client_ville FROM rendez_vous rv LEFT JOIN techniciens t ON rv.id_technicien = t.id LEFT JOIN clients c ON rv.client_id = c.id WHERE rv.id = ?");
    $stmt->execute([$_GET['id']]);
    $rdv = $stmt->fetch(PDO::FETCH_ASSOC);
    
    echo json_encode($rdv);
    exit;
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate">
    <meta http-equiv="Pragma" content="no-cache">
    <meta http-equiv="Expires" content="0">
    <!-- VERSION CACHE BUSTER: <?php echo time(); ?> -->
    <title><?php echo APP_NAME; ?> - FullCalendar</title>
    
    <!-- FullCalendar CSS 
    <link href="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.15/index.global.min.css" rel="stylesheet">
    -->
	
    <!-- Styles personnalisés -->
    <link rel="stylesheet" href="includes/common_styles.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body { 
            font-family: Arial, sans-serif;
            background: #f5f5f5;
            overflow: hidden;
        }
        
        /* Layout principal avec sidebar */
        .layout-wrapper {
            display: flex;
            height: calc(100vh - 50px);
            overflow: hidden;
        }
        
        /* Sidebar gauche */
        .sidebar {
            width: 240px;
            background: #fff;
            border-right: 1px solid #e0e0e0;
            display: flex;
            flex-direction: column;
            overflow-y: auto;
            max-height: 100%;
        }
        
        .sidebar-header {
            padding: 10px;
            background: #e53935;
            color: white;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .sidebar-header h1 {
            font-size: 16px;
            font-weight: normal;
        }
        
        /* Mini calendrier */
        .mini-calendar {
            padding: 8px;
            border-bottom: 1px solid #e0e0e0;
        }
        
        .mini-calendar-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
        }
        
        .mini-calendar-header h3 {
            font-size: 14px;
            font-weight: bold;
        }
        
        .mini-calendar-nav {
            display: flex;
            gap: 5px;
        }
        
        .mini-calendar-nav button {
            background: none;
            border: none;
            cursor: pointer;
            font-size: 16px;
            color: #666;
            padding: 2px 6px;
        }
        
        .mini-calendar-grid {
            display: grid;
            grid-template-columns: repeat(7, 1fr);
            gap: 2px;
        }
        
        .mini-calendar-day-header {
            text-align: center;
            font-size: 11px;
            color: #666;
            padding: 5px 0;
            font-weight: bold;
        }
        
        .mini-calendar-day {
            aspect-ratio: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 12px;
            cursor: pointer;
            border-radius: 50%;
            transition: background 0.2s;
        }
        
        .mini-calendar-day:hover {
            background: #f0f0f0;
        }
        
        .mini-calendar-day.today {
            background: #e53935;
            color: white;
            font-weight: bold;
        }
        
        .mini-calendar-day.other-month {
            color: #ccc;
        }
        
        /* Section recherche client */
        .sidebar-search {
            padding: 8px;
            border-bottom: 1px solid #e0e0e0;
        }
        
        .sidebar-search input {
            width: 100%;
            padding: 6px 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 12px;
        }
        
        /* Liste des techniciens */
        .sidebar-section {
            padding: 8px;
        }
        
        .sidebar-section h3 {
            font-size: 13px;
            font-weight: bold;
            margin-bottom: 10px;
            color: #666;
            text-transform: uppercase;
        }
        
        .technicien-list {
            list-style: none;
        }
        
        .technicien-item {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 6px 0;
            cursor: pointer;
            font-size: 13px;
        }
        
        .technicien-item:hover {
            opacity: 0.7;
        }
        
        .technicien-color {
            width: 16px;
            height: 16px;
            border-radius: 50%;
            flex-shrink: 0;
        }
        
        /* Zone principale */
        .main-content {
            flex: 1;
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }
        
        /* Toolbar en haut (masquée car on utilise celle de FullCalendar) */
        .calendar-toolbar {
            display: none;
        }
        
        .toolbar-left {
            display: flex;
            gap: 10px;
            align-items: center;
        }
        
        .btn-today {
            padding: 6px 16px;
            background: white;
            border: 1px solid #ddd;
            border-radius: 4px;
            cursor: pointer;
            font-size: 13px;
        }
        
        .btn-today:hover {
            background: #f5f5f5;
        }
        
        .nav-arrows {
            display: flex;
            gap: 5px;
        }
        
        .nav-arrow {
            width: 32px;
            height: 32px;
            background: white;
            border: 1px solid #ddd;
            border-radius: 4px;
            cursor: pointer;
            font-size: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .nav-arrow:hover {
            background: #f5f5f5;
        }
        
        .calendar-title {
            font-size: 20px;
            font-weight: normal;
        }
        
        .view-switcher {
            display: flex;
            gap: 0;
            border: 1px solid #ddd;
            border-radius: 4px;
            overflow: hidden;
        }
        
        .view-btn {
            padding: 6px 16px;
            background: white;
            border: none;
            border-right: 1px solid #ddd;
            cursor: pointer;
            font-size: 13px;
        }
        
        .view-btn:last-child {
            border-right: none;
        }
        
        .view-btn:hover {
            background: #f5f5f5;
        }
        
        .view-btn.active {
            background: #e53935;
            color: white;
        }
        
        /* Conteneur du calendrier */
        .calendar-container {
            flex: 1;
            padding: 5px;
            overflow: hidden;
            display: flex;
            flex-direction: column;
            min-height: 0;
        }
        
        /* Footer */
        .calendar-footer {
            background: #fff;
            border-top: 1px solid #e0e0e0;
            padding: 5px 8px;
            font-size: 11px;
            color: #666;
            flex-shrink: 0;
            min-height: 25px;
        }
        
        .footer-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
            max-width: 1400px;
            margin: 0 auto;
        }
        
        #calendar {
            flex: 1;
            background: white;
            border-radius: 4px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            overflow: hidden;
            display: flex;
            flex-direction: column;
            min-height: 0;
        }
        
        #calendar .fc {
            height: 100% !important;
            display: flex;
            flex-direction: column;
        }
        
        #calendar .fc-view-harness {
            flex: 1 !important;
            overflow-y: auto !important;
            overflow-x: hidden !important;
            min-height: 0;
        }
        
        /* Assurer que les créneaux horaires sont visibles jusqu'à 24:00 */
        #calendar .fc-timegrid-slots {
            min-height: 100%;
        }
        
        /* Afficher la toolbar FullCalendar pour navigation */
        .fc-toolbar {
            margin-bottom: 5px !important;
            padding: 6px !important;
            background: #f8f9fa !important;
            border-radius: 4px !important;
        }
        
        /* Séparation visuelle entre les jours avec pseudo-éléments */
        
        /* Masquer les bordures natives de FullCalendar pour éviter les doublons */
        #calendar .fc-timegrid-col.fc-day {
            border-right: none !important;
            position: relative !important;
            padding-right: 8px !important; /* Marge à droite pour espace cliquable */
        }
        
        #calendar .fc-col-header-cell {
            border-right: none !important;
            position: relative !important;
            font-weight: bold;
        }
        
        /* Créer des séparateurs personnalisés avec ::after */
        #calendar .fc-timegrid-col.fc-day::after {
            content: '' !important;
            position: absolute !important;
            right: 4px !important; /* Décalage pour centrer le trait dans la marge */
            top: 0 !important;
            bottom: 0 !important;
            width: 2px !important;
            background: #999 !important;
            z-index: 10 !important;
            pointer-events: none !important; /* Permet de cliquer à travers le trait */
        }
        
        #calendar .fc-timegrid-col.fc-day:last-child::after {
            display: none !important;
        }
        
        /* Séparateurs pour les headers */
        #calendar .fc-col-header-cell::after {
            content: '' !important;
            position: absolute !important;
            right: 0 !important;
            top: 0 !important;
            bottom: 0 !important;
            width: 2px !important;
            background: #999 !important;
            z-index: 10 !important;
        }
        
        #calendar .fc-col-header-cell:last-child::after {
            display: none !important;
        }
        
        /* Ajouter de la marge autour des événements */
        #calendar .fc-timegrid-event-harness {
            margin-right: 6px !important;
            margin-left: 2px !important;
        }
        
        /* Modal événement personnalisé */
        .modal-event {
            display: none;
            position: fixed;
            z-index: 10000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            animation: fadeIn 0.2s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .modal-event-content {
            background-color: white;
            margin: 2% auto;
            padding: 0;
            border-radius: 8px;
            width: 90%;
            max-width: 600px;
            max-height: 85vh;
            box-shadow: 0 8px 10px 1px rgba(0,0,0,0.14), 0 3px 14px 2px rgba(0,0,0,0.12), 0 5px 5px -3px rgba(0,0,0,0.2);
            animation: slideIn 0.2s cubic-bezier(0.4, 0, 0.2, 1);
            overflow: hidden;
            display: flex;
            flex-direction: column;
        }

        .modal-event-header {
            background: #e53935;
            color: #ffffff;
            padding: 14px 16px;
            border-bottom: none;
            position: relative;
            flex-shrink: 0;
        }

        .modal-event-header h2 {
            margin: 0;
            font-size: 18px;
            font-weight: 500;
            color: #ffffff;
            padding-right: 120px;
            text-transform: uppercase;
        }

        .modal-event-actions {
            position: absolute;
            right: 16px;
            top: 16px;
            display: flex;
            gap: 8px;
        }

        .modal-btn {
            width: 40px;
            height: 40px;
            border: none;
            background: none;
            border-radius: 50%;
            cursor: pointer;
            display: flex;
            flex-direction: row-reverse;
            align-items: center;
            justify-content: center;
            font-size: 18px;
            color: #ffffff;
            transition: background-color 0.2s;
        }

        .modal-btn:hover {
            background-color: rgba(255,255,255,0.2);
        }

        .modal-btn-close {
            font-size: 22px;
            font-weight: bold;
        }

        .modal-event-body {
            padding: 0;
            overflow-y: auto;
            flex: 1;
            background: #f5f5f5;
        }

        .modal-section {
            background: #ffffff;
            margin-bottom: 3px;
            padding: 6px 10px;
        }

        .modal-section-collapsible {
            cursor: pointer;
        }

        .modal-section-header {
            display: flex;
            align-items: center;
            gap: 12px;
            font-weight: 600;
            color: #3c4043;
            padding: 4px 0;
            user-select: none;
        }

        .modal-section-header .toggle-icon {
            margin-left: auto;
            transition: transform 0.3s ease;
            font-size: 12px;
        }

        .modal-section-header.collapsed .toggle-icon {
            transform: rotate(-90deg);
        }

        .modal-section-content {
            overflow: hidden;
            transition: max-height 0.3s ease;
        }

        .modal-section-content.collapsed {
            max-height: 0;
            padding: 0;
        }

        .modal-event-row {
            margin-bottom: 4px;
            display: flex;
            align-items: flex-start;
            gap: 10px;
        }

        .modal-event-row:last-child {
            margin-bottom: 0;
        }

        .modal-info-grid {
            display: flex;
            flex-direction: column;
            gap: 8px;
            width: 100%;
        }

        .modal-info-item {
            padding: 8px 12px;
            background: #f8f9fa;
            border-radius: 4px;
            font-size: 13px;
            color: #3c4043;
        }

        .info-value {
            font-weight: 600;
            font-size: 14px;
        }

        .info-value.green {
            color: #1e8e3e;
        }

        .info-value.orange {
            color: #f9ab00;
        }

        .modal-event-badge {
            display: inline-block;
            padding: 6px 12px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .badge-planifie { 
            background: #e8f0fe;
            color: #1967d2;
        }

        .badge-en_cours { 
            background: #e6f4ea;
            color: #1e8e3e;
        }

        .badge-termine { 
            background: #f1f3f4;
            color: #5f6368;
        }

        .badge-annule { 
            background: #fce8e6;
            color: #d93025;
        }

        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        @keyframes slideIn {
            from { 
                transform: translateY(-50px);
                opacity: 0;
            }
            to { 
                transform: translateY(0);
                opacity: 1;
            }
        }
        
        /* Custom editable fields in event modal */
        .modal-event-body .modal-event-row { align-items:flex-start; }
        .modal-event-body .modal-event-row .modal-event-content-text { width:100%; }
        .modal-event-icon { width:40px; text-align:center; font-size:1.2rem; }
        .modal-event-label { font-weight:700; margin-bottom:4px; display:block; }
        .modal-event-value { margin-top:2px; }
        .edit-field { display:none; width:100%; box-sizing:border-box; margin-top:6px; padding:6px 8px; font-size:1rem; border:1px solid #ddd; border-radius:4px; }
        .edit-field[type="time"], .edit-field[type="date"] { width:auto; min-width:120px; padding:6px; }
        #editStart, #editEnd { display:none; }
        #editTechnicien { display:none; }
        .modal-event-row .time-group { display:flex; gap:8px; align-items:center; }
        
        /* Client suggestions autocomplete */
        #clientSuggestions {
            position: fixed;
            background: #fff;
            border: 1px solid #ddd;
            max-height: 200px;
            overflow: auto;
            display: none;
            z-index: 10001;
            border-radius: 4px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.25);
            min-width: 200px;
        }
        
        #clientSuggestions > div {
            padding: 10px 12px;
            cursor: pointer;
            border-bottom: 1px solid #f0f0f0;
        }
        
        #clientSuggestions > div:hover {
            background: #f5f5f5;
        }
        
        #clientSuggestions > div:last-child {
            border-bottom: none;
        }
    </style>
</head>
<body>
    <?php include 'includes/header.php'; ?>
    <div class="layout-wrapper">
        <!-- Sidebar gauche -->
        <div class="sidebar">
            <div class="sidebar-header">
                <span style="font-size:20px">📅</span>
                <h1><?php echo APP_NAME; ?></h1>
            </div>
            
            <!-- Mini calendrier -->
            <div class="mini-calendar">
                <div class="mini-calendar-header">
                    <h3 id="miniCalendarMonth">novembre 2025</h3>
                    <div class="mini-calendar-nav">
                        <button onclick="miniCalendarPrev()">◀</button>
                        <button onclick="miniCalendarNext()">▶</button>
                    </div>
                </div>
                <div class="mini-calendar-grid" id="miniCalendarGrid">
                    <!-- Généré par JavaScript -->
                </div>
            </div>
            
            <!-- Recherche client -->
            <div class="sidebar-search">
                <input type="text" placeholder="Rechercher client..." id="sidebarClientSearch" oninput="filterSidebarClients(this.value)">
            </div>
            
            <!-- Liste des clients -->
            <div class="sidebar-section">
                <h3>Clients</h3>
                <div id="nouveauClientBtn" style="margin-bottom:10px">
                    <button style="padding:6px 12px;background:#1976d2;color:white;border:none;border-radius:4px;cursor:pointer;font-size:12px;width:100%" onclick="window.location.href='clients.php'">
                        ➕ Nouveau client
                    </button>
                </div>
                <ul class="technicien-list" id="sidebarClientList">
                    <!-- Généré par JavaScript -->
                </ul>
            </div>
        </div>
        
        <!-- Zone principale -->
        <div class="main-content">
            <!-- Toolbar -->
            <div class="calendar-toolbar">
                <div class="toolbar-left">
                    <button class="btn-today" onclick="goToToday()">Aujourd'hui</button>
                    <div class="nav-arrows">
                        <button class="nav-arrow" onclick="calendarPrev()">◀</button>
                        <button class="nav-arrow" onclick="calendarNext()">▶</button>
                    </div>
                    <span class="calendar-title" id="calendarTitle">24 — 30 nov. 2025</span>
                </div>
                <div class="view-switcher">
                    <button class="view-btn" onclick="changeView('dayGridMonth')">Mois</button>
                    <button class="view-btn active" onclick="changeView('timeGridWeek')">Semaine</button>
                    <button class="view-btn" onclick="changeView('timeGridDay')">Jour</button>
                    <button class="view-btn" onclick="changeView('listWeek')">Planning</button>
                    <button class="view-btn" onclick="changeView('planningMois')">Planning Mois</button>
                </div>
            </div>
            
            <!-- Calendrier -->
            <div class="calendar-container">
                <div id="calendar"></div>
            </div>
            
            <!-- Footer pour informations futures -->
            <div class="calendar-footer">
                <div class="footer-content">
                    <span id="footerInfo"><!-- Informations à venir --></span>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Modal pour les détails de l'événement -->
    <div id="eventModal" class="modal-event">
        <div class="modal-event-content">
            <div class="modal-event-header">
                <h2 id="eventTitle"></h2>
                <div class="modal-event-actions">
                    <button id="btnEdit" class="modal-btn modal-btn-edit" title="Modifier">✏️</button>
                    <button class="modal-btn modal-btn-delete" title="Supprimer">🗑️</button>
                    <button class="modal-btn modal-btn-close" title="Fermer">✕</button>
                </div>
            </div>
			
            <div class="modal-event-body">
                <!-- Section Date et Horaire -->
                <div class="modal-section">
                    <div class="modal-event-row">
                        <div class="modal-event-icon">🗓️</div>
                        <div class="modal-event-content-text" style="display:flex; justify-content:space-between; align-items:center;">
                            <div>
                                <div class="modal-event-value" id="eventDate"></div>
                                <input class="edit-field" id="editDate" type="date" />
                            </div>
                            <div>
                                <div class="modal-event-value" id="eventTime" style="font-weight:600; color:#e53935;"></div>
                                <div class="time-group" style="display:none;">
                                    <input class="edit-field" id="editStart" type="time" style="display:inline-block; width:auto;" />
                                    <input class="edit-field" id="editEnd" type="time" style="display:inline-block; width:auto;" />
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="modal-event-row">
                        <div class="modal-event-icon">📅</div>
                        <div class="modal-event-content-text" style="display:flex; justify-content:space-between; align-items:center;">
                            <div style="flex:1;">
                                <div class="modal-event-value" id="eventStatutText2">Rendez-vous : <span id="eventStatutText"></span></div>
                                <select class="edit-field" id="editStatut" style="margin-top:6px;">
                                    <option value="planifie">Planifié</option>
                                    <option value="en_cours">En cours</option>
                                    <option value="termine">Terminé</option>
                                    <option value="annule">Annulé</option>
                                </select>
                            </div>
                            <button id="btnClotureIntervention" class="modal-event-value" style="padding:6px 12px; background:#4caf50; color:#fff; border:none; border-radius:4px; cursor:pointer; font-size:0.85em; white-space:nowrap; margin-left:10px;" title="Clôturer l'intervention">✓ Clôturer</button>
                        </div>
                    </div>
                </div>

                <!-- Section Téléphone -->
                <div class="modal-section" id="rowClientTel" style="display:none;">
                    <div class="modal-event-row">
                        <div class="modal-event-icon">📞</div>
                        <div class="modal-event-content-text">
                            <div class="modal-event-value" id="eventClientTel"></div>
                        </div>
                    </div>
                </div>

                <!-- Section Adresse -->
                <div class="modal-section">
                    <div class="modal-event-row" id="rowLieu" style="display:none;">
                        <div class="modal-event-icon">📍</div>
                        <div class="modal-event-content-text">
                            <div class="modal-event-value" id="eventLieu" style="color:#1a73e8;cursor:pointer;"></div>
                            <input class="edit-field" id="editLieu" type="text" />
                        </div>
                    </div>
                </div>

                <!-- Section Infos contrat (collapsible) -->
                <div class="modal-section modal-section-collapsible">
                    <div class="modal-section-header" onclick="toggleSection(this)">
                        <div class="modal-event-icon">📋</div>
                        <span>Infos contrat :</span>
                        <span class="toggle-icon">▼</span>
                    </div>
                    <div class="modal-section-content">
                        <div id="forfaitInfo" class="modal-event-row">
                            <div class="modal-event-content-text">
                                <div class="modal-info-item">
                                    <strong>Aucun forfait trouvé</strong>
                                </div>
                            </div>
                        </div>

                        <div class="modal-event-row">
                            <div class="modal-event-icon">👤</div>
                            <div class="modal-event-content-text">
                                <div class="modal-event-label">Client</div>
                                <div class="modal-event-value" id="eventClient">Aucun</div>
                                <div style="position:relative;">
                                    <input id="editClientSearch" class="edit-field" type="text" placeholder="Rechercher un client (nom, prénom, mobile)" autocomplete="off" />
                                    <input id="editClientId" type="hidden" />
                                    <div id="clientSuggestions"></div>
                                    <button id="btnShowCreateClientForm" class="edit-field" style="display:none; margin-top:6px; padding:4px 8px; background:#1976d2; color:#fff; border:none; border-radius:4px; cursor:pointer; font-size:0.9em;">➕ Nouveau client</button>
                                </div>
                            </div>
                        </div>

                        <div class="modal-event-row">
                            <div class="modal-event-icon">👷</div>
                            <div class="modal-event-content-text" style="display:flex; align-items:center; gap:10px;">
                                <div class="modal-event-label" style="min-width:80px;">Technicien</div>
                                <div class="modal-event-value" id="eventTechnicien" style="flex:1;">Aucun</div>
                                <select id="editTechnicien" class="edit-field" style="flex:1; max-width:200px;"></select>
                            </div>
                        </div>

                        <div class="modal-event-row edit-field" style="display:none;">
                            <div class="modal-event-icon">🚗</div>
                            <div class="modal-event-content-text" style="display:flex; align-items:center; gap:10px;">
                                <div class="modal-event-label" style="min-width:80px;">Véhicule</div>
                                <div class="modal-event-value" id="eventVehicule" style="flex:1; color:#999; font-style:italic;">Auto-détecté</div>
                            </div>
                        </div>

                        <div class="modal-event-row edit-field" style="display:none;">
                            <div class="modal-event-icon">📍</div>
                            <div class="modal-event-content-text" style="display:flex; align-items:center; gap:10px;">
                                <div class="modal-event-label" style="min-width:80px;">Distance</div>
                                <input type="number" id="editDistance" placeholder="km" step="0.1" min="0" 
                                       style="flex:1; max-width:100px; padding:6px; border:1px solid #ccc; border-radius:4px;" />
                                <span style="color:#666; font-size:0.9em;">km (A/R)</span>
                            </div>
                        </div>

                        <div class="modal-event-row edit-field" style="display:none;">
                            <div class="modal-event-icon">⏱️</div>
                            <div class="modal-event-content-text" style="display:flex; align-items:center; gap:10px;">
                                <div class="modal-event-label" style="min-width:80px;">Temps trajet</div>
                                <input type="number" id="editTempsTrajet" placeholder="minutes" step="1" min="0" 
                                       style="flex:1; max-width:100px; padding:6px; border:1px solid #ccc; border-radius:4px;" />
                                <span style="color:#666; font-size:0.9em;">minutes (optionnel)</span>
                            </div>
                        </div>

                        <div id="forfaitDetails" class="modal-event-row" style="display:none;">
                            <div class="modal-info-grid">
                                <div class="modal-info-item">
                                    <span class="info-value green" id="heuresRestantes">0</span> Heure(s) Restante(s)
                                </div>
                                <div class="modal-info-item">
                                    <span id="bonusInfo" class="info-value" style="color:#666;">Bonus : 0 minute(s)</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>


                <!-- Mini-form création client -->
                <div id="miniClientForm" class="edit-field" style="display:none; margin:12px 0; padding:10px; border:1px solid #ddd; border-radius:6px; background:#f9f9f9;">
                    <div style="margin-bottom:6px; font-weight:600;">Créer un nouveau client</div>
                    <div style="display:grid; grid-template-columns: 1fr 1fr; gap:6px;">
                        <input id="newClientNom" type="text" placeholder="Nom *" style="padding:6px; border:1px solid #ccc; border-radius:4px;" />
                        <input id="newClientPrenom" type="text" placeholder="Prénom *" style="padding:6px; border:1px solid #ccc; border-radius:4px;" />
                        <input id="newClientEmail" type="email" placeholder="Email" style="padding:6px; border:1px solid #ccc; border-radius:4px;" />
                        <input id="newClientMobile" type="text" placeholder="Téléphone mobile" style="padding:6px; border:1px solid #ccc; border-radius:4px;" />
                        <input id="newClientFixe" type="text" placeholder="Téléphone fixe" style="padding:6px; border:1px solid #ccc; border-radius:4px;" />
                        <input id="newClientVille" type="text" placeholder="Ville" style="padding:6px; border:1px solid #ccc; border-radius:4px;" />
                        <input id="newClientCodePostal" type="text" placeholder="Code postal" style="padding:6px; border:1px solid #ccc; border-radius:4px;" />
                        <input id="newClientPays" type="text" placeholder="Pays" style="padding:6px; border:1px solid #ccc; border-radius:4px;" />
                        <input id="newClientAdresse" type="text" placeholder="Adresse" style="grid-column: 1 / -1; padding:6px; border:1px solid #ccc; border-radius:4px;" />
                        <input id="newClientEtage" type="text" placeholder="Étage" style="padding:6px; border:1px solid #ccc; border-radius:4px;" />
                        <input id="newClientCodeEntree" type="text" placeholder="Code entrée" style="padding:6px; border:1px solid #ccc; border-radius:4px;" />
                    </div>
                    <div style="display:flex; gap:8px; margin-top:8px;">
                        <button id="btnSaveNewClient" style="padding:6px 12px; background:#4caf50; color:#fff; border:none; border-radius:4px; cursor:pointer;">Enregistrer</button>
                        <button id="btnCancelNewClient" style="padding:6px 12px; background:#999; color:#fff; border:none; border-radius:4px; cursor:pointer;">Annuler</button>
                    </div>
                </div>

                <!-- Description -->
                <div class="modal-section">
                    <div class="modal-event-row">
                        <div class="modal-event-icon">📝</div>
                        <div class="modal-event-content-text">
                            <div class="modal-event-label">Description</div>
                            <div class="modal-event-value" id="eventDescription"></div>
                            <textarea class="edit-field" id="editDescription" rows="4"></textarea>
                        </div>
                    </div>
                </div>

                <!-- Hidden title field - auto-generated from client name -->
                <input id="editTitle" type="hidden" />
            </div>
        </div>
    </div>
    
    <!-- FullCalendar JS -->
    <script src="includes/index.global.min.js"></script>
    <script src="includes/fr.global.min.js"></script>
    <script src="includes/signature-pad.js"></script>
    
    <!-- Modal Signature Clôture -->
    <div id="signatureClotureModal" class="modal-event" style="display:none;">
        <div class="modal-event-content" style="max-width:500px;">
            <div class="modal-event-header">
                <h2>Signature du client</h2>
                <div class="modal-event-actions">
                    <button class="modal-btn modal-btn-close" onclick="closeSignatureClotureModal()" title="Fermer">✕</button>
                </div>
            </div>
            <div class="modal-event-body" style="padding:20px;">
                <p style="margin-bottom:15px;color:#666;">Veuillez signer pour confirmer la fin de l'intervention</p>
                <div style="border:2px solid #ddd;border-radius:4px;background:#fff;margin-bottom:15px;">
                    <canvas id="signatureCanvasCloture" style="width:100%;height:200px;touch-action:none;cursor:crosshair;"></canvas>
                </div>
                <div style="display:flex;gap:10px;justify-content:flex-end;">
                    <button onclick="clearSignatureCloture()" style="padding:8px 16px;background:#999;color:#fff;border:none;border-radius:4px;cursor:pointer;">Effacer</button>
                    <button onclick="validateSignatureCloture()" style="padding:8px 16px;background:#4caf50;color:#fff;border:none;border-radius:4px;cursor:pointer;">✓ Valider</button>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Modal Signature Vente Forfait -->
    <div id="signatureVenteModal" class="modal-event" style="display:none;z-index:10001;">
        <div class="modal-event-content" style="max-width:500px;">
            <div class="modal-event-header">
                <h2>Signature du client</h2>
                <div class="modal-event-actions">
                    <button class="modal-btn modal-btn-close" onclick="closeSignatureVenteModal()" title="Fermer">✕</button>
                </div>
            </div>
            <div class="modal-event-body" style="padding:20px;">
                <p style="margin-bottom:15px;color:#666;">Veuillez signer pour confirmer l'achat du/des forfait(s)</p>
                <div style="border:2px solid #ddd;border-radius:4px;background:#fff;margin-bottom:15px;">
                    <canvas id="signatureCanvasVente" style="width:100%;height:200px;touch-action:none;cursor:crosshair;"></canvas>
                </div>
                <div style="display:flex;gap:10px;justify-content:flex-end;">
                    <button onclick="clearSignatureVente()" style="padding:8px 16px;background:#999;color:#fff;border:none;border-radius:4px;cursor:pointer;">Effacer</button>
                    <button onclick="validateSignatureVente()" style="padding:8px 16px;background:#4caf50;color:#fff;border:none;border-radius:4px;cursor:pointer;">✓ Valider</button>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        // VERSION SCRIPT: <?php echo date('Y-m-d H:i:s'); ?>

        console.log('🔄 AGENDA.PHP LOADED - Version: <?php echo time(); ?>');
        
        // Fonction helper pour formater les nombres au format français (virgule comme séparateur décimal)
        function formatFR(nombre, decimales = 2) {
            if (nombre === null || nombre === undefined) return '0,00';
            return parseFloat(nombre).toFixed(decimales).replace('.', ',');
        }
        
        document.addEventListener('DOMContentLoaded', function() {
			
            // helper : format Date → 'YYYY-MM-DDTHH:MM:SS' (local timezone, no timezone suffix)
            function toLocalMysqlDatetime(date) {
                if (!date) return null;
                const pad = n => String(n).padStart(2, '0');
                const yyyy = date.getFullYear();
                const mm = pad(date.getMonth() + 1);
                const dd = pad(date.getDate());
                const hh = pad(date.getHours());
                const mi = pad(date.getMinutes());
                const ss = pad(date.getSeconds());
                return `${yyyy}-${mm}-${dd}T${hh}:${mi}:${ss}`;
            }
            const calendarEl = document.getElementById('calendar');
            
            const calendar = new FullCalendar.Calendar(calendarEl, {
				
				// Paramètres d'édition (depuis config.php)
				editable: <?php echo FC_EDITABLE ? 'true' : 'false'; ?>,
				eventStartEditable: <?php echo FC_EVENT_START_EDITABLE ? 'true' : 'false'; ?>,
				eventDurationEditable: <?php echo FC_EVENT_DURATION_EDITABLE ? 'true' : 'false'; ?>,
				selectable: <?php echo FC_SELECTABLE ? 'true' : 'false'; ?>,
                
                // Configuration générale
                initialView: '<?php echo FC_INITIAL_VIEW; ?>',
                locale: 'fr',
                timeZone: '<?php echo TIMEZONE; ?>',
                firstDay: <?php echo FC_FIRST_DAY; ?>,
                weekNumbers: <?php echo FC_WEEK_NUMBERS ? 'true' : 'false'; ?>,
                weekText: 'S',
                nowIndicator: <?php echo FC_NOW_INDICATOR ? 'true' : 'false'; ?>,
                allDaySlot: <?php echo FC_ALL_DAY_SLOT ? 'true' : 'false'; ?>,
                height: '<?php echo FC_HEIGHT; ?>',
                
                // Configuration des créneaux horaires
                slotMinTime: '<?php echo SLOT_MIN_TIME; ?>',
                slotMaxTime: '<?php echo SLOT_MAX_TIME; ?>',
                slotDuration: '<?php echo SLOT_DURATION; ?>',
                slotLabelInterval: '<?php echo SLOT_LABEL_INTERVAL; ?>',
                scrollTime: '<?php echo SCROLL_TIME; ?>',
                
                // Barre d'outils
                headerToolbar: {
                    left: 'prev,next today',
                    center: 'title',
                    right: 'dayGridMonth,timeGridWeek,timeGridDay,listWeek'
                },
                buttonText: {
                    today: "Aujourd'hui",
                    month: 'Mois',
                    week: 'Semaine',
                    day: 'Jour',
                    list: 'Planning'
                },
                
                // Format d'affichage
                slotLabelFormat: {
                    hour: '2-digit',
                    minute: '2-digit',
                    hour12: false
                },
                eventTimeFormat: {
                    hour: '2-digit',
                    minute: '2-digit',
                    hour12: false
                },
                
                // Source des événements
                events: function(info, successCallback, failureCallback) {
                    fetch('api/events.php?action=get_events&start=' + info.startStr.split('T')[0] + '&end=' + info.endStr.split('T')[0])
                        .then(response => response.json())
                        .then(data => successCallback(data))
                        .catch(error => {
                            console.error('Erreur lors du chargement des événements:', error);
                            failureCallback(error);
                        });
                },
                
                // Clic sur un événement
                eventClick: function(info) {
                    info.jsEvent.preventDefault();
                    showEventDetails(info.event.id);
                },
                // Clic sur heure -> ouvrir la modale de création (réutilise la modale d'édition)
                dateClick: function(info) {
                    openCreateModal(info);
                },
				// Déclenché quand un événement est déplacé.
				/*eventDrop: function(info) {
					const id = info.event.id;
					const start = toLocalMysqlDatetime(info.event.start);
					const end = info.event.end ? toLocalMysqlDatetime(info.event.end) : null;

					console.log("eventDrop -> id:", id, start, end);

					fetch('update_event.php', {
						method: 'POST',
						headers: {'Content-Type': 'application/json'},
						body: JSON.stringify({ id: id, start: start, end: end })
					})
					.then(resp => resp.json().then(json => ({ok: resp.ok, json})))
					.then(({ok, json}) => {
						if (!ok || json.status !== 'updated') {
							console.error('Erreur update_event (drop) :', json);
							alert('Erreur lors de la mise à jour — annulation du déplacement');
							info.revert(); // revert visuel si erreur
						} else {
							console.log('Mise à jour OK (drop)');
						}
					})
					.catch(err => {
						console.error('Erreur réseau (drop) :', err);
						alert('Erreur réseau — annulation du déplacement');
						info.revert();
					});
				},*/
                eventDrop: function(info) {
                    const id = info.event.id;
                    // Prefer FullCalendar's string representations which respect the calendar timeZone
                    const start = info.event.startStr ? (info.event.startStr.substring(0,19)) : toLocalMysqlDatetime(info.event.start);
                    const end = info.event.endStr ? (info.event.endStr.substring(0,19)) : (info.event.end ? toLocalMysqlDatetime(info.event.end) : null);

                    // Formatage date/heure via l'utilitaire FullCalendar (respecte le timezone du calendrier)
                    // Use event.startStr (ISO-like string provided by FullCalendar in calendar timezone)
                    // e.g. '2025-11-19T06:00:00' — extract date and time parts to avoid timezone conversion issues
                    (function(){
                        const s = info.event.startStr || (info.event.start ? info.event.start.toISOString().split('T').join('T') : null);
                        if (s) {
                            const parts = s.split('T');
                            const datePart = parts[0]; // YYYY-MM-DD
                            const timePart = (parts[1] || '').substring(0,5); // HH:MM
                            const [yyyy,mm,dd] = datePart.split('-');
                            const day = dd + ' ' + mm + ' ' + yyyy;
                            window.__fc_whenDay = day;
                            window.__fc_whenTime = timePart;
                        } else {
                            // fallback to local getters
                            const d = info.event.start;
                            const pad = n => String(n).padStart(2,'0');
                            window.__fc_whenDay = pad(d.getDate()) + ' ' + pad(d.getMonth()+1) + ' ' + d.getFullYear();
                            window.__fc_whenTime = pad(d.getHours()) + ':' + pad(d.getMinutes());
                        }
                    })();
                    const whenDay = window.__fc_whenDay;
                    const whenTime = window.__fc_whenTime;
                    const title = info.event.title || '(sans titre)';

                    const msg = "Le rendez-vous '" + title + "' a été déplacé au " + whenDay + " à " + whenTime + ". Confirmer ?";
                    if (!confirm(msg)) {
                        info.revert();
                        return;
                    }

                    // Persister la modification côté serveur
                    // Use local datetime string (same format as modal saveEdit) to avoid timezone shifts
                    const isoStart = start; // toLocalMysqlDatetime(info.event.start)
                    const isoEnd = end; // toLocalMysqlDatetime(info.event.end)

                    // build payload and include id_technicien when available
                    const payload = { id: id, start: isoStart, end: isoEnd };
                    if (info.event.extendedProps && info.event.extendedProps.id_technicien) {
                        payload.id_technicien = info.event.extendedProps.id_technicien;
                    }

                    console.log('update_event payload', payload);

                    fetch('api/events.php?action=update', {
                        method: 'POST',
                        headers: {'Content-Type': 'application/json'},
                        body: JSON.stringify(payload)
                    })
                    .then(resp => resp.json().then(json => ({ok: resp.ok, json})))
                    .then(({ok, json}) => {
                        if (!ok || json.status !== 'updated') {
                            console.error('Erreur update_event (drop) :', json);
                            alert('Erreur lors de la mise à jour — annulation du déplacement');
                            info.revert();
                        } else {
                            console.log('Mise à jour OK (drop)');
                        }
                    })
                    .catch(err => {
                        console.error('Erreur réseau (drop) :', err);
                        alert('Erreur réseau — annulation du déplacement');
                        info.revert();
                    });
                },
				// Déclenché quand on étire un événement pour changer l'heure.
				eventResize: function(info) {
					const id = info.event.id;
					const start = toLocalMysqlDatetime(info.event.start);
					const end = info.event.end ? toLocalMysqlDatetime(info.event.end) : null;

					console.log("eventResize -> id:", id, start, end);

					fetch('api/events.php?action=update', {
						method: 'POST',
						headers: {'Content-Type': 'application/json'},
						body: JSON.stringify({ id: id, start: start, end: end })
					})
					.then(resp => resp.json().then(json => ({ok: resp.ok, json})))
					.then(({ok, json}) => {
						if (!ok || json.status !== 'updated') {
							console.error('Erreur update_event (resize) :', json);
							alert('Erreur lors de la mise à jour — annulation du redimensionnement');
							info.revert();
						} else {
							console.log('Mise à jour OK (resize)');
						}
					})
					.catch(err => {
						console.error('Erreur réseau (resize) :', err);
						alert('Erreur réseau — annulation du redimensionnement');
						info.revert();
					});
				},
				
                // Style des événements
                eventDidMount: function(info) {
                    info.el.style.cursor = 'pointer';
                    
                    // Appliquer un style différent pour les rendez-vous terminés
                    if (info.event.extendedProps && info.event.extendedProps.statut === 'termine') {
                        // Ajouter une classe CSS pour les événements terminés
                        info.el.style.opacity = '0.5';
                        info.el.style.filter = 'grayscale(30%)';
                        info.el.style.fontStyle = 'italic';
                        
                        // Ajouter une petite icône pour indiquer que c'est terminé
                        const titleEl = info.el.querySelector('.fc-event-title');
                        if (titleEl && !titleEl.textContent.startsWith('✓ ')) {
                            titleEl.textContent = '✓ ' + titleEl.textContent;
                        }
                    }
                },
                
                // Options de vue
                views: {
                    dayGridMonth: {
                        titleFormat: { year: 'numeric', month: 'long' }
                    },
                    timeGridWeek: {
                        titleFormat: { year: 'numeric', month: 'long', day: 'numeric' },
                        dayHeaderFormat: { weekday: 'short', day: 'numeric', month: 'numeric' }
                    },
                    timeGridDay: {
                        titleFormat: { year: 'numeric', month: 'long', day: 'numeric', weekday: 'long' }
                    },
                    listWeek: {
                        titleFormat: { year: 'numeric', month: 'long', day: 'numeric' }
                    }
                }
            });
            
            calendar.render();
            
            // Forcer les bordures verticales après le rendu (JavaScript)
            setTimeout(function() {
                // Cibler toutes les colonnes de jour
                const cols = document.querySelectorAll('.fc-timegrid-col, .fc-day');
                cols.forEach((col, index) => {
                    // Ne pas mettre de bordure épaisse sur la dernière colonne
                    if (index < cols.length - 1 || cols.length === 1) {
                        col.style.borderRight = '3px solid #999';
                    }
                });
                
                // Forcer les headers
                const headers = document.querySelectorAll('.fc-col-header-cell');
                headers.forEach((header, index) => {
                    if (index < headers.length - 1 || headers.length === 1) {
                        header.style.borderRight = '3px solid #999';
                    }
                });
                
                console.log('Bordures verticales appliquées sur', cols.length, 'colonnes');
            }, 500);
            
            // expose calendar globally for modal actions
            window.calendar = calendar;
            
            // Ouvrir automatiquement un événement si passé en paramètre ?open_event=123
            const urlParams = new URLSearchParams(window.location.search);
            const openEventId = urlParams.get('open_event');
            const autoClose = urlParams.get('auto_close') === '1';
            
            if (openEventId) {
                if (autoClose) {
                    // Lancer directement la clôture sans attendre le calendrier
                    console.log('Lancement auto de la clôture pour RDV', openEventId);
                    setTimeout(() => {
                        clotureIntervention(parseInt(openEventId));
                    }, 800); // Un peu plus de délai pour que les fonctions soient chargées
                } else {
                    // Attendre que le calendrier soit chargé pour ouvrir le modal
                    setTimeout(() => {
                        const event = calendar.getEventById(openEventId);
                        if (event) {
                            // Simuler un clic sur l'événement pour ouvrir le modal
                            openEventDetails(event.id);
                        } else {
                            console.warn('Événement non trouvé dans la vue courante, ouverture directe des détails:', openEventId);
                            showEventDetails(parseInt(openEventId));
                        }
                    }, 500);
                }
            }
            
            // charger la liste des techniciens pour le sélecteur
            if (typeof loadTechniciensList === 'function') loadTechniciensList();
            // init clients autocomplete
            initClientsAutocomplete();
        });
        // Clients autocomplete utilities
        let _clientDebounceTimer = null;
        window._clientsCache = {};
        function initClientsAutocomplete(){
            const input = document.getElementById('editClientSearch');
            const suggestions = document.getElementById('clientSuggestions');
            if (!input || !suggestions) return;

            input.addEventListener('input', function(e){
                const q = this.value.trim();
                clearTimeout(_clientDebounceTimer);
                _clientDebounceTimer = setTimeout(() => {
                    if (q.length < 1) { suggestions.style.display='none'; return; }
                    fetchClients(q).then(list => renderClientSuggestions(list));
                }, 250);
            });

            input.addEventListener('focus', function(){
                const q = this.value.trim();
                if (q.length > 0) fetchClients(q).then(list => renderClientSuggestions(list));
            });

            document.addEventListener('click', function(ev){
                if (!ev.target.closest || (!ev.target.closest('#editClientSearch') && !ev.target.closest('#clientSuggestions'))) {
                    suggestions.style.display = 'none';
                }
            });
        }

        function fetchClients(q){
            return fetch('api/clients.php?action=list&q=' + encodeURIComponent(q) + '&limit=30')
                .then(r => r.json())
                .then(json => (json.clients || []).map(c => ({ 
                    id: c.id, 
                    display: c.display, 
                    prenom: c.prenom, 
                    nom: c.nom, 
                    telephone_mobile: c.telephone_mobile,
                    telephone_fixe: c.telephone_fixe,
                    adresse: c.adresse,
                    code_postal: c.code_postal,
                    ville: c.ville
                })))
                .catch(err => { console.error('Erreur fetchClients', err); return []; });
        }

        function renderClientSuggestions(list){
            const suggestions = document.getElementById('clientSuggestions');
            const btnCreate = document.getElementById('btnShowCreateClientForm');
            const input = document.getElementById('editClientSearch');
            
            suggestions.innerHTML = '';
            if (!list || list.length === 0) { 
                suggestions.style.display='none'; 
                // show create button if search has content
                const searchVal = input.value.trim();
                if (searchVal && btnCreate) btnCreate.style.display = 'block';
                return; 
            }
            if (btnCreate) btnCreate.style.display = 'none';
            list.forEach(c => {
                const div = document.createElement('div');
                div.style.padding = '8px';
                div.style.cursor = 'pointer';
                div.style.borderBottom = '1px solid #eee';
                
                // Afficher le téléphone mobile en priorité, sinon le fixe
                const tel = c.telephone_mobile || c.telephone_fixe || '';
                div.textContent = c.display + (tel ? ' — ' + tel : '');
                
                div.addEventListener('click', function(){ selectClient(c); });
                suggestions.appendChild(div);
                // cache
                window._clientsCache[c.id] = c;
            });
            
            // Positionner la liste sous le champ input (position fixed)
            if (input) {
                const rect = input.getBoundingClientRect();
                suggestions.style.top = (rect.bottom + 2) + 'px';
                suggestions.style.left = rect.left + 'px';
                suggestions.style.width = rect.width + 'px';
            }
            
            suggestions.style.display = 'block';
        }

        function selectClient(c){
            const input = document.getElementById('editClientSearch');
            const hid = document.getElementById('editClientId');
            const suggestions = document.getElementById('clientSuggestions');
            const btnCreate = document.getElementById('btnShowCreateClientForm');
            input.value = c.display;
            hid.value = c.id;
            suggestions.style.display = 'none';
            if (btnCreate) btnCreate.style.display = 'none';
            
            // update client info display (telephone and address)
            const clientEl = document.getElementById('eventClient');
            const rowTel = document.getElementById('rowClientTel');
            
            if (clientEl) {
                clientEl.textContent = c.display;
                if (c.id) {
                    clientEl.style.cursor = 'pointer';
                    clientEl.title = 'Ouvrir la fiche client';
                    clientEl.onclick = function(){
                        window.location.href = 'client_dashboard.php?client_id=' + encodeURIComponent(c.id);
                    };
                }
            }
            
            // afficher téléphone si présent
            const tel = c.telephone_mobile || c.telephone_fixe || '';
            console.log('selectClient - téléphone:', tel, 'mobile:', c.telephone_mobile, 'fixe:', c.telephone_fixe);
            if (tel && rowTel) {
                const telEl = document.getElementById('eventClientTel');
                if (telEl) telEl.textContent = tel;
                rowTel.style.removeProperty('display');
                console.log('rowClientTel affiché avec:', tel);
            } else if (rowTel) {
                rowTel.style.display = 'none';
                console.log('rowClientTel masqué (pas de téléphone)');
            }
            
            // construire l'adresse complète pour le champ Lieu
            let adresse = '';
            if (c.adresse) adresse += c.adresse;
            if (c.code_postal || c.ville) {
                if (adresse) adresse += ', ';
                adresse += (c.code_postal ? c.code_postal + ' ' : '') + (c.ville || '');
            }
            
            // mettre à jour le champ Lieu avec l'adresse du client
            const lieuEl = document.getElementById('editLieu');
            if (lieuEl && adresse) {
                lieuEl.value = adresse;
                // aussi mettre à jour l'affichage si en mode lecture avec lien Google Maps
                const eventLieuEl = document.getElementById('eventLieu');
                if (eventLieuEl) {
                    const encodedAddress = encodeURIComponent(adresse);
                    eventLieuEl.innerHTML = `<a href="https://www.google.com/maps/search/?api=1&query=${encodedAddress}" target="_blank" style="color:#1a73e8;text-decoration:none;" onmouseover="this.style.textDecoration='underline'" onmouseout="this.style.textDecoration='none'">${adresse} 🗺️</a>`;
                }
                // afficher la ligne lieu
                const rowLieu = document.getElementById('rowLieu');
                if (rowLieu) rowLieu.style.display = '';
            }
            
            // Charger les informations de forfait pour ce client
            if (c.id) {
                loadClientForfait(c.id);
            }
        }

        // Fonction pour charger les forfaits d'un client
        function loadClientForfait(clientId) {
            // Charger d'abord les infos du client pour avoir le bonus
            fetch('api/clients.php?action=list&limit=1000')
                .then(resp => resp.json())
                .then(clientsData => {
                    const client = clientsData.clients.find(c => c.id == clientId);
                    const bonusHeures = client ? parseFloat(client.heure_bonus || 0) : 0;
                    
                    // Ensuite charger le forfait
                    return fetch('api/forfaits.php?action=list&client_id=' + clientId)
                        .then(resp => {
                            if (!resp.ok) {
                                throw new Error('HTTP error ' + resp.status);
                            }
                            return resp.json();
                        })
                        .then(data => {
                            // Ajouter le bonus du client aux données
                            data.heure_bonus = bonusHeures;
                            data.heure_bonus_minutes = Math.round(bonusHeures * 60);
                            return data;
                        });
                })
                .then(data => {
                    if (data.error) {
                        console.warn('Erreur chargement forfait:', data.error);
                        
                        // Afficher quand même le bonus/malus si disponible
                        const bonusMinutes = Math.round(data.heure_bonus_minutes || 0);
                        const bonusColor = bonusMinutes >= 0 ? '#4caf50' : '#f44336';
                        const bonusSign = bonusMinutes >= 0 ? '+' : '';
                        const bonusText = `${bonusSign}${bonusMinutes} min`;
                        
                        const forfaitInfo = document.getElementById('forfaitInfo');
                        if (forfaitInfo) {
                            forfaitInfo.innerHTML = `
                                <div class="modal-event-content-text">
                                    <div class="modal-info-item" style="display:flex; justify-content:space-between; align-items:center; gap:15px;">
                                        <div style="flex:1;">
                                            <strong>Aucun forfait trouvé</strong>
                                        </div>
                                        <div style="text-align:right; white-space:nowrap; padding:4px 10px; background:${bonusColor}15; border-radius:4px; border:1px solid ${bonusColor}40;">
                                            <div style="color:${bonusColor}; font-weight:bold; font-size:1em;">${bonusText}</div>
                                            <div style="color:#666; font-size:0.75em; margin-top:1px;">bonus/malus</div>
                                        </div>
                                    </div>
                                </div>
                            `;
                        }
                        const forfaitDetails = document.getElementById('forfaitDetails');
                        if (forfaitDetails) forfaitDetails.style.display = 'none';
                        return;
                    }
                    
                    const forfaitInfo = document.getElementById('forfaitInfo');
                    const forfaitDetails = document.getElementById('forfaitDetails');
                    
                    // Calculer bonus/malus (commun aux deux cas)
                    const bonusMinutes = Math.round(data.heure_bonus_minutes || 0);
                    const bonusColor = bonusMinutes >= 0 ? '#4caf50' : '#f44336';
                    const bonusSign = bonusMinutes >= 0 ? '+' : '';
                    const bonusText = `${bonusSign}${bonusMinutes} min`;
                    
                    if (data.forfait_actif) {
                        // CAS 1: Heures restantes sur forfait
                        const f = data.forfait_actif;
                        const totalHeuresRestantes = formatFR(data.total_heures_restantes, 2);
                        const heuresRestantesMinutes = Math.round(data.total_heures_restantes * 60);
                        
                        if (forfaitInfo) {
                            forfaitInfo.innerHTML = `
                                <div class="modal-event-content-text">
                                    <div style="display:flex; justify-content:space-between; align-items:flex-start; gap:10px;">
                                        <div style="flex:1;">
                                            <div style="margin-bottom:8px;">
                                                <strong style="font-size:1.05em;">Forfait en cours</strong>
                                            </div>
                                            <div style="padding:8px; background:#f5f5f5; border-radius:4px;">
                                                <div style="font-weight:600; color:#333;">${f.type_forfait_nom || 'Forfait'}</div>
                                                <div style="font-size:0.85em; color:#666; margin-top:2px;">${f.heures_total}h achetées • ${heuresRestantesMinutes} min restantes</div>
                                            </div>
                                        </div>
                                        <div style="display:flex; flex-direction:column; gap:6px; align-items:flex-end;">
                                            <div style="text-align:center; padding:6px 12px; background:${data.total_heures_restantes > 2 ? '#4caf50' : '#ff9800'}15; border-radius:4px; border:1px solid ${data.total_heures_restantes > 2 ? '#4caf50' : '#ff9800'}40; min-width:70px;">
                                                <div style="color:${data.total_heures_restantes > 2 ? '#4caf50' : '#ff9800'}; font-weight:bold; font-size:1.1em;">${totalHeuresRestantes}h</div>
                                                <div style="color:#666; font-size:0.7em; margin-top:1px;">restantes</div>
                                            </div>
                                            <div style="text-align:center; padding:6px 12px; background:${bonusColor}08; border-radius:4px; border:1px solid ${bonusColor}30; min-width:70px;">
                                                <div style="color:${bonusColor}; font-weight:bold; font-size:1.1em;">${bonusText}</div>
                                                <div style="color:#666; font-size:0.7em; margin-top:1px;">bonus/malus</div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            `;
                        }
                        if (forfaitDetails) forfaitDetails.style.display = 'none';
                        
                    } else {
                        // CAS 2: Aucune heure restante
                        let forfaitNom = 'Aucun forfait';
                        let dateAchat = '';
                        
                        if (data.dernier_forfait) {
                            const df = data.dernier_forfait;
                            forfaitNom = df.type_forfait_nom || 'Forfait';
                            const saleDate = df.date_vente || df.created_at;
                            dateAchat = saleDate ? new Date(saleDate).toLocaleDateString('fr-FR') : '';
                        }
                        
                        if (forfaitInfo) {
                            forfaitInfo.innerHTML = `
                                <div class="modal-event-content-text">
                                    <div style="display:flex; justify-content:space-between; align-items:flex-start; gap:10px;">
                                        <div style="flex:1;">
                                            <div style="margin-bottom:8px;">
                                                <strong style="font-size:1.05em;">Dernier forfait acheté</strong>
                                            </div>
                                            <div style="padding:8px; background:#f5f5f5; border-radius:4px;">
                                                <div style="font-weight:600; color:#333;">${forfaitNom}</div>
                                                ${dateAchat ? `<div style="font-size:0.85em; color:#666; margin-top:2px;">Acheté le ${dateAchat}</div>` : ''}
                                            </div>
                                        </div>
                                        <div style="display:flex; flex-direction:column; gap:6px; align-items:flex-end;">
                                            <div style="text-align:center; padding:6px 12px; background:#f4433615; border-radius:4px; border:1px solid #f4433640; min-width:70px;">
                                                <div style="color:#f44336; font-weight:bold; font-size:1.1em;">0h</div>
                                                <div style="color:#f44336; font-size:0.7em; margin-top:1px;">aucune heure</div>
                                            </div>
                                            <div style="text-align:center; padding:6px 12px; background:${bonusColor}08; border-radius:4px; border:1px solid ${bonusColor}30; min-width:70px;">
                                                <div style="color:${bonusColor}; font-weight:bold; font-size:1.1em;">${bonusText}</div>
                                                <div style="color:#666; font-size:0.7em; margin-top:1px;">bonus/malus</div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            `;
                        }
                        if (forfaitDetails) forfaitDetails.style.display = 'none';
                    }
                })
                .catch(err => {
                    console.error('Erreur chargement forfait:', err);
                    // Afficher message par défaut en cas d'erreur
                    const forfaitInfo = document.getElementById('forfaitInfo');
                    if (forfaitInfo) {
                        forfaitInfo.innerHTML = `
                            <div class="modal-event-content-text">
                                <div class="modal-info-item">
                                    <strong>Aucun forfait trouvé</strong>
                                </div>
                            </div>
                        `;
                    }
                    const forfaitDetails = document.getElementById('forfaitDetails');
                    if (forfaitDetails) forfaitDetails.style.display = 'none';
                });
        }

        function showCreateClientForm(){
            const form = document.getElementById('miniClientForm');
            const btn = document.getElementById('btnShowCreateClientForm');
            if (form) form.style.display = 'block';
            if (btn) btn.style.display = 'none';
            // pre-fill with search value if any
            const searchVal = document.getElementById('editClientSearch').value.trim();
            if (searchVal) {
                const parts = searchVal.split(' ').filter(p => p.trim() !== '');
                if (parts.length === 1) { document.getElementById('newClientNom').value = parts[0]; }
                else if (parts.length >= 2) { document.getElementById('newClientPrenom').value = parts[0]; document.getElementById('newClientNom').value = parts.slice(1).join(' '); }
            }
        }

        function hideCreateClientForm(){
            const form = document.getElementById('miniClientForm');
            const btn = document.getElementById('btnShowCreateClientForm');
            if (form) form.style.display = 'none';
            if (btn) btn.style.display = 'block';
            document.getElementById('newClientNom').value = '';
            document.getElementById('newClientPrenom').value = '';
            document.getElementById('newClientEmail').value = '';
            document.getElementById('newClientMobile').value = '';
            document.getElementById('newClientFixe').value = '';
            document.getElementById('newClientVille').value = '';
            document.getElementById('newClientCodePostal').value = '';
            document.getElementById('newClientPays').value = '';
            document.getElementById('newClientAdresse').value = '';
            document.getElementById('newClientEtage').value = '';
            document.getElementById('newClientCodeEntree').value = '';
        }

        function saveNewClient(){
            const nom = document.getElementById('newClientNom').value.trim();
            const prenom = document.getElementById('newClientPrenom').value.trim();
            const email = document.getElementById('newClientEmail').value.trim();
            const telephone_mobile = document.getElementById('newClientMobile').value.trim();
            const telephone_fixe = document.getElementById('newClientFixe').value.trim();
            const ville = document.getElementById('newClientVille').value.trim();
            const code_postal = document.getElementById('newClientCodePostal').value.trim();
            const pays = document.getElementById('newClientPays').value.trim();
            const adresse = document.getElementById('newClientAdresse').value.trim();
            const etage = document.getElementById('newClientEtage').value.trim();
            const code_entree = document.getElementById('newClientCodeEntree').value.trim();
            if (!prenom && !nom) return alert('Prénom ou nom requis');

            fetch('api/clients.php?action=create', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({ nom, prenom, email, telephone_mobile, telephone_fixe, ville, code_postal, pays, adresse, etage, code_entree })
            })
            .then(r => r.json())
            .then(json => {
                if (json.error) { alert('Erreur: ' + json.error); return; }
                const newId = json.id;
                const display = (prenom ? prenom + ' ' : '') + nom;
                // select the newly created client
                selectClient({ id: newId, display: display.trim(), prenom: prenom, nom: nom, telephone_mobile: telephone_mobile });
                hideCreateClientForm();
                alert('Client créé avec succès');
                
                // S'assurer que le bouton d'enregistrement du rendez-vous est activé
                const btnEdit = document.getElementById('btnEdit');
                if (btnEdit) {
                    btnEdit.disabled = false;
                    btnEdit.style.opacity = '1';
                    btnEdit.style.cursor = 'pointer';
                }
            })
            .catch(err => { console.error(err); alert('Erreur réseau lors de la création du client'); });
        }

        // attach handlers for mini-form
        document.addEventListener('DOMContentLoaded', function(){
            const btnShow = document.getElementById('btnShowCreateClientForm');
            const btnSave = document.getElementById('btnSaveNewClient');
            const btnCancel = document.getElementById('btnCancelNewClient');
            if (btnShow) btnShow.addEventListener('click', showCreateClientForm);
            if (btnSave) btnSave.addEventListener('click', saveNewClient);
            if (btnCancel) btnCancel.addEventListener('click', hideCreateClientForm);
            
            // Écouteur pour le changement de technicien - auto-détecte le véhicule
            const selectTech = document.getElementById('editTechnicien');
            if (selectTech) {
                selectTech.addEventListener('change', function() {
                    loadTechnicienVehicle(this.value);
                });
            }
        });
        
        // Charger et remplir le sélecteur des techniciens (Prénom Nom)
        function loadTechniciensList(){
            return fetch('api/techniciens.php?action=list&actifs_only=1')
                .then(resp => resp.json())
                .then(data => {
                    window._techniciensList = data || [];
                    const sel = document.getElementById('editTechnicien');
                    if (!sel) return data;
                    // conserver la valeur actuelle si possible
                    const current = sel.value;
                    sel.innerHTML = '';
                    const empty = document.createElement('option'); empty.value = ''; empty.textContent = '(Aucun)'; sel.appendChild(empty);
                    data.forEach(t => {
                        const opt = document.createElement('option');
                        opt.value = t.id;
                        const name = (t.prenom ? t.prenom + ' ' : '') + (t.nom || '');
                        opt.textContent = name.trim();
                        sel.appendChild(opt);
                    });
                    // restore value if still present
                    if (current) sel.value = current;
                    return data;
                })
                .catch(err => { console.error('Erreur loadTechniciensList:', err); return []; });
        }
        
        // Charger le véhicule d'un technicien
        function loadTechnicienVehicle(idTechnicien) {
            if (!idTechnicien) {
                document.getElementById('eventVehicule').innerHTML = '<em style="color:#999;">Aucun technicien sélectionné</em>';
                return Promise.resolve(null);
            }
            
            return fetch(`api/techniciens.php?action=get_vehicle&id_technicien=${idTechnicien}`)
                .then(resp => resp.json())
                .then(data => {
                    const vehiculeEl = document.getElementById('eventVehicule');
                    if (data.vehicle) {
                        vehiculeEl.innerHTML = `${data.vehicle.nom} <span style="background:#2196f3;color:white;padding:2px 6px;border-radius:3px;font-size:0.85em;margin-left:5px;">${data.vehicle.immatriculation}</span>`;
                        vehiculeEl.style.fontStyle = 'normal';
                        vehiculeEl.style.color = '#333';
                        return data.vehicle;
                    } else {
                        vehiculeEl.innerHTML = '<em style="color:#f44336;">⚠️ Aucun véhicule attribué à ce technicien</em>';
                        vehiculeEl.style.fontStyle = 'italic';
                        return null;
                    }
                })
                .catch(err => {
                    console.error('Erreur loadTechnicienVehicle:', err);
                    document.getElementById('eventVehicule').innerHTML = '<em style="color:#999;">Erreur chargement véhicule</em>';
                    return null;
                });
        }

        // Toggle collapsible sections
        function toggleSection(header) {
            const content = header.nextElementSibling;
            const icon = header.querySelector('.toggle-icon');
            
            if (content.classList.contains('collapsed')) {
                content.classList.remove('collapsed');
                header.classList.remove('collapsed');
            } else {
                content.classList.add('collapsed');
                header.classList.add('collapsed');
            }
        }

        // Fonction pour afficher les détails d'un événement
        function showEventDetails(eventId) {
            fetch('api/events.php?action=get&id=' + eventId)
                .then(response => response.json())
                .then(data => {
                            // store current id and data
                            window.currentEventId = data.id;
                            window.currentEventData = data;

                            const eventTitleEl = document.getElementById('eventTitle');
                            eventTitleEl.textContent = data.titre;
                            
                            // Appliquer la couleur au titre selon avance immédiate
                            if (data.client_avance_imme == 1) {
                                eventTitleEl.style.backgroundColor = '#4caf50';
                                eventTitleEl.style.color = '#fff';
                                eventTitleEl.style.padding = '12px';
                                eventTitleEl.style.borderRadius = '4px';
                            } else if (data.client_id) {
                                eventTitleEl.style.backgroundColor = '#f44336';
                                eventTitleEl.style.color = '#fff';
                                eventTitleEl.style.padding = '12px';
                                eventTitleEl.style.borderRadius = '4px';
                            } else {
                                // Pas de client, style par défaut
                                eventTitleEl.style.backgroundColor = '';
                                eventTitleEl.style.color = '';
                                eventTitleEl.style.padding = '';
                                eventTitleEl.style.borderRadius = '';
                            }
                            
                    document.getElementById('eventDate').textContent = formatDate(data.date_rdv);
                    document.getElementById('eventTime').textContent = data.heure_debut.substring(0, 5) + ' - ' + data.heure_fin.substring(0, 5);
                    
                    // Afficher le lieu avec lien Google Maps
                    // Priorité : adresse du client > champ lieu
                    let lieuText = '';
                    if (data.client_adresse && data.client_adresse.trim()) {
                        lieuText += data.client_adresse.trim();
                    }
                    if (data.code_postal || data.ville) {
                        if (lieuText) lieuText += ', ';
                        lieuText += (data.code_postal ? data.code_postal.trim() + ' ' : '') + (data.ville ? data.ville.trim() : '');
                    }
                    if (!lieuText || lieuText === ', ') {
                        lieuText = data.lieu || 'Non spécifié';
                    }
                    console.log('LieuText construit:', lieuText, 'depuis:', {adresse: data.client_adresse, cp: data.code_postal, ville: data.ville});
                    
                    const eventLieuEl = document.getElementById('eventLieu');
                    if (eventLieuEl) {
                        if (lieuText && lieuText !== 'Non spécifié') {
                            const encodedAddress = encodeURIComponent(lieuText);
                            eventLieuEl.innerHTML = `<a href="https://www.google.com/maps/search/?api=1&query=${encodedAddress}" target="_blank" style="color:#1a73e8;text-decoration:none;" onmouseover="this.style.textDecoration='underline'" onmouseout="this.style.textDecoration='none'">${lieuText} 🗺️</a>`;
                        } else {
                            eventLieuEl.textContent = 'Non spécifié';
                        }
                    }
                    
                    document.getElementById('eventDescription').textContent = data.description || 'Aucune description';
                            // populate edit fields
                            document.getElementById('editTitle').value = data.titre || '';
                            document.getElementById('editDate').value = data.date_rdv || '';
                            // heure_debut / heure_fin stored as HH:MM:SS
                            document.getElementById('editStart').value = data.heure_debut ? data.heure_debut.substring(0,5) : '';
                            document.getElementById('editEnd').value = data.heure_fin ? data.heure_fin.substring(0,5) : '';
                            
                            // Utiliser l'adresse complète du client pour le champ lieu (avec CP et ville)
                            let editLieuValue = '';
                            if (data.client_adresse && data.client_adresse.trim()) {
                                editLieuValue += data.client_adresse.trim();
                            }
                            if (data.code_postal || data.ville) {
                                if (editLieuValue) editLieuValue += ', ';
                                editLieuValue += (data.code_postal ? data.code_postal.trim() + ' ' : '') + (data.ville ? data.ville.trim() : '');
                            }
                            if (!editLieuValue || editLieuValue === ', ') {
                                editLieuValue = data.lieu || '';
                            }
                            document.getElementById('editLieu').value = editLieuValue;
                            
                            document.getElementById('editDescription').value = data.description || '';
                            document.getElementById('editStatut').value = data.statut || 'planifie';
                            // set technician select if present
                            const sel = document.getElementById('editTechnicien');
                            if (sel) {
                                sel.value = data.id_technicien ? data.id_technicien : '';
                                // Charger le véhicule du technicien
                                if (data.id_technicien) {
                                    loadTechnicienVehicle(data.id_technicien);
                                }
                            }
                            
                            // Charger distance et temps trajet si présents
                            const editDistance = document.getElementById('editDistance');
                            const editTempsTrajet = document.getElementById('editTempsTrajet');
                            if (editDistance) editDistance.value = data.distance_km || '';
                            if (editTempsTrajet) editTempsTrajet.value = data.temps_trajet_minutes || '';
                            
                    const statutBadge = document.getElementById('eventStatutText');
                    const statutTexte = {
                        'planifie': 'Planifié',
                        'en_cours': 'En cours',
                        'termine': 'Terminé',
                        'annule': 'Annulé'
                    };
                    statutBadge.textContent = statutTexte[data.statut] || data.statut;
                    statutBadge.className = 'modal-event-badge badge-' + data.statut;
                    // technicien display
                    const techEl = document.getElementById('eventTechnicien');
                    if (techEl) {
                        if (data.tech_nom) {
                            techEl.textContent = (data.tech_prenom ? data.tech_prenom + ' ' : '') + data.tech_nom;
                        } else {
                            techEl.textContent = 'Aucun';
                        }
                    }
                            // client display
                            const clientEl = document.getElementById('eventClient');
                            const rowTel = document.getElementById('rowClientTel');
                            if (clientEl) {
                                if (data.client_nom) {
                                    let clientDisplay = ((data.client_prenom ? data.client_prenom + ' ' : '') + data.client_nom).trim();
                                    
                                    // Ajouter l'indicateur d'avance immédiate si activé
                                    if (data.client_avance_imme == 1) {
                                        clientDisplay += ' 💚 Avance immédiate activée';
                                    }
                                    
                                    clientEl.textContent = clientDisplay;
                                    
                                    // Appliquer la couleur de fond selon avance immédiate
                                    if (data.client_avance_imme == 1) {
                                        clientEl.style.backgroundColor = '#4caf50';
                                        clientEl.style.color = '#fff';
                                        clientEl.style.padding = '8px';
                                        clientEl.style.borderRadius = '4px';
                                    } else {
                                        clientEl.style.backgroundColor = '#f44336';
                                        clientEl.style.color = '#fff';
                                        clientEl.style.padding = '8px';
                                        clientEl.style.borderRadius = '4px';
                                    }
                                    
                                    // set hidden fields + input for edit
                                    const hid = document.getElementById('editClientId'); if (hid) hid.value = data.client_id ? data.client_id : '';
                                    const inp = document.getElementById('editClientSearch'); if (inp) inp.value = ((data.client_prenom ? data.client_prenom + ' ' : '') + data.client_nom).trim();
                                    if (data.client_id) {
                                        clientEl.style.cursor = 'pointer';
                                        clientEl.title = 'Ouvrir la fiche client';
                                        clientEl.onclick = function(){
                                            window.location.href = 'client_dashboard.php?client_id=' + encodeURIComponent(data.client_id);
                                        };
                                    } else {
                                        clientEl.style.cursor = '';
                                        clientEl.title = '';
                                        clientEl.onclick = null;
                                    }
                                    
                                    // afficher téléphone si présent (priorité mobile)
                                    const tel = data.client_telephone_mobile || data.client_telephone_fixe || '';
                                    if (tel && rowTel) {
                                        const telEl = document.getElementById('eventClientTel');
                                        if (telEl) telEl.textContent = tel;
                                        rowTel.style.removeProperty('display');
                                    } else if (rowTel) {
                                        rowTel.style.display = 'none';
                                    }
                                    
                                    // afficher adresse si présente
                                    let adresse = '';
                                    if (data.client_adresse) adresse += data.client_adresse;
                                    if (data.code_postal || data.ville) {
                                        if (adresse) adresse += ', ';
                                        adresse += (data.code_postal ? data.code_postal + ' ' : '') + (data.ville || '');
                                    }
                                    
                                    // Utiliser rowLieu pour afficher l'adresse du client avec lien Google Maps
                                    const rowLieu = document.getElementById('rowLieu');
                                    if (adresse && rowLieu) {
                                        const lieuEl = document.getElementById('eventLieu');
                                        if (lieuEl) {
                                            const encodedAddress = encodeURIComponent(adresse);
                                            lieuEl.innerHTML = `<a href="https://www.google.com/maps/search/?api=1&query=${encodedAddress}" target="_blank" style="color:#1a73e8;text-decoration:none;" onmouseover="this.style.textDecoration='underline'" onmouseout="this.style.textDecoration='none'">${adresse} 🗺️</a>`;
                                        }
                                        rowLieu.style.removeProperty('display');
                                    } else if (rowLieu && data.lieu) {
                                        const lieuEl = document.getElementById('eventLieu');
                                        if (lieuEl) {
                                            const encodedAddress = encodeURIComponent(data.lieu);
                                            lieuEl.innerHTML = `<a href="https://www.google.com/maps/search/?api=1&query=${encodedAddress}" target="_blank" style="color:#1a73e8;text-decoration:none;" onmouseover="this.style.textDecoration='underline'" onmouseout="this.style.textDecoration='none'">${data.lieu} 🗺️</a>`;
                                        }
                                        rowLieu.style.removeProperty('display');
                                    } else if (rowLieu) {
                                        rowLieu.style.display = 'none';
                                    }
                                } else {
                                    clientEl.textContent = 'Aucun';
                                    clientEl.style.backgroundColor = '';
                                    clientEl.style.color = '';
                                    clientEl.style.padding = '';
                                    clientEl.style.borderRadius = '';
                                    clientEl.style.cursor = '';
                                    clientEl.title = '';
                                    clientEl.onclick = null;
                                    if (rowTel) rowTel.style.display = 'none';
                                    const rowLieu = document.getElementById('rowLieu');
                                    if (rowLieu) rowLieu.style.display = 'none';
                                    const hid = document.getElementById('editClientId'); if (hid) hid.value = '';
                                    const inp = document.getElementById('editClientSearch'); if (inp) inp.value = '';
                                }
                            }
                    
                    // Charger les informations de forfait si un client est associé
                    if (data.client_id) {
                        loadClientForfait(data.client_id);
                    } else {
                        // Réinitialiser l'affichage forfait
                        const forfaitInfo = document.getElementById('forfaitInfo');
                        if (forfaitInfo) {
                            forfaitInfo.innerHTML = '<div class="modal-event-content-text"><div class="modal-info-item"><strong>Aucun forfait trouvé</strong></div></div>';
                        }
                        const forfaitDetails = document.getElementById('forfaitDetails');
                        if (forfaitDetails) forfaitDetails.style.display = 'none';
                    }
                    
                    // Gérer le bouton de clôture
                    const btnCloture = document.getElementById('btnClotureIntervention');
                    if (btnCloture) {
                        // Afficher uniquement si le statut n'est pas 'termine' et qu'il y a un client
                        if (data.statut === 'termine' || !data.client_id) {
                            btnCloture.style.display = 'none';
                        } else {
                            btnCloture.style.removeProperty('display');
                            btnCloture.onclick = function() { clotureIntervention(data.id); };
                        }
                    }
                    
                    // Désactiver le bouton Modifier si le rendez-vous est terminé
                    const btnEdit = document.getElementById('btnEdit');
                    if (btnEdit) {
                        if (data.statut === 'termine') {
                            btnEdit.style.opacity = '0.5';
                            btnEdit.style.cursor = 'not-allowed';
                            btnEdit.disabled = true;
                            btnEdit.title = 'Modification impossible : rendez-vous terminé';
                            btnEdit.onclick = function() {
                                alert('Impossible de modifier un rendez-vous terminé.\n\nLa clôture a déjà décompté les heures de forfait.');
                                return false;
                            };
                        } else {
                            btnEdit.style.opacity = '1';
                            btnEdit.style.cursor = 'pointer';
                            btnEdit.disabled = false;
                            btnEdit.title = 'Modifier';
                            btnEdit.onclick = editEvent;
                        }
                    }
                    
                    // Désactiver le bouton Supprimer si le rendez-vous est terminé
                    const btnDelete = document.querySelector('.modal-btn-delete');
                    if (btnDelete) {
                        if (data.statut === 'termine') {
                            btnDelete.style.opacity = '0.5';
                            btnDelete.style.cursor = 'not-allowed';
                            btnDelete.disabled = true;
                            btnDelete.title = 'Suppression impossible : rendez-vous terminé';
                            btnDelete.onclick = function() {
                                alert('Impossible de supprimer un rendez-vous terminé.\n\nLa clôture a déjà décompté les heures de forfait.');
                                return false;
                            };
                        } else {
                            btnDelete.style.opacity = '1';
                            btnDelete.style.cursor = 'pointer';
                            btnDelete.disabled = false;
                            btnDelete.title = 'Supprimer';
                            btnDelete.onclick = deleteEvent;
                        }
                    }
                    
                    // Gérer le bouton Close
                    const btnClose = document.querySelector('.modal-btn-close');
                    if (btnClose) {
                        btnClose.onclick = closeEventModal;
                    }
                    
                    document.getElementById('eventModal').style.display = 'block';
                })
                .catch(error => {
                    console.error('Erreur lors du chargement des détails:', error);
                    alert('Erreur lors du chargement des détails de l\'événement');
                });
        }
        
        // Fonction pour fermer le modal
        function closeEventModal() {
            // reset edit mode
            cancelEdit(true);
            document.getElementById('eventModal').style.display = 'none';
        }
        
        // Fonction pour formater la date
        function formatDate(dateStr) {
            const date = new Date(dateStr + 'T00:00:00');
            return date.toLocaleDateString('fr-FR', { 
                weekday: 'long', 
                year: 'numeric', 
                month: 'long', 
                day: 'numeric' 
            });
        }
        
        // Fermer le modal en cliquant en dehors
        window.onclick = function(event) {
            const modal = document.getElementById('eventModal');
            if (event.target == modal) {
                closeEventModal();
            }
        }
        
        // Fermer avec la touche Échap
        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape') {
                closeEventModal();
            }
        });
        
        function editEvent() {
            // Vérifier si le rendez-vous est terminé
            if (window.currentEventData && window.currentEventData.statut === 'termine') {
                alert('Impossible de modifier un rendez-vous terminé.\n\nLa clôture a déjà décompté les heures de forfait.');
                return false;
            }
            
            // toggle edit mode
            const editFields = document.querySelectorAll('.edit-field');
            const firstField = Array.from(editFields).find(el => el.type !== 'hidden' && el.id !== 'miniClientForm');
            const isEditing = firstField && firstField.style.display !== 'none' && firstField.style.display !== '';
            
            if (!isEditing) {
                // enter edit mode - show all edit fields except hidden inputs and mini-form
                editFields.forEach(el => {
                    if (el.type === 'hidden' || el.id === 'miniClientForm') return; // skip hidden and mini-form
                    el.style.display = 'block';
                });
                // afficher le time-group spécifiquement
                const timeGroup = document.querySelector('.time-group');
                if (timeGroup) timeGroup.style.display = 'flex';
                // hide view values (sauf le bouton de clôture et le téléphone client)
                document.querySelectorAll('.modal-event-value').forEach(el => {
                    if (el.id !== 'btnClotureIntervention' && el.id !== 'eventClientTel') {
                        el.style.display = 'none';
                    }
                });
                // change edit button to save
                const btn = document.getElementById('btnEdit');
                if (btn) {
                    btn.textContent = '💾';
                    btn.title = 'Enregistrer';
                    btn.onclick = saveEdit;
                }
                // change close button to cancel
                const closeBtn = document.querySelector('.modal-btn-close');
                if (closeBtn) {
                    closeBtn.textContent = 'Annuler';
                    closeBtn.onclick = cancelEdit;
                }
            }
        }

        function cancelEdit(hideOnly = false) {
            // exit edit mode without saving
            document.querySelectorAll('.edit-field').forEach(el => el.style.display = 'none');
            // masquer le time-group spécifiquement
            const timeGroup = document.querySelector('.time-group');
            if (timeGroup) timeGroup.style.display = 'none';
            document.querySelectorAll('.modal-event-value').forEach(el => el.style.display = 'block');
            const btn = document.getElementById('btnEdit');
            if (btn) {
                btn.textContent = '✏️';
                btn.title = 'Modifier';
                btn.onclick = editEvent;
            }
            const closeBtn = document.querySelector('.modal-btn-close');
            if (closeBtn) {
                closeBtn.textContent = '✕';
                closeBtn.onclick = closeEventModal;
            }
            // ensure mini-form is hidden
            const miniForm = document.getElementById('miniClientForm');
            if (miniForm) miniForm.style.display = 'none';
            const btnShowCreate = document.getElementById('btnShowCreateClientForm');
            if (btnShowCreate) btnShowCreate.style.display = 'none';
            // hide client suggestions
            const suggestions = document.getElementById('clientSuggestions');
            if (suggestions) suggestions.style.display = 'none';
            
            if (!hideOnly) {
                // refresh details
                if (window.currentEventId) showEventDetails(window.currentEventId);
            }
        }

        // Ouvrir la modale pour créer un nouvel événement (réutilise la modale d'édition)
        function openCreateModal(info){
            // no current id => creation
            window.currentEventId = null;
            window.currentEventData = null; // Réinitialiser les données de l'événement précédent
            document.getElementById('eventTitle').textContent = 'Nouveau rendez-vous';
            const iso = info && info.dateStr ? info.dateStr : null;
            let date = '';
            let start = '';
            let end = '';
            if (iso){
                const parts = iso.split('T');
                date = parts[0] || '';
                if (parts[1]) start = parts[1].substring(0,5);
                if (start){
                    const [hh,mm] = start.split(':').map(n=>parseInt(n,10));
                    const hh2 = (hh + 1) % 24;
                    end = String(hh2).padStart(2,'0') + ':' + (mm<10? '0'+mm : String(mm));
                }
            }
            document.getElementById('editTitle').value = '';
            document.getElementById('editDate').value = date;
            document.getElementById('editStart').value = start;
            document.getElementById('editEnd').value = end;
            document.getElementById('editLieu').value = '';
            document.getElementById('editDescription').value = '';
            document.getElementById('editStatut').value = 'planifie';
            const sel = document.getElementById('editTechnicien'); if (sel) sel.value = '';
            const cid = document.getElementById('editClientId'); if (cid) cid.value = '';
            const csearch = document.getElementById('editClientSearch'); if (csearch) csearch.value = '';
            // masquer les lignes client supplémentaires en mode création
            const rowTel = document.getElementById('rowClientTel'); if (rowTel) rowTel.style.display = 'none';
            const rowLieu = document.getElementById('rowLieu'); if (rowLieu) rowLieu.style.display = 'none';
            // réinitialiser affichage client
            const clientEl = document.getElementById('eventClient'); if (clientEl) clientEl.textContent = 'Aucun';
            // masquer le bouton de clôture en mode création
            const btnCloture = document.getElementById('btnClotureIntervention');
            if (btnCloture) btnCloture.style.display = 'none';
            // réinitialiser l'affichage forfait
            const forfaitInfo = document.getElementById('forfaitInfo');
            if (forfaitInfo) {
                forfaitInfo.innerHTML = '<div class="modal-event-content-text"><div class="modal-info-item"><strong>Aucun forfait trouvé</strong></div></div>';
            }
            const forfaitDetails = document.getElementById('forfaitDetails');
            if (forfaitDetails) forfaitDetails.style.display = 'none';
            
            // Réinitialiser complètement l'état du bouton Edit avant d'ouvrir le modal
            const btnEdit = document.getElementById('btnEdit');
            if (btnEdit) {
                btnEdit.textContent = '✏️';
                btnEdit.title = 'Modifier';
                btnEdit.style.opacity = '1';
                btnEdit.style.cursor = 'pointer';
                btnEdit.disabled = false;
                btnEdit.onclick = editEvent;
            }
            
            // Réinitialiser le bouton de suppression
            const btnDelete = document.querySelector('.modal-btn-delete');
            if (btnDelete) {
                btnDelete.style.opacity = '1';
                btnDelete.style.cursor = 'pointer';
                btnDelete.disabled = false;
                btnDelete.title = 'Supprimer';
                btnDelete.onclick = deleteEvent;
            }
            
            // Réinitialiser le bouton de fermeture
            const btnClose = document.querySelector('.modal-btn-close');
            if (btnClose) {
                btnClose.textContent = '✕';
                btnClose.title = 'Fermer';
                btnClose.onclick = closeEventModal;
            }
            
            document.getElementById('eventModal').style.display = 'block';
            // enter edit mode
            try { editEvent(); } catch(e) { /* ignore */ }
        }

        function saveEdit() {
            const id = window.currentEventId; 

            const date = document.getElementById('editDate').value;
            const start = document.getElementById('editStart').value;
            const end = document.getElementById('editEnd').value;
            const lieu = document.getElementById('editLieu').value.trim();
            const description = document.getElementById('editDescription').value.trim();
            const statut = document.getElementById('editStatut').value;
            const idTechnicienVal = document.getElementById('editTechnicien') ? document.getElementById('editTechnicien').value : '';
            const idTechnicien = idTechnicienVal && idTechnicienVal !== '' ? parseInt(idTechnicienVal,10) : null;
            const idClientVal = document.getElementById('editClientId') ? document.getElementById('editClientId').value : '';
            const idClient = idClientVal && idClientVal !== '' ? parseInt(idClientVal,10) : null;
            
            // Récupérer distance et temps trajet
            const distanceKm = document.getElementById('editDistance') ? parseFloat(document.getElementById('editDistance').value) || null : null;
            const tempsTrajet = document.getElementById('editTempsTrajet') ? parseInt(document.getElementById('editTempsTrajet').value) || null : null;

            // Générer automatiquement le titre à partir du client ou utiliser un titre par défaut
            let title = 'Rendez-vous';
            if (idClient) {
                const clientSearch = document.getElementById('editClientSearch');
                if (clientSearch && clientSearch.value.trim()) {
                    title = clientSearch.value.trim();
                }
            }

            if (!date || !start || !end) return alert('Date, heure de début et heure de fin sont requis');
            if (!idTechnicien) return alert('Veuillez sélectionner un technicien');

            const startIso = date + 'T' + start + ':00';
            const endIso = date + 'T' + end + ':00';

            // if id exists -> update, else create
            if (id) {
                fetch('api/events.php?action=update', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({ 
                        id: id, 
                        title: title, 
                        start: startIso, 
                        end: endIso, 
                        lieu: lieu, 
                        description: description, 
                        statut: statut, 
                        id_technicien: idTechnicien, 
                        client_id: idClient,
                        distance_km: distanceKm,
                        temps_trajet_minutes: tempsTrajet
                    })
                })
                .then(resp => resp.json())
                .then(json => {
                    if (json.error) {
                        alert('Erreur: ' + json.error);
                    } else {
                        // update event in calendar
                        const ev = window.calendar.getEventById(String(id));
                        if (ev) {
                            ev.setProp('title', title);
                            ev.setStart(startIso);
                            ev.setEnd(endIso);
                            ev.setExtendedProp('lieu', lieu);
                            ev.setExtendedProp('description', description);
                            ev.setExtendedProp('statut', statut);
                            // update color if technician set
                            if (idTechnicien) {
                                const tech = (window._techniciensList || []).find(x=>String(x.id)===String(idTechnicien));
                                if (tech && tech.couleur) { ev.setProp('backgroundColor', tech.couleur); ev.setProp('borderColor', tech.couleur); }
                            }
                        }
                        cancelEdit(true);

                        if (statut === 'annule') {
                            executerClotureAnnulee(id, start, end);
                            return;
                        }

                        showEventDetails(id);
                    }
                })
                .catch(err => {
                    console.error('Erreur saveEdit:', err);
                    alert('Erreur réseau lors de la sauvegarde');
                });
            } else {
                // create new event
                fetch('api/events.php?action=create', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({ 
                        title: title, 
                        start: startIso, 
                        end: endIso, 
                        lieu: lieu, 
                        description: description, 
                        statut: statut, 
                        id_technicien: idTechnicien, 
                        client_id: idClient,
                        distance_km: distanceKm,
                        temps_trajet_minutes: tempsTrajet
                    })
                })
                .then(resp => resp.json())
                .then(json => {
                    if (json.error) { alert('Erreur: ' + json.error); }
                    else {
                        const newId = json.id;
                        // add into calendar
                        const ev = window.calendar.addEvent({ id: newId, title: title, start: startIso, end: endIso });
                        // set color from technician if any
                        if (idTechnicien) {
                            const tech = (window._techniciensList || []).find(x=>String(x.id)===String(idTechnicien));
                            if (tech && tech.couleur) { ev.setProp('backgroundColor', tech.couleur); ev.setProp('borderColor', tech.couleur); }
                        }
                        cancelEdit(true);
                        showEventDetails(newId);
                    }
                })
                .catch(err=>{ console.error('Erreur create event:', err); alert('Erreur réseau lors de la création'); });
            }
        }

        function deleteEvent() {
            // Vérifier si le rendez-vous est terminé
            if (window.currentEventData && window.currentEventData.statut === 'termine') {
                alert('Impossible de supprimer un rendez-vous terminé.\n\nLa clôture a déjà décompté les heures de forfait.');
                return false;
            }
            
            const id = window.currentEventId;
            if (!id) return alert('ID événement manquant');
            if (!confirm('Confirmez la suppression de cet événement ?')) return;

            fetch('api/events.php?action=delete', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({ id: id })
            })
            .then(resp => resp.json())
            .then(json => {
                if (json.error) {
                    alert('Erreur: ' + json.error);
                } else {
                    const ev = window.calendar.getEventById(String(id));
                    if (ev) ev.remove();
                    closeEventModal();
                }
            })
            .catch(err => {
                console.error('Erreur deleteEvent:', err);
                alert('Erreur réseau lors de la suppression');
            });
        }
        
        // Fonction pour clôturer une intervention manuellement
        function clotureIntervention(rdvId) {
            console.log('=== DEBUT clotureIntervention | RDV ID:', rdvId);
            if (!rdvId) return alert('ID rendez-vous manquant');
            
            // Récupérer les heures depuis les champs d'édition ou depuis les données stockées
            let heureDebut = document.getElementById('editStart')?.value || '';
            let heureFin = document.getElementById('editEnd')?.value || '';
            console.log('Heures depuis editStart/editEnd:', heureDebut, heureFin);
            
            // Si pas disponibles (mode consultation), récupérer depuis les données
            if (!heureDebut || !heureFin) {
                console.log('Heures manquantes, vérification de window.currentEventData...');
                if (window.currentEventData) {
                    heureDebut = window.currentEventData.heure_debut ? window.currentEventData.heure_debut.substring(0, 5) : '';
                    heureFin = window.currentEventData.heure_fin ? window.currentEventData.heure_fin.substring(0, 5) : '';
                    console.log('Heures depuis currentEventData:', heureDebut, heureFin);
                }
            }
            
            // Si toujours pas disponibles, récupérer depuis le serveur
            if (!heureDebut || !heureFin) {
                console.log('Heures toujours manquantes, appel API pour RDV ID:', rdvId);
                fetch('api/events.php?action=get&id=' + rdvId)
                    .then(resp => resp.json())
                    .then(data => {
                        if (data && data.heure_debut && data.heure_fin) {
                            const heureD = data.heure_debut.substring(0, 5);
                            const heureF = data.heure_fin.substring(0, 5);
                            // Stocker les données et relancer
                            window.currentEventData = data;
                            afficherModalCloture(rdvId, heureD, heureF);
                        } else {
                            alert('Impossible de récupérer les heures du rendez-vous');
                        }
                    })
                    .catch(err => {
                        console.error('Erreur récupération heures:', err);
                        alert('Erreur lors de la récupération des heures');
                    });
                return;
            }
            
            console.log('Appel afficherModalCloture avec:', { rdvId, heureDebut, heureFin });
            afficherModalCloture(rdvId, heureDebut, heureFin);
        }
        
        function afficherModalCloture(rdvId, heureDebut, heureFin) {
            console.log('=== DEBUT afficherModalCloture | RDV:', rdvId, 'Heures:', heureDebut, '-', heureFin);
            
            // Créer un modal de confirmation avec modification des heures
            const modal = document.createElement('div');
            modal.id = 'clotureConfirmModal';
            modal.style.cssText = 'position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.5);display:flex;align-items:center;justify-content:center;z-index:10001';
            
            const content = document.createElement('div');
            content.style.cssText = 'background:#fff;padding:30px;border-radius:8px;max-width:500px;width:90%';
            
            content.innerHTML = `
                <h3 style="margin-top:0;color:#e53935">Clôture de l'intervention</h3>
                <div style="margin:20px 0">
                    <label style="display:block;margin-bottom:8px;font-weight:bold">Heure de début *</label>
                    <input type="time" id="clotureHeureDebut" value="${heureDebut}" style="width:100%;padding:10px;border:1px solid #ddd;border-radius:4px">
                </div>
                <div style="margin:20px 0">
                    <label style="display:block;margin-bottom:8px;font-weight:bold">Heure de fin *</label>
                    <input type="time" id="clotureHeureFin" value="${heureFin}" style="width:100%;padding:10px;border:1px solid #ddd;border-radius:4px">
                </div>
                <div id="tempsDuree" style="margin:20px 0;padding:15px;background:#f5f5f5;border-radius:4px;font-weight:bold;text-align:center">
                    Durée : <span id="dureeCalculee">--</span>
                </div>
                <div style="display:flex;gap:10px;margin-top:30px">
                    <button onclick="confirmerClotureAvecHeures(${rdvId})" style="flex:1;padding:12px;background:#4caf50;color:#fff;border:none;border-radius:4px;cursor:pointer;font-weight:bold">Confirmer</button>
                    <button onclick="closeClotureModal()" style="flex:1;padding:12px;background:#666;color:#fff;border:none;border-radius:4px;cursor:pointer">Annuler</button>
                </div>
            `;
            
            modal.appendChild(content);
            document.body.appendChild(modal);
            
            // Calculer la durée en temps réel
            const updateDuree = () => {
                const debut = document.getElementById('clotureHeureDebut').value;
                const fin = document.getElementById('clotureHeureFin').value;
                if (debut && fin) {
                    const [hd, md] = debut.split(':').map(Number);
                    const [hf, mf] = fin.split(':').map(Number);
                    const minutesDebut = hd * 60 + md;
                    const minutesFin = hf * 60 + mf;
                    const dureeMinutes = minutesFin - minutesDebut;
                    if (dureeMinutes > 0) {
                        const heures = Math.floor(dureeMinutes / 60);
                        const minutes = dureeMinutes % 60;
                        document.getElementById('dureeCalculee').textContent = `${heures}h${minutes.toString().padStart(2, '0')}`;
                    } else {
                        document.getElementById('dureeCalculee').textContent = 'Invalide';
                    }
                }
            };
            
            document.getElementById('clotureHeureDebut').addEventListener('change', updateDuree);
            document.getElementById('clotureHeureFin').addEventListener('change', updateDuree);
            updateDuree();
        }
        
        function closeClotureModal() {
            const modal = document.getElementById('clotureConfirmModal');
            if (modal) modal.remove();
        }
        
        function confirmerClotureAvecHeures(rdvId) {
            const heureDebut = document.getElementById('clotureHeureDebut').value;
            const heureFin = document.getElementById('clotureHeureFin').value;
            const statut = document.getElementById('editStatut') ? document.getElementById('editStatut').value : '';

            if (statut === 'annule') {
                closeClotureModal();
                if (confirm('Cette intervention est marquée comme annulée.\n\nLa clôture sera enregistrée sans déduire d\'heures au forfait. Continuer ?')) {
                    executerClotureAnnulee(rdvId, heureDebut, heureFin);
                }
                return;
            }
            
            if (!heureDebut || !heureFin) {
                alert('Veuillez renseigner les deux heures');
                return;
            }
            
            // Calculer la durée
            const [hd, md] = heureDebut.split(':').map(Number);
            const [hf, mf] = heureFin.split(':').map(Number);
            const minutesDebut = hd * 60 + md;
            const minutesFin = hf * 60 + mf;
            const dureeMinutes = minutesFin - minutesDebut;
            
            if (dureeMinutes <= 0) {
                alert('L\'heure de fin doit être après l\'heure de début');
                return;
            }
            
            console.log('=== CONTROLE ARRONDI v2 | Durée: ' + dureeMinutes + ' minutes ===');
            
            // NOUVEAU : Vérifier si la durée est déjà un multiple de 30 minutes
            const estMultipleDe30 = (dureeMinutes % 30) === 0;
            console.log('Est multiple de 30 ? ' + estMultipleDe30);
            
            if (estMultipleDe30) {
                // Durée exacte (30min, 60min, 90min, etc.) : pas d'arrondi nécessaire
                console.log(`Durée exacte détectée: ${dureeMinutes} minutes (multiple de 30)`);
                closeClotureModal();
                
                // Passer directement à la vérification des heures (pas besoin de choisir l'arrondi)
                // Utiliser false car aucun arrondi n'est nécessaire pour une durée exacte
                verifierHeuresAvantSignature(rdvId, heureDebut, heureFin, false);
                return;
            }
            
            // Si la durée n'est pas un multiple de 30 minutes, afficher le choix d'arrondi
            const dureeHeures = dureeMinutes / 60;
            const dureeArrondieSup = Math.ceil(dureeHeures * 2) / 2; // Arrondi au 30min supérieur
            const dureeArrondieInf = Math.floor(dureeHeures * 2) / 2; // Arrondi au 30min inférieur
            
            // Calcul bonus/malus CLIENT selon la nouvelle règle
            // Bonus client = temps_facturé - temps_réel (positif si client achète plus que consommé = crédit)
            // Malus client = temps_facturé - temps_réel (négatif si client achète moins que consommé = dette)
            const bonusArrondiSup = Math.round((dureeArrondieSup - dureeHeures) * 60);
            const bonusArrondiInf = Math.round((dureeArrondieInf - dureeHeures) * 60);
            
            closeClotureModal();
            
            // Toujours proposer le choix entre arrondi sup et inf
            const modalArrondi = document.createElement('div');
            modalArrondi.id = 'arrondiModal';
            modalArrondi.style.cssText = 'position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.5);display:flex;align-items:center;justify-content:center;z-index:10002';
            
            const contentArrondi = document.createElement('div');
            contentArrondi.style.cssText = 'background:#fff;padding:30px;border-radius:8px;max-width:550px;width:90%';
            
            const heuresReelles = Math.floor(dureeMinutes / 60);
            const minutesReelles = dureeMinutes % 60;
            const heuresInf = Math.floor(dureeArrondieInf);
            const minutesInf = Math.round((dureeArrondieInf - heuresInf) * 60);
            const heuresSup = Math.floor(dureeArrondieSup);
            const minutesSup = Math.round((dureeArrondieSup - heuresSup) * 60);
            
            // Déterminer les textes bonus/malus CLIENT
            // Positif = Bonus client (client achète plus que consommé, a du crédit) → VERT
            // Négatif = Malus client (client achète moins que consommé, a une dette) → ROUGE
            const texteBonusSup = bonusArrondiSup > 0 
                ? `<span style="color:#4caf50">Bonus client : +${bonusArrondiSup} min</span>` 
                : `<span style="color:#f44336">Malus client : ${bonusArrondiSup} min</span>`;
            
            const texteBonusInf = bonusArrondiInf > 0 
                ? `<span style="color:#4caf50">Bonus client : +${bonusArrondiInf} min</span>` 
                : `<span style="color:#f44336">Malus client : ${bonusArrondiInf} min</span>`;
            
            contentArrondi.innerHTML = `
                <h3 style="margin-top:0;color:#e53935">Choix de l'arrondi</h3>
                <div style="margin:20px 0;padding:15px;background:#e3f2fd;border-left:4px solid #2196f3;border-radius:4px">
                    <div style="font-size:1.1em"><strong>Durée réelle :</strong> ${heuresReelles}h${minutesReelles.toString().padStart(2, '0')} (${dureeMinutes} minutes)</div>
                </div>
                <p style="font-size:1.05em;margin:20px 0;color:#666">Choisissez l'arrondi à appliquer :</p>
                <div style="display:grid;gap:15px;margin:20px 0">
                    <div style="padding:15px;border:2px solid ${bonusArrondiSup > 0 ? '#4caf50' : '#f44336'};border-radius:8px;background:${bonusArrondiSup > 0 ? '#f1f8f4' : '#fef1f0'}">
                        <div style="margin-bottom:8px;font-size:1.1em"><strong>↑ Arrondi SUPÉRIEUR</strong></div>
                        <div style="margin-bottom:5px">Facturer : <strong>${heuresSup}h${minutesSup.toString().padStart(2, '0')}</strong></div>
                        <div>Impact : ${texteBonusSup}</div>
                    </div>
                    <div style="padding:15px;border:2px solid ${bonusArrondiInf > 0 ? '#4caf50' : '#f44336'};border-radius:8px;background:${bonusArrondiInf > 0 ? '#f1f8f4' : '#fef1f0'}">
                        <div style="margin-bottom:8px;font-size:1.1em"><strong>↓ Arrondi INFÉRIEUR</strong></div>
                        <div style="margin-bottom:5px">Facturer : <strong>${heuresInf}h${minutesInf.toString().padStart(2, '0')}</strong></div>
                        <div>Impact : ${texteBonusInf}</div>
                    </div>
                </div>
                <div style="background:#fff3cd;padding:12px;border-radius:4px;margin:15px 0;font-size:0.85em;color:#856404">
                    💡 <strong>Rappel :</strong> Le bonus/malus client s'accumule et sera utilisé lors des prochaines interventions.<br>
                    • <strong style="color:#4caf50">Bonus (vert)</strong> : vous facturez plus que le temps réel → client a du crédit<br>
                    • <strong style="color:#f44336">Malus (rouge)</strong> : vous facturez moins que le temps réel → client vous doit des heures
                </div>
                <div style="display:flex;gap:15px;margin-top:30px">
                    <button onclick="repondreArrondi(${rdvId}, '${heureDebut}', '${heureFin}', true)" style="flex:1;padding:15px;background:${bonusArrondiSup > 0 ? '#4caf50' : '#f44336'};color:#fff;border:none;border-radius:4px;cursor:pointer;font-weight:bold;font-size:1.05em">↑ Arrondi SUP</button>
                    <button onclick="repondreArrondi(${rdvId}, '${heureDebut}', '${heureFin}', false)" style="flex:1;padding:15px;background:${bonusArrondiInf > 0 ? '#4caf50' : '#f44336'};color:#fff;border:none;border-radius:4px;cursor:pointer;font-weight:bold;font-size:1.05em">↓ Arrondi INF</button>
                </div>
            `;
            
            modalArrondi.appendChild(contentArrondi);
            document.body.appendChild(modalArrondi);
        }
        
        function closeArrondiModal() {
            const modal = document.getElementById('arrondiModal');
            if (modal) modal.remove();
        }
        
        function repondreArrondi(rdvId, heureDebut, heureFin, appliquerArrondi) {
            closeArrondiModal();
            
            // NOUVEAU : Vérifier les heures disponibles AVANT la signature
            verifierHeuresAvantSignature(rdvId, heureDebut, heureFin, appliquerArrondi);
        }
        
        // Variables globales pour la signature clôture
        let signaturePadCloture = null;
        let currentClotureParams = null;
        
        // NOUVELLE FONCTION : Vérifier si les heures sont suffisantes avant de demander la signature
        function verifierHeuresAvantSignature(rdvId, heureDebut, heureFin, appliquerArrondi) {
            // Appeler l'API pour vérifier les heures disponibles sans clôturer
            fetch('api/interventions.php?action=check_heures', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({ 
                    rendez_vous_id: rdvId,
                    heure_debut: heureDebut,
                    heure_fin: heureFin,
                    appliquer_arrondi: appliquerArrondi
                })
            })
            .then(resp => resp.json())
            .then(json => {
                if (json.heures_suffisantes === true) {
                    // Cas a) Heures suffisantes → Signature simple de clôture
                    console.log('Heures suffisantes, signature clôture simple');
                    procederCloture(rdvId, heureDebut, heureFin, appliquerArrondi);
                } else if (json.heures_suffisantes === false) {
                    // Cas b) Heures insuffisantes → Vente forfait + signature
                    console.log('Heures insuffisantes, ouverture vente forfait');
                    if (json.client_id) {
                        openVenteForfaitDepuisCloture(json.client_id, rdvId, json.message || 'Heures insuffisantes', heureDebut, heureFin, appliquerArrondi, json);
                    } else {
                        alert('Impossible de vérifier le forfait : client manquant');
                    }
                } else {
                    // Erreur API
                    alert('Erreur lors de la vérification des heures : ' + (json.error || 'Erreur inconnue'));
                }
            })
            .catch(err => {
                console.error('Erreur verifierHeuresAvantSignature:', err);
                alert('Erreur réseau lors de la vérification des heures');
            });
        }
        
        function procederCloture(rdvId, heureDebut, heureFin, appliquerArrondi) {
            // Stocker les paramètres pour la signature
            currentClotureParams = { rdvId, heureDebut, heureFin, appliquerArrondi };
            
            // Ouvrir le modal de signature
            openSignatureClotureModal();
        }
        
        function openSignatureClotureModal() {
            const modal = document.getElementById('signatureClotureModal');
            const messageEl = modal.querySelector('.modal-event-body p');
            
            // Mettre à jour le message avec les informations de clôture
            if (currentClotureParams) {
                const { heureDebut, heureFin, appliquerArrondi } = currentClotureParams;
                
                // Calculer la durée
                const [hd, md] = heureDebut.split(':').map(Number);
                const [hf, mf] = heureFin.split(':').map(Number);
                const minutesDebut = hd * 60 + md;
                const minutesFin = hf * 60 + mf;
                const dureeMinutes = minutesFin - minutesDebut;
                const dureeHeures = dureeMinutes / 60;
                const dureeArrondie = appliquerArrondi ? Math.ceil(dureeHeures * 2) / 2 : dureeHeures;
                
                const heuresReelles = Math.floor(dureeMinutes / 60);
                const minutesReelles = dureeMinutes % 60;
                
                messageEl.innerHTML = `
                    <strong style="color:#e53935;font-size:1.1em;">Clôture de l'intervention</strong><br><br>
                    Durée réelle : ${heuresReelles}h${minutesReelles.toString().padStart(2, '0')}<br>
                    <strong>Heures qui seront décomptées : ${formatFR(dureeArrondie)}h</strong><br><br>
                    <span style="color:#666;">Veuillez signer pour confirmer la fin de l'intervention</span>
                `;
            }
            
            modal.style.display = 'block';
            
            // Initialiser le canvas de signature
            const canvas = document.getElementById('signatureCanvasCloture');
            if (!signaturePadCloture) {
                signaturePadCloture = new SignaturePad(canvas, {
                    backgroundColor: 'rgb(255, 255, 255)',
                    penColor: 'rgb(0, 0, 0)'
                });
            }
        }
        
        function closeSignatureClotureModal() {
            const modal = document.getElementById('signatureClotureModal');
            modal.style.display = 'none';
            if (signaturePadCloture) {
                signaturePadCloture.clear();
            }
        }
        
        function clearSignatureCloture() {
            if (signaturePadCloture) {
                signaturePadCloture.clear();
            }
        }
        
        function validateSignatureCloture() {
            if (!signaturePadCloture || signaturePadCloture.isEmpty) {
                alert('Veuillez signer avant de valider');
                return;
            }
            
            const signatureData = signaturePadCloture.toDataURL();
            closeSignatureClotureModal();
            
            // Procéder à la clôture avec la signature
            executerCloture(signatureData);
        }
        
        function executerCloture(signatureData) {
            const { rdvId, heureDebut, heureFin, appliquerArrondi } = currentClotureParams;
            
            fetch('api/interventions.php?action=close_forfait', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({ 
                    rendez_vous_id: rdvId,
                    heure_debut: heureDebut,
                    heure_fin: heureFin,
                    appliquer_arrondi: appliquerArrondi,
                    signature_client: signatureData
                })
            })
            .then(resp => resp.json().then(json => ({ok: resp.ok, json})))
                .then(({ok, json}) => {
                    if (!ok || json.error) {
                        const errorMsg = json.error || 'Erreur inconnue';
                        console.log('Erreur clôture:', json); // DEBUG
                        if (json.besoin_nouveau_forfait) {
                            if (json.client_id) {
                                console.log('Ouverture modal vente forfait pour client', json.client_id); // DEBUG
                                openVenteForfaitDepuisCloture(json.client_id, rdvId, errorMsg, heureDebut, heureFin, appliquerArrondi, json);
                            } else {
                                console.error('client_id manquant dans la réponse:', json); // DEBUG
                                alert('Clôture impossible : ' + errorMsg + '\n\nIl faut vendre un nouveau forfait au client.');
                            }
                        } else {
                            alert('Erreur lors de la clôture : ' + errorMsg);
                        }
                    } else {
                        let msg = 'Intervention clôturée avec succès !\n\n';
                        msg += 'Temps réel : ' + json.temps_reel + 'h\n';
                        msg += 'Temps arrondi (facturé) : ' + json.temps_arrondi + 'h\n';
                        msg += 'Heures décomptées : ' + json.heures_decomptes + 'h\n';
                        msg += 'Heures restantes : ' + json.heures_apres + 'h\n';
                        if (json.difference_arrondi < 0) {
                            msg += '\nMalus client : ' + Math.round(json.difference_arrondi * 60) + ' minutes (paie plus que le temps réel)';
                        } else if (json.difference_arrondi > 0) {
                            msg += '\nBonus client : +' + Math.round(json.difference_arrondi * 60) + ' minutes (paie moins que le temps réel)';
                        }
                        alert(msg);
                        
                        // Fermer le modal et rafraîchir le calendrier
                        closeEventModal();
                        if (window.calendar) window.calendar.refetchEvents();
                    }
                })
                .catch(err => {
                    console.error('Erreur clotureIntervention:', err);
                    alert('Erreur réseau lors de la clôture');
                });
        }

        function executerClotureAnnulee(rdvId, heureDebut, heureFin) {
            fetch('api/interventions.php?action=close_annule', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({
                    rendez_vous_id: rdvId,
                    heure_debut: heureDebut,
                    heure_fin: heureFin
                })
            })
            .then(resp => resp.json().then(json => ({ok: resp.ok, json})))
            .then(({ok, json}) => {
                if (!ok || json.error) {
                    alert('Erreur lors de la clôture annulée : ' + (json.error || 'Erreur inconnue'));
                    return;
                }

                alert('Intervention annulée clôturée sans décompte de forfait.');
                closeEventModal();
                if (window.calendar) window.calendar.refetchEvents();
            })
            .catch(err => {
                console.error('Erreur clôture annulée:', err);
                alert('Erreur réseau lors de la clôture annulée');
            });
        }
        
        // Fonction pour ouvrir le modal de vente de forfait depuis une clôture échouée
        function openVenteForfaitDepuisCloture(clientId, rdvId, errorMsg, heureDebut, heureFin, appliquerArrondi, infoManquantes) {
            // Stocker les paramètres pour réessayer après vente
            window._clotureParams = { rdvId, heureDebut, heureFin, appliquerArrondi };
            
            // Calculer le temps manquant (ce qui manque réellement)
            let tempsNecessaire;
            if (infoManquantes && infoManquantes.heures_necessaires && infoManquantes.heures_restantes) {
                // Utiliser les valeurs du serveur
                const heuresNecessaires = parseFloat(infoManquantes.heures_necessaires);
                const heuresRestantes = parseFloat(infoManquantes.heures_restantes);
                tempsNecessaire = heuresNecessaires - heuresRestantes;
                console.log('Heures nécessaires:', heuresNecessaires, '- Heures restantes:', heuresRestantes, '= Manque:', tempsNecessaire);
            } else {
                // Fallback : calculer depuis les heures
                const debut = new Date('2000-01-01 ' + heureDebut);
                const fin = new Date('2000-01-01 ' + heureFin);
                const dureeMinutes = (fin - debut) / 1000 / 60;
                tempsNecessaire = dureeMinutes / 60;
                console.log('Calcul fallback - Temps total:', tempsNecessaire);
            }
            
            // Initialiser le panier de forfaits
            window._panierForfaits = [];
            window._tempsNecessaire = tempsNecessaire;
            
            // Fermer tous les modals ouverts
            closeArrondiModal();
            closeClotureModal();
            
            console.log('Temps nécessaire à couvrir:', tempsNecessaire, 'heures'); // DEBUG
            
            // Récupérer les types de forfait disponibles
            fetch('api/forfaits.php?action=list_types')
                .then(resp => resp.json())
                .then(types => {
                    console.log('Types forfait reçus:', types); // DEBUG
                    if (!types || types.length === 0) {
                        alert('Aucun type de forfait disponible. Veuillez d\'abord créer des forfaits.');
                        return;
                    }
                    
                    // Créer un modal simplifié pour la vente
                    const modal = document.createElement('div');
                    modal.id = 'venteForfaitModal';
                    modal.style.cssText = 'position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.5);display:flex;align-items:center;justify-content:center;z-index:10000';
                    
                    const content = document.createElement('div');
                    content.style.cssText = 'background:#fff;padding:30px;border-radius:8px;max-width:500px;width:90%;max-height:90vh;overflow-y:auto';
                    
                    content.innerHTML = `
                        <h3 style="margin-top:0;color:#e53935">Forfait insuffisant</h3>
                        <div style="background:#fff3cd;padding:15px;border-radius:4px;margin-bottom:20px;border-left:4px solid #ff9800">
                            <strong>⚠️ ${errorMsg}</strong>
                        </div>
                        
                        <div style="background:#e8f5e9;padding:15px;border-radius:4px;margin-bottom:20px;border-left:4px solid #4caf50">
                            <div style="font-weight:bold;margin-bottom:5px">Temps nécessaire : ${formatFR(tempsNecessaire)}h</div>
                            <div id="compteurHeures" style="font-size:1.2em;color:#2e7d32">
                                Heures sélectionnées : <strong>0.00h</strong>
                            </div>
                            <div id="statutCouverture" style="margin-top:8px;color:#666;font-size:0.9em">
                                Cliquez sur les forfaits ci-dessous pour accumuler des heures
                            </div>
                        </div>
                        
                        <div id="panierForfaits" style="display:none;background:#f5f5f5;padding:15px;border-radius:4px;margin-bottom:20px">
                            <div style="font-weight:bold;margin-bottom:10px">Forfaits sélectionnés :</div>
                            <div id="listePanier"></div>
                            <div style="margin-top:10px;padding-top:10px;border-top:2px solid #ddd">
                                <div style="display:flex;justify-content:space-between;align-items:center">
                                    <strong>Total :</strong>
                                    <div>
                                        <span id="totalHeures" style="font-size:1.1em;color:#2e7d32;font-weight:bold">0.00h</span>
                                        <span style="margin:0 5px">-</span>
                                        <span id="totalPrix" style="font-size:1.1em;color:#4caf50;font-weight:bold">0,00 €</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div id="sectionForfaitsDisponibles">
                            <h4 style="margin:20px 0 10px 0;color:#333">Forfaits disponibles (cliquez pour ajouter)</h4>
                            <div id="listeForfaitsDisponibles" style="max-height:250px;overflow-y:auto;margin-bottom:20px">
                                ${types.filter(t => t.actif).map(t => `
                                    <div onclick="ajouterForfaitAuPanier(${t.id}, '${t.type_forfait}', ${t.nbr_heure_forfait}, ${t.prix_forfait})" 
                                         class="forfait-card"
                                         data-forfait-id="${t.id}"
                                         style="cursor:pointer;padding:15px;margin-bottom:10px;border:2px solid #ddd;border-radius:8px;background:#f9f9f9;transition:all 0.2s">
                                        <div style="display:flex;justify-content:space-between;align-items:center">
                                            <div>
                                                <div style="font-weight:bold;font-size:1.1em;color:#333">${t.type_forfait}</div>
                                                <div style="color:#666;margin-top:4px">${t.nbr_heure_forfait}h</div>
                                            </div>
                                            <div style="font-size:1.3em;font-weight:bold;color:#4caf50">${formatFR(t.prix_forfait)} €</div>
                                        </div>
                                    </div>
                                `).join('')}
                            </div>
                        </div>
                        
                        <div style="display:flex;flex-direction:column;gap:10px;margin-top:20px">
                            <button id="btnValiderPanier" disabled style="padding:12px;background:#ccc;color:#fff;border:none;border-radius:4px;cursor:not-allowed;font-weight:bold">✓ Valider et clôturer</button>
                            <button id="btnForcerCloture" style="padding:12px;background:#ff9800;color:#fff;border:none;border-radius:4px;cursor:pointer;font-weight:bold">⚡ Clôturer quand même (solde négatif)</button>
                            <button id="btnAnnulerVente" style="padding:12px;background:#666;color:#fff;border:none;border-radius:4px;cursor:pointer">✗ Annuler</button>
                        </div>
                    `;
                    
                    modal.appendChild(content);
                    document.body.appendChild(modal);
                    
                    // Attacher les événements après création du modal
                    console.log('Attachement des événements - clientId:', clientId, 'rdvId:', rdvId);
                    const btnValider = document.getElementById('btnValiderPanier');
                    const btnForcer = document.getElementById('btnForcerCloture');
                    const btnAnnuler = document.getElementById('btnAnnulerVente');
                    
                    console.log('Boutons trouvés:', {btnValider, btnForcer, btnAnnuler});
                    
                    if (btnValider) {
                        btnValider.addEventListener('click', function() {
                            console.log('Clic sur Valider - disabled:', this.disabled);
                            if (!this.disabled) {
                                console.log('Appel validerPanierEtCloturer avec clientId:', clientId, 'rdvId:', rdvId);
                                validerPanierEtCloturer(clientId, rdvId);
                            } else {
                                console.log('Bouton désactivé, clic ignoré');
                            }
                        });
                        console.log('Event listener ajouté sur btnValider');
                    } else {
                        console.error('btnValider non trouvé !');
                    }
                    
                    if (btnForcer) {
                        btnForcer.addEventListener('click', function() {
                            console.log('Clic sur Forcer clôture');
                            forcerClotureSansForfait(rdvId);
                        });
                    }
                    
                    if (btnAnnuler) {
                        btnAnnuler.addEventListener('click', function() {
                            console.log('Clic sur Annuler');
                            closeForfaitModal();
                        });
                    }
                    
                    // Ajouter les événements hover pour les cartes
                    document.querySelectorAll('.forfait-card').forEach(card => {
                        card.addEventListener('mouseenter', function() {
                            this.style.borderColor = '#4caf50';
                            this.style.background = '#e8f5e9';
                        });
                        card.addEventListener('mouseleave', function() {
                            this.style.borderColor = '#ddd';
                            this.style.background = '#f9f9f9';
                        });
                    });
                    
                    // Fonction pour ajouter un forfait au panier
                    window.ajouterForfaitAuPanier = function(id, nom, heures, prix) {
                        // Ajouter au panier
                        window._panierForfaits.push({ id, nom, heures, prix });
                        
                        // Calculer le total
                        const totalHeures = window._panierForfaits.reduce((sum, f) => sum + parseFloat(f.heures), 0);
                        const totalPrix = window._panierForfaits.reduce((sum, f) => sum + parseFloat(f.prix), 0);
                        
                        // Mettre à jour l'affichage du compteur
                        document.getElementById('compteurHeures').innerHTML = 
                            'Heures sélectionnées : <strong style="color:#2e7d32">' + totalHeures.toFixed(2) + 'h</strong>';
                        
                        // Vérifier la couverture
                        const tempsNecessaire = window._tempsNecessaire;
                        const manque = tempsNecessaire - totalHeures;
                        const statutDiv = document.getElementById('statutCouverture');
                        
                        if (totalHeures >= tempsNecessaire) {
                            statutDiv.innerHTML = '<strong style="color:#2e7d32">✓ Temps suffisant !</strong> Vous pouvez valider.';
                            statutDiv.style.color = '#2e7d32';
                            
                            // Activer le bouton de validation
                            const btn = document.getElementById('btnValiderPanier');
                            btn.disabled = false;
                            btn.style.background = '#4caf50';
                            btn.style.cursor = 'pointer';
                            
                            // Masquer la section des forfaits disponibles
                            const sectionForfaits = document.getElementById('sectionForfaitsDisponibles');
                            if (sectionForfaits) sectionForfaits.style.display = 'none';
                        } else {
                            statutDiv.innerHTML = '⚠️ Il manque encore <strong>' + manque.toFixed(2) + 'h</strong>. Ajoutez d\'autres forfaits.';
                            statutDiv.style.color = '#ff9800';
                        }
                        
                        // Afficher le panier
                        const panierDiv = document.getElementById('panierForfaits');
                        panierDiv.style.display = 'block';
                        
                        // Mettre à jour la liste
                        const listePanier = document.getElementById('listePanier');
                        listePanier.innerHTML = window._panierForfaits.map((f, index) => `
                            <div style="display:flex;justify-content:space-between;align-items:center;padding:8px;background:#fff;border-radius:4px;margin-bottom:5px">
                                <div>
                                    <strong>${f.nom}</strong>
                                    <span style="color:#666;margin-left:10px">${f.heures}h</span>
                                </div>
                                <div style="display:flex;align-items:center;gap:10px">
                                    <span style="color:#4caf50;font-weight:bold">${formatFR(f.prix)} €</span>
                                    <button onclick="retirerForfaitDuPanier(${index})" 
                                            style="background:#f44336;color:#fff;border:none;border-radius:3px;padding:4px 8px;cursor:pointer;font-size:0.85em">
                                        ✗
                                    </button>
                                </div>
                            </div>
                        `).join('');
                        
                        // Mettre à jour les totaux
                        document.getElementById('totalHeures').textContent = formatFR(totalHeures) + 'h';
                        document.getElementById('totalPrix').textContent = formatFR(totalPrix) + ' €';
                        
                        // Animation visuelle sur la carte cliquée
                        const card = document.querySelector(`.forfait-card[data-forfait-id="${id}"]`);
                        if (card) {
                            card.style.transform = 'scale(0.95)';
                            setTimeout(() => {
                                card.style.transform = 'scale(1)';
                            }, 150);
                        }
                    };
                    
                    // Fonction pour retirer un forfait du panier
                    window.retirerForfaitDuPanier = function(index) {
                        window._panierForfaits.splice(index, 1);
                        
                        if (window._panierForfaits.length === 0) {
                            document.getElementById('panierForfaits').style.display = 'none';
                            document.getElementById('compteurHeures').innerHTML = 
                                'Heures sélectionnées : <strong>0.00h</strong>';
                            document.getElementById('statutCouverture').innerHTML = 
                                'Cliquez sur les forfaits ci-dessous pour accumuler des heures';
                            document.getElementById('statutCouverture').style.color = '#666';
                            
                            const btn = document.getElementById('btnValiderPanier');
                            btn.disabled = true;
                            btn.style.background = '#ccc';
                            btn.style.cursor = 'not-allowed';
                            
                            // Réafficher la section des forfaits disponibles
                            const sectionForfaits = document.getElementById('sectionForfaitsDisponibles');
                            if (sectionForfaits) sectionForfaits.style.display = 'block';
                        } else {
                            // Recalculer et réafficher
                            ajouterForfaitAuPanier(0, '', 0, 0); // Déclenchera la mise à jour
                            window._panierForfaits.pop(); // Retirer le dernier ajouté fictif
                            
                            // Refaire le calcul proprement
                            const totalHeures = window._panierForfaits.reduce((sum, f) => sum + parseFloat(f.heures), 0);
                            const totalPrix = window._panierForfaits.reduce((sum, f) => sum + parseFloat(f.prix), 0);
                            
                            document.getElementById('compteurHeures').innerHTML = 
                                'Heures sélectionnées : <strong style="color:#2e7d32">' + totalHeures.toFixed(2) + 'h</strong>';
                            
                            const tempsNecessaire = window._tempsNecessaire;
                            const manque = tempsNecessaire - totalHeures;
                            const statutDiv = document.getElementById('statutCouverture');
                            
                            if (totalHeures >= tempsNecessaire) {
                                statutDiv.innerHTML = '<strong style="color:#2e7d32">✓ Temps suffisant !</strong> Vous pouvez valider.';
                                statutDiv.style.color = '#2e7d32';
                                
                                const btn = document.getElementById('btnValiderPanier');
                                btn.disabled = false;
                                btn.style.background = '#4caf50';
                                btn.style.cursor = 'pointer';
                                
                                // Masquer la section des forfaits disponibles
                                const sectionForfaits = document.getElementById('sectionForfaitsDisponibles');
                                if (sectionForfaits) sectionForfaits.style.display = 'none';
                            } else {
                                statutDiv.innerHTML = '⚠️ Il manque encore <strong>' + manque.toFixed(2) + 'h</strong>. Ajoutez d\'autres forfaits.';
                                statutDiv.style.color = '#ff9800';
                                
                                const btn = document.getElementById('btnValiderPanier');
                                btn.disabled = true;
                                btn.style.background = '#ccc';
                                btn.style.cursor = 'not-allowed';
                                
                                // Réafficher la section des forfaits disponibles
                                const sectionForfaits = document.getElementById('sectionForfaitsDisponibles');
                                if (sectionForfaits) sectionForfaits.style.display = 'block';
                            }
                            
                            const listePanier = document.getElementById('listePanier');
                            listePanier.innerHTML = window._panierForfaits.map((f, idx) => `
                                <div style="display:flex;justify-content:space-between;align-items:center;padding:8px;background:#fff;border-radius:4px;margin-bottom:5px">
                                    <div>
                                        <strong>${f.nom}</strong>
                                        <span style="color:#666;margin-left:10px">${f.heures}h</span>
                                    </div>
                                    <div style="display:flex;align-items:center;gap:10px">
                                        <span style="color:#4caf50;font-weight:bold">${f.prix} €</span>
                                        <button onclick="retirerForfaitDuPanier(${idx})" 
                                                style="background:#f44336;color:#fff;border:none;border-radius:3px;padding:4px 8px;cursor:pointer;font-size:0.85em">
                                            ✗
                                        </button>
                                    </div>
                                </div>
                            `).join('');
                            
                            document.getElementById('totalHeures').textContent = totalHeures.toFixed(2) + 'h';
                            document.getElementById('totalPrix').textContent = totalPrix.toFixed(2) + ' €';
                        }
                    };
                })
                .catch(err => {
                    console.error('Erreur chargement types forfait:', err);
                    alert('Impossible de charger les types de forfait');
                });
        }
        
        // Variables globales pour la signature vente
        let signaturePadVente = null;
        let currentVenteParams = null;
        
        // Fonction pour valider le panier et vendre tous les forfaits
        function validerPanierEtCloturer(clientId, rdvId) {
            console.log('validerPanierEtCloturer appelée - clientId:', clientId, 'rdvId:', rdvId, 'panier:', window._panierForfaits);
            if (!window._panierForfaits || window._panierForfaits.length === 0) {
                alert('Aucun forfait sélectionné');
                return;
            }
            
            const totalHeures = window._panierForfaits.reduce((sum, f) => sum + parseFloat(f.heures), 0);
            const totalPrix = window._panierForfaits.reduce((sum, f) => sum + parseFloat(f.prix), 0);
            
            // Stocker les paramètres pour la signature
            currentVenteParams = { clientId, rdvId, totalHeures, totalPrix };
            
            // Ouvrir directement le modal de signature (la confirmation sera faite après)
            openSignatureVenteModal();
        }
        
        function openSignatureVenteModal() {
            console.log('openSignatureVenteModal appelée');
            
            // IMPORTANT: Fermer le modal de vente forfait avant d'ouvrir la signature
            const forfaitModal = document.getElementById('forfaitModal');
            if (forfaitModal) {
                console.log('Modal forfait display avant fermeture:', forfaitModal.style.display);
                forfaitModal.style.display = 'none';
                forfaitModal.style.zIndex = '9999'; // Forcer en dessous
                console.log('Modal forfait fermé et z-index abaissé');
            }
            
            const modal = document.getElementById('signatureVenteModal');
            console.log('Modal signature avant:', modal.style.display);
            modal.style.display = 'block';
            modal.style.zIndex = '10001'; // Forcer au-dessus
            console.log('Modal signature après:', modal.style.display, 'z-index:', modal.style.zIndex);
            
            // Initialiser le canvas de signature
            const canvas = document.getElementById('signatureCanvasVente');
            console.log('Canvas vente trouvé:', canvas);
            if (!signaturePadVente) {
                signaturePadVente = new SignaturePad(canvas, {
                    backgroundColor: 'rgb(255, 255, 255)',
                    penColor: 'rgb(0, 0, 0)'
                });
                console.log('SignaturePad vente initialisé:', signaturePadVente);
            } else {
                // Réinitialiser le pad existant
                signaturePadVente.clear();
                console.log('SignaturePad vente réinitialisé');
            }
        }
        
        function closeSignatureVenteModal() {
            const modal = document.getElementById('signatureVenteModal');
            modal.style.display = 'none';
            if (signaturePadVente) {
                signaturePadVente.clear();
            }
        }
        
        function clearSignatureVente() {
            if (signaturePadVente) {
                signaturePadVente.clear();
            }
        }
        
        function validateSignatureVente() {
            console.log('validateSignatureVente appelée - signaturePadVente:', signaturePadVente);
            if (!signaturePadVente || signaturePadVente.isEmpty) {
                console.log('Signature vide ou pad non initialisé');
                alert('Veuillez signer avant de valider');
                return;
            }
            
            const signatureData = signaturePadVente.toDataURL();
            closeSignatureVenteModal();
            
            // Demander confirmation APRÈS la signature
            const { totalHeures, totalPrix } = currentVenteParams;
            if (!confirm(`Vendre ${window._panierForfaits.length} forfait(s) ?\n\nTotal : ${formatFR(totalHeures)}h pour ${formatFR(totalPrix)} €`)) {
                return;
            }
            
            // Procéder à la vente avec la signature
            executerVente(signatureData);
        }
        
        function executerVente(signatureData) {
            const { clientId, rdvId } = currentVenteParams;
            
            // Vendre chaque forfait du panier avec la signature
            let promesses = window._panierForfaits.map(forfait => {
                return fetch('api/forfaits.php?action=vendre', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        client_id: clientId,
                        type_forfait_id: forfait.id,
                        signature_client: signatureData
                    })
                });
            });
            
            Promise.all(promesses)
                .then(responses => Promise.all(responses.map(r => r.json())))
                .then(results => {
                    console.log('Résultats vente forfaits:', results);
                    
                    // Vérifier si toutes les ventes ont réussi
                    const erreurs = results.filter(r => r.status !== 'created');
                    if (erreurs.length > 0) {
                        console.error('Erreurs détaillées:', erreurs);
                        alert('Erreur lors de la vente de certains forfaits:\n' + erreurs.map(e => e.error).join('\n'));
                        return;
                    }
                    
                    alert(`${window._panierForfaits.length} forfait(s) vendu(s) avec succès !`);
                    closeForfaitModal();
                    
                    // Réessayer la clôture
                    if (window._clotureParams) {
                        const { rdvId, heureDebut, heureFin, appliquerArrondi } = window._clotureParams;
                        setTimeout(() => procederCloture(rdvId, heureDebut, heureFin, appliquerArrondi), 500);
                    }
                })
                .catch(err => {
                    console.error('Erreur vente forfaits:', err);
                    alert('Erreur réseau lors de la vente des forfaits');
                });
        }
        
        function closeForfaitModal() {
            const modal = document.getElementById('venteForfaitModal');
            if (modal) modal.remove();
        }
        
        function forcerClotureSansForfait(rdvId) {
            if (!confirm('Confirmer la clôture avec un solde négatif ?\n\nLe client devra des heures.')) {
                return;
            }
            
            closeForfaitModal();
            
            // Réessayer la clôture avec force_cloture = true
            if (window._clotureParams) {
                const { heureDebut, heureFin, appliquerArrondi } = window._clotureParams;
                
                fetch('api/interventions.php?action=close_forfait', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({ 
                        rendez_vous_id: rdvId,
                        heure_debut: heureDebut,
                        heure_fin: heureFin,
                        appliquer_arrondi: appliquerArrondi,
                        force_cloture: true
                    })
                })
                .then(resp => resp.json().then(json => ({ok: resp.ok, json})))
                .then(({ok, json}) => {
                    if (!ok || json.error) {
                        alert('Erreur lors de la clôture forcée : ' + (json.error || 'Erreur inconnue'));
                    } else {
                        let msg = 'Intervention clôturée avec solde négatif !\n\n';
                        msg += 'Temps facturé : ' + json.heures_decomptes + 'h\n';
                        msg += 'Heures restantes : ' + json.heures_apres + 'h (négatif)\n';
                        alert(msg);
                        location.reload();
                    }
                })
                .catch(err => {
                    console.error('Erreur clôture forcée:', err);
                    alert('Erreur réseau');
                });
                
                delete window._clotureParams;
            }
        }
        
        function facturerHorsForfait(rdvId, clientId) {
            closeForfaitModal();
            
            // Demander le tarif horaire
            const tarifDefaut = 50; // Tarif par défaut
            const tarif = prompt(`Tarif horaire (€) :\n\nRègle de facturation :\n• Première heure : toujours 1h\n• Au-delà : par tranches de 30min`, tarifDefaut);
            
            if (!tarif || isNaN(tarif) || parseFloat(tarif) <= 0) {
                alert('Tarif invalide');
                return;
            }
            
            const tarifHoraire = parseFloat(tarif);
            
            // Récupérer les paramètres de clôture
            if (!window._clotureParams) {
                alert('Paramètres de clôture manquants');
                return;
            }
            
            const { heureDebut, heureFin } = window._clotureParams;
            
            if (confirm(`Confirmer la facturation hors forfait ?\n\nTarif : ${tarifHoraire}€/h\nRègle : 1h mini, puis tranches de 30min`)) {
                fetch('api/interventions.php?action=close_hors_forfait', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({
                        rendez_vous_id: rdvId,
                        heure_debut: heureDebut,
                        heure_fin: heureFin,
                        tarif_horaire: tarifHoraire
                    })
                })
                .then(resp => resp.json().then(json => ({ok: resp.ok, json})))
                .then(({ok, json}) => {
                    if (!ok || json.error) {
                        alert('Erreur : ' + (json.error || 'Erreur inconnue'));
                    } else {
                        let msg = 'Intervention facturée hors forfait !\n\n';
                        msg += 'Durée réelle : ' + json.temps_reel + 'h\n';
                        msg += 'Quantité facturée : ' + json.quantite_facturee + 'h\n';
                        msg += 'Tarif horaire : ' + json.tarif_horaire + '€\n';
                        msg += 'Montant total : ' + json.montant_total + '€\n';
                        alert(msg);
                        delete window._clotureParams;
                        location.reload();
                    }
                })
                .catch(err => {
                    console.error('Erreur facturation hors forfait:', err);
                    alert('Erreur réseau');
                });
            }
        }
        
        function vendreEtReessayerCloture(clientId, rdvId) {
            const typeForfaitId = parseInt(document.getElementById('typeForfaitVente').value);
            const prix = parseFloat(document.getElementById('prixVente').value);
            
            if (!typeForfaitId || !prix) {
                alert('Veuillez remplir tous les champs');
                return;
            }
            
            // Vendre le forfait
            fetch('api/forfaits.php?action=vendre', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({
                    client_id: clientId,
                    type_forfait_id: typeForfaitId,
                    tarif: prix
                })
            })
            .then(resp => resp.json())
            .then(json => {
                if (json.error) {
                    alert('Erreur lors de la vente : ' + json.error);
                    return;
                }
                
                // Fermer le modal
                closeForfaitModal();
                
                // Réessayer la clôture automatiquement avec les paramètres stockés
                if (window._clotureParams) {
                    const { rdvId, heureDebut, heureFin, appliquerArrondi } = window._clotureParams;
                    console.log('Réessai clôture avec:', window._clotureParams);
                    procederCloture(rdvId, heureDebut, heureFin, appliquerArrondi);
                    delete window._clotureParams; // Nettoyer
                } else {
                    alert('Forfait vendu avec succès ! Veuillez relancer la clôture.');
                    location.reload();
                }
            })
            .catch(err => {
                console.error('Erreur vente forfait:', err);
                alert('Erreur réseau lors de la vente');
            });
        }
        
        // ==================== FONCTIONS SIDEBAR ====================
        
        // Navigation mini-calendrier
        let miniCalendarDate = new Date();
        
        function miniCalendarPrev() {
            miniCalendarDate.setMonth(miniCalendarDate.getMonth() - 1);
            renderMiniCalendar();
        }
        
        function miniCalendarNext() {
            miniCalendarDate.setMonth(miniCalendarDate.getMonth() + 1);
            renderMiniCalendar();
        }
        
        function renderMiniCalendar() {
            const year = miniCalendarDate.getFullYear();
            const month = miniCalendarDate.getMonth();
            
            // Mettre à jour le titre
            const monthNames = ['janvier', 'février', 'mars', 'avril', 'mai', 'juin', 
                                'juillet', 'août', 'septembre', 'octobre', 'novembre', 'décembre'];
            document.getElementById('miniCalendarMonth').textContent = monthNames[month] + ' ' + year;
            
            // Générer la grille
            const grid = document.getElementById('miniCalendarGrid');
            grid.innerHTML = '';
            
            // Jours de la semaine
            const dayHeaders = ['lun', 'mar', 'mer', 'jeu', 'ven', 'sam', 'dim'];
            dayHeaders.forEach(day => {
                const header = document.createElement('div');
                header.className = 'mini-calendar-day-header';
                header.textContent = day;
                grid.appendChild(header);
            });
            
            // Premier jour du mois
            const firstDay = new Date(year, month, 1);
            let startDay = firstDay.getDay();
            startDay = startDay === 0 ? 6 : startDay - 1; // Lundi = 0
            
            // Dernier jour du mois
            const lastDay = new Date(year, month + 1, 0).getDate();
            
            // Jours du mois précédent
            const prevMonthLastDay = new Date(year, month, 0).getDate();
            for (let i = startDay - 1; i >= 0; i--) {
                const day = document.createElement('div');
                day.className = 'mini-calendar-day other-month';
                day.textContent = prevMonthLastDay - i;
                grid.appendChild(day);
            }
            
            // Jours du mois actuel
            const today = new Date();
            for (let i = 1; i <= lastDay; i++) {
                const day = document.createElement('div');
                day.className = 'mini-calendar-day';
                if (year === today.getFullYear() && month === today.getMonth() && i === today.getDate()) {
                    day.classList.add('today');
                }
                day.textContent = i;
                day.onclick = function() {
                    // Créer la date à 12h pour éviter les problèmes de timezone
                    const date = new Date(year, month, i, 12, 0, 0);
                    
                    if (window.calendar) {
                        // Basculer en vue hebdomadaire
                        window.calendar.changeView('timeGridWeek');
                        // Petit délai pour laisser la vue se charger, puis aller à la date
                        setTimeout(() => {
                            // Si c'est un lundi, ajouter 1 jour pour que FullCalendar se positionne correctement
                            const dayOfWeek = date.getDay();
                            const targetDate = (dayOfWeek === 1) ? new Date(date.getTime() + 24*60*60*1000) : date;
                            window.calendar.gotoDate(targetDate);
                        }, 50);
                    }
                };
                grid.appendChild(day);
            }
            
            // Jours du mois suivant
            const remaining = 42 - (startDay + lastDay); // 6 semaines max
            for (let i = 1; i <= remaining; i++) {
                const day = document.createElement('div');
                day.className = 'mini-calendar-day other-month';
                day.textContent = i;
                grid.appendChild(day);
            }
        }
        
        // Charger la liste des clients dans la sidebar
        function loadSidebarClients() {
            fetch('api/clients.php?action=list&limit=100')
                .then(r => r.json())
                .then(data => {
                    const list = document.getElementById('sidebarClientList');
                    list.innerHTML = '';
                    
                    (data.clients || []).forEach(client => {
                        const li = document.createElement('li');
                        li.className = 'technicien-item';
                        
                        // Déterminer la couleur en fonction du mode de paiement
                        let color = '#666';
                        if (client.avance_imme == 1) {
                            color = '#4caf50'; // Vert pour avance immédiate
                        } else if (client.mode_paiement === 'avance_immediate') {
                            color = '#ffa726'; // Orange pour avance immédiate (ancien)
                        }
                        
                        li.innerHTML = `
                            <span class="technicien-color" style="background:${color}"></span>
                            <span>${client.prenom} ${client.nom}</span>
                        `;
                        li.onclick = function() {
                            // Rediriger vers la page client ou filtrer les événements
                            window.location.href = 'clients.php?highlight=' + client.id;
                        };
                        list.appendChild(li);
                    });
                })
                .catch(err => console.error('Erreur chargement clients sidebar:', err));
        }
        
        // Filtrer les clients dans la sidebar
        function filterSidebarClients(searchText) {
            const items = document.querySelectorAll('#sidebarClientList .technicien-item');
            const search = searchText.toLowerCase();
            
            items.forEach(item => {
                const text = item.textContent.toLowerCase();
                if (text.includes(search)) {
                    item.style.display = 'flex';
                } else {
                    item.style.display = 'none';
                }
            });
        }
        
        // Navigation toolbar
        function goToToday() {
            if (window.calendar) {
                window.calendar.today();
                updateCalendarTitle();
            }
        }
        
        function calendarPrev() {
            if (window.calendar) {
                window.calendar.prev();
                updateCalendarTitle();
            }
        }
        
        function calendarNext() {
            if (window.calendar) {
                window.calendar.next();
                updateCalendarTitle();
            }
        }
        
        function changeView(viewName) {
            if (window.calendar) {
                window.calendar.changeView(viewName);
                updateCalendarTitle();
                
                // Mettre à jour les boutons actifs
                document.querySelectorAll('.view-btn').forEach(btn => btn.classList.remove('active'));
                event.target.classList.add('active');
            }
        }
        
        function updateCalendarTitle() {
            if (!window.calendar) return;
            
            const view = window.calendar.view;
            const start = view.activeStart;
            const end = view.activeEnd;
            
            const options = { day: 'numeric', month: 'short', year: 'numeric' };
            const startStr = start.toLocaleDateString('fr-FR', options);
            const endStr = new Date(end.getTime() - 86400000).toLocaleDateString('fr-FR', options);
            
            let title = '';
            if (view.type === 'timeGridWeek') {
                const startDay = start.getDate();
                const endDay = new Date(end.getTime() - 86400000).getDate();
                const month = start.toLocaleDateString('fr-FR', { month: 'short' });
                const year = start.getFullYear();
                title = `${startDay} — ${endDay} ${month}. ${year}`;
            } else if (view.type === 'timeGridDay') {
                title = startStr;
            } else if (view.type === 'dayGridMonth') {
                title = start.toLocaleDateString('fr-FR', { month: 'long', year: 'numeric' });
            } else {
                title = startStr;
            }
            
            document.getElementById('calendarTitle').textContent = title;
        }
        
        // Initialisation au chargement
        document.addEventListener('DOMContentLoaded', function() {
            renderMiniCalendar();
            loadSidebarClients();
            
            // Mettre à jour le titre après l'init du calendrier
            setTimeout(() => {
                updateCalendarTitle();
            }, 500);
        });
    </script>
</body>
</html>
