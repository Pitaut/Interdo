<?php
/**
 * Fichier de configuration de l'application Agenda
 * 
 * Ce fichier contient toutes les configurations de base de données
 * et les paramètres généraux de l'application
 */

// Configuration de la base de données
define('DB_HOST', 'localhost');
define('DB_NAME', 'sc4ptwq1547_InterDo');
define('DB_USER', 'sc4ptwq1547_AdminisBase');
define('DB_PASS', 'TJ[Bq5$m16F~');
define('DB_CHARSET', 'utf8mb4');

// Configuration du fuseau horaire
define('TIMEZONE', 'Europe/Paris');
date_default_timezone_set(TIMEZONE);

// Configuration API OpenRouteService
define('OPENROUTE_API_KEY', 'eyJvcmciOiI1YjNjZTM1OTc4NTExMTAwMDFjZjYyNDgiLCJpZCI6ImQwZTQxZjE0MjQzOTRlYTViMTQxZjFmOWNiYTc3MWEzIiwiaCI6Im11cm11cjY0In0=');

// Configuration de l'affichage des erreurs (à désactiver en production)
define('DEBUG_MODE', true);

if (DEBUG_MODE) {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
} else {
    error_reporting(0);
    ini_set('display_errors', 0);
}

// Fonction de connexion à la base de données
function getDBConnection() {
    try {
        $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];
        
        $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        
        // Exécuter la migration des signatures si nécessaire
        if (file_exists(__DIR__ . '/migrations/signatures_migration.php')) {
            require_once __DIR__ . '/migrations/signatures_migration.php';
        }
        
        return $pdo;
        
    } catch(PDOException $e) {
        if (DEBUG_MODE) {
            die("Erreur de connexion à la base de données : " . $e->getMessage());
        } else {
            die("Erreur de connexion à la base de données. Veuillez contacter l'administrateur.");
        }
    }
}

// Configuration générale de l'application
define('APP_NAME', 'Agenda 24h');
define('APP_VERSION', '1.0.0');

// Paramètres de l'agenda
define('HOUR_START', 0);    // Heure de début (0h = minuit pour accès complet avec ascenseur)
define('HOUR_END', 24);     // Heure de fin (24h = minuit suivant)
define('SLOT_MIN_TIME', '00:00:00');  // Heure minimum affichée dans l'agenda (00:00 = minuit)
define('SLOT_MAX_TIME', '24:00:00');  // Heure maximum affichée dans l'agenda (24:00 = minuit suivant)
define('SCROLL_TIME', '09:00:00');    // Heure de défilement automatique au chargement (centre sur la journée de travail)
define('SLOT_DURATION', '00:30:00');  // Durée d'un créneau horaire (30 minutes)
define('SLOT_LABEL_INTERVAL', '01:00'); // Intervalle d'affichage des labels d'heures (1 heure)
define('SLOT_HEIGHT', 60);  // Hauteur en pixels d'un créneau d'une heure

// Paramètres d'affichage FullCalendar
define('FC_INITIAL_VIEW', 'timeGridWeek'); // Vue par défaut : timeGridWeek, timeGridDay, dayGridMonth, listWeek
define('FC_FIRST_DAY', 1);  // Premier jour de la semaine (0=Dimanche, 1=Lundi)
define('FC_WEEK_NUMBERS', true); // Afficher les numéros de semaine
define('FC_NOW_INDICATOR', true); // Afficher l'indicateur de l'heure actuelle
define('FC_ALL_DAY_SLOT', false); // Afficher la ligne "toute la journée"
define('FC_HEIGHT', '100%'); // Hauteur du calendrier (100%, auto, ou valeur en px)

// Paramètres d'édition FullCalendar
define('FC_EDITABLE', true); // Autoriser le drag & drop et resize des événements
define('FC_EVENT_START_EDITABLE', true); // Autoriser le changement de l'heure de début
define('FC_EVENT_DURATION_EDITABLE', true); // Autoriser le changement de durée (resize)
define('FC_SELECTABLE', true); // Autoriser la sélection de plages horaires

// Statuts possibles pour les rendez-vous
define('STATUTS_RDV', [
    'planifie' => 'Planifié',
    'en_cours' => 'En cours',
    'termine' => 'Terminé',
    'annule' => 'Annulé'
]);

// Couleurs par statut (optionnel pour future utilisation)
define('COULEURS_STATUT', [
    'planifie' => '#667eea',
    'en_cours' => '#4caf50',
    'termine' => '#9e9e9e',
    'annule' => '#f44336'
]);

// ========================================
// Configuration des clients à risque
// ========================================

// Activer ou désactiver le système d'alerte clients à risque
define('CLIENT_RISQUE_ENABLED', true);

// Seuils pour identifier un client à risque
define('CLIENT_RISQUE_CONFIG', [
    
    // FACTURATION IMPAYÉE
    // Nombre d'heures hors forfait impayées à partir duquel le client devient à risque
    'hors_forfait_impaye_heures_seuil' => 5.0,
    
    // Montant total hors forfait impayé (en €) à partir duquel le client devient à risque
    'hors_forfait_impaye_montant_seuil' => 250.00,
    
    // Délai en jours après lequel une facturation impayée devient critique
    'hors_forfait_delai_paiement_jours' => 30,
    
    // FORFAITS IMPAYÉS
    // Nombre de forfaits vendus mais non payés à partir duquel le client devient à risque
    'forfaits_impayes_nombre_seuil' => 1,
    
    // Montant total de forfaits impayés (en €) à partir duquel le client devient à risque
    'forfaits_impayes_montant_seuil' => 500.00,
    
    // Délai en jours après la date de signature pour considérer un forfait impayé comme critique
    'forfaits_delai_paiement_jours' => 45,
    
    // ÉPUISEMENT DE FORFAIT
    // Pourcentage d'heures restantes en dessous duquel alerter (ex: 10 = alerte si <10% restant)
    'forfait_heures_restantes_pourcent_seuil' => 10,
    
    // Nombre d'heures restantes minimum avant alerte (quelque soit le pourcentage)
    'forfait_heures_restantes_absolu_seuil' => 2.0,
    
    // COMMUNICATION
    // Nombre de jours sans rappel après lequel le client devient à risque
    'delai_dernier_rappel_jours' => 90,
    
    // BONUS ET AVANCES
    // Heures de bonus accumulées au-delà desquelles le client devient à risque
    'heure_bonus_seuil_max' => 15.0,
    
    // MODES DE PAIEMENT À RISQUE
    // Modes de paiement considérés comme moins sûrs (liste)
    'modes_paiement_risque' => ['cheque', 'especes'],
    
    // CRITÈRES DE COMBINAISON
    // Nombre de critères devant être remplis pour considérer le client vraiment à risque
    // Si = 1 : un seul critère suffit (très strict)
    // Si = 2 : au moins 2 critères (équilibré)
    // Si = 3 : au moins 3 critères (peu strict)
    'nombre_criteres_minimum' => 1
]);

// ========================================
// Fonction d'évaluation des clients à risque
// ========================================

/**
 * Évalue si un client est à risque selon les règles configurées
 * 
 * @param int $clientId ID du client à évaluer
 * @param PDO|null $pdo Connexion PDO (optionnelle, sera créée si non fournie)
 * @return array ['at_risk' => bool, 'criteres' => array, 'details' => array]
 */
function isClientAtRisk($clientId, $pdo = null) {
    if (!CLIENT_RISQUE_ENABLED) {
        return ['at_risk' => false, 'criteres' => [], 'details' => []];
    }
    
    if ($pdo === null) {
        $pdo = getDBConnection();
    }
    
    $config = CLIENT_RISQUE_CONFIG;
    $criteres = [];
    $details = [];
    
    // 1. VÉRIFIER FACTURATION HORS FORFAIT IMPAYÉE
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(*) as nb_impayes,
            SUM(duree_reelle) as heures_impayees,
            SUM(montant_total) as montant_impaye,
            MIN(date_intervention) as plus_ancienne
        FROM facturation_hors_forfait
        WHERE client_id = ? AND paye = 0
    ");
    $stmt->execute([$clientId]);
    $horsForfait = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($horsForfait['nb_impayes'] > 0) {
        $heuresImpayees = (float)$horsForfait['heures_impayees'];
        $montantImpaye = (float)$horsForfait['montant_impaye'];
        
        if ($heuresImpayees >= $config['hors_forfait_impaye_heures_seuil']) {
            $criteres[] = 'hors_forfait_heures';
            $details['hors_forfait_heures'] = sprintf("%.2f heures impayées (seuil: %.2f)", 
                $heuresImpayees, $config['hors_forfait_impaye_heures_seuil']);
        }
        
        if ($montantImpaye >= $config['hors_forfait_impaye_montant_seuil']) {
            $criteres[] = 'hors_forfait_montant';
            $details['hors_forfait_montant'] = sprintf("%.2f € impayés (seuil: %.2f €)", 
                $montantImpaye, $config['hors_forfait_impaye_montant_seuil']);
        }
        
        // Vérifier ancienneté de l'impayé
        if ($horsForfait['plus_ancienne']) {
            $joursImpaye = (new DateTime())->diff(new DateTime($horsForfait['plus_ancienne']))->days;
            if ($joursImpaye >= $config['hors_forfait_delai_paiement_jours']) {
                $criteres[] = 'hors_forfait_ancien';
                $details['hors_forfait_ancien'] = sprintf("Impayé depuis %d jours (seuil: %d)", 
                    $joursImpaye, $config['hors_forfait_delai_paiement_jours']);
            }
        }
    }
    
    // 2. VÉRIFIER FORFAITS IMPAYÉS
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(*) as nb_impayes,
            SUM(tarif) as montant_impaye,
            MIN(COALESCE(date_vente, date_signature, DATE(created_at))) as plus_ancienne
        FROM forfaits_vendus
        WHERE client_id = ? AND paye = 0
    ");
    $stmt->execute([$clientId]);
    $forfaits = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($forfaits['nb_impayes'] > 0) {
        $nbImpayes = (int)$forfaits['nb_impayes'];
        $montantImpaye = (float)$forfaits['montant_impaye'];
        
        if ($nbImpayes >= $config['forfaits_impayes_nombre_seuil']) {
            $criteres[] = 'forfaits_impayes_nombre';
            $details['forfaits_impayes_nombre'] = sprintf("%d forfait(s) impayé(s) (seuil: %d)", 
                $nbImpayes, $config['forfaits_impayes_nombre_seuil']);
        }
        
        if ($montantImpaye >= $config['forfaits_impayes_montant_seuil']) {
            $criteres[] = 'forfaits_impayes_montant';
            $details['forfaits_impayes_montant'] = sprintf("%.2f € de forfaits impayés (seuil: %.2f €)", 
                $montantImpaye, $config['forfaits_impayes_montant_seuil']);
        }
        
        // Vérifier ancienneté
        if ($forfaits['plus_ancienne']) {
            $joursImpaye = (new DateTime())->diff(new DateTime($forfaits['plus_ancienne']))->days;
            if ($joursImpaye >= $config['forfaits_delai_paiement_jours']) {
                $criteres[] = 'forfaits_impaye_ancien';
                $details['forfaits_impaye_ancien'] = sprintf("Forfait impayé depuis %d jours (seuil: %d)", 
                    $joursImpaye, $config['forfaits_delai_paiement_jours']);
            }
        }
    }
    
    // 3. VÉRIFIER ÉPUISEMENT DE FORFAIT
    $stmt = $pdo->prepare("
        SELECT heures_total, heures_restantes
        FROM forfaits_vendus
        WHERE client_id = ? AND paye = 1 AND heures_restantes > 0
        ORDER BY date_fin ASC
        LIMIT 1
    ");
    $stmt->execute([$clientId]);
    $forfaitActif = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($forfaitActif) {
        $heuresTotal = (float)$forfaitActif['heures_total'];
        $heuresRestantes = (float)$forfaitActif['heures_restantes'];
        $pourcentRestant = ($heuresTotal > 0) ? ($heuresRestantes / $heuresTotal * 100) : 0;
        
        if ($pourcentRestant < $config['forfait_heures_restantes_pourcent_seuil']) {
            $criteres[] = 'forfait_epuisement_pourcent';
            $details['forfait_epuisement_pourcent'] = sprintf("%.1f%% d'heures restantes (seuil: %d%%)", 
                $pourcentRestant, $config['forfait_heures_restantes_pourcent_seuil']);
        }
        
        if ($heuresRestantes < $config['forfait_heures_restantes_absolu_seuil']) {
            $criteres[] = 'forfait_epuisement_absolu';
            $details['forfait_epuisement_absolu'] = sprintf("%.2f heures restantes (seuil: %.2f)", 
                $heuresRestantes, $config['forfait_heures_restantes_absolu_seuil']);
        }
    }
    
    // 4. VÉRIFIER COMMUNICATION
    $stmt = $pdo->prepare("
        SELECT date_dernier_rappel, mode_paiement, heure_bonus
        FROM clients
        WHERE id = ?
    ");
    $stmt->execute([$clientId]);
    $client = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($client) {
        // Dernier rappel
        if ($client['date_dernier_rappel']) {
            $joursDepuisRappel = (new DateTime())->diff(new DateTime($client['date_dernier_rappel']))->days;
            if ($joursDepuisRappel >= $config['delai_dernier_rappel_jours']) {
                $criteres[] = 'communication_absente';
                $details['communication_absente'] = sprintf("Pas de rappel depuis %d jours (seuil: %d)", 
                    $joursDepuisRappel, $config['delai_dernier_rappel_jours']);
            }
        }
        
        // Mode de paiement à risque
        if (in_array($client['mode_paiement'], $config['modes_paiement_risque'])) {
            $criteres[] = 'mode_paiement_risque';
            $details['mode_paiement_risque'] = sprintf("Mode de paiement à risque: %s", $client['mode_paiement']);
        }
        
        // Heures bonus excessives
        $heureBonus = (float)$client['heure_bonus'];
        if ($heureBonus >= $config['heure_bonus_seuil_max']) {
            $criteres[] = 'bonus_excessif';
            $details['bonus_excessif'] = sprintf("%.2f heures de bonus (seuil: %.2f)", 
                $heureBonus, $config['heure_bonus_seuil_max']);
        }
    }
    
    // DÉCISION FINALE
    $nbCriteres = count($criteres);
    $atRisk = $nbCriteres >= $config['nombre_criteres_minimum'];
    
    return [
        'at_risk' => $atRisk,
        'nb_criteres' => $nbCriteres,
        'criteres' => $criteres,
        'details' => $details
    ];
}
?>
