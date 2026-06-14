<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'config.php';

echo "Test de l'API forfaits...\n\n";

try {
    $pdo = getDBConnection();
    echo "✓ Connexion BD réussie\n";
    
    // Simuler une requête create_type
    $_SERVER['REQUEST_METHOD'] = 'POST';
    $_GET['action'] = 'create_type';
    
    $data = [
        'type_forfait' => 'Test Direct 5h',
        'nbr_heure_forfait' => 5,
        'prix_forfait' => 250
    ];
    
    // Simuler php://input
    $GLOBALS['mockInput'] = json_encode($data);
    
    ob_start();
    include 'api/forfaits.php';
    $output = ob_get_clean();
    
    echo "Réponse API:\n";
    echo $output;
    
} catch (Exception $e) {
    echo "❌ ERREUR: " . $e->getMessage() . "\n";
    echo "Trace:\n" . $e->getTraceAsString();
}
