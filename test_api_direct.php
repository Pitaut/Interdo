<?php
/**
 * Test direct de l'API interventions avec et sans arrondi
 */
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Inclure l'API directement
$_SERVER['REQUEST_METHOD'] = 'POST';
$_GET['action'] = 'check_heures';

// Simuler un body JSON
$json_data = json_encode([
    'rendez_vous_id' => 90,
    'heure_debut' => '2026-02-10T13:00:00',
    'heure_fin' => '2026-02-10T14:00:00',
    'appliquer_arrondi' => true
]);

// Capturer la sortie de l'API
ob_start();

// Simuler php://input
$GLOBALS['test_input'] = $json_data;
$old_file_get_contents = 'file_get_contents';

// Remplacer temporairement file_get_contents pour php://input
stream_wrapper_unregister("php");
stream_wrapper_register("php", "MockPHPStream");

class MockPHPStream {
    public $context;
    private $position = 0;
    
    function stream_open($path) {
        if ($path === 'php://input') {
            return true;
        }
        return false;
    }
    
    function stream_read($count) {
        $ret = substr($GLOBALS['test_input'], $this->position, $count);
        $this->position += strlen($ret);
        return $ret;
    }
    
    function stream_eof() {
        return $this->position >= strlen($GLOBALS['test_input']);
    }
    
    function stream_stat() {
        return [];
    }
}

try {
    require 'api/interventions.php';
    $output = ob_get_clean();
    
    echo "═══════════════════════════════════════════\n";
    echo "TEST DIRECT DE L'API INTERVENTIONS\n";
    echo "═══════════════════════════════════════════\n\n";
    
    echo "Requête envoyée:\n";
    echo $json_data . "\n\n";
    
    echo "Réponse de l'API:\n";
    echo $output . "\n\n";
    
    $response = json_decode($output, true);
    if ($response) {
        echo "Champs retournés:\n";
        foreach ($response as $key => $value) {
            echo "  - $key: " . var_export($value, true) . "\n";
        }
        
        if (isset($response['arrondi_necessaire'])) {
            echo "\n✅ Le champ 'arrondi_necessaire' est présent!\n";
            echo "   Valeur: " . ($response['arrondi_necessaire'] ? 'OUI' : 'NON') . "\n";
        } else {
            echo "\n❌ Le champ 'arrondi_necessaire' est ABSENT\n";
            echo "   Le cache opcache n'est pas encore vidé\n";
        }
        
        if (isset($response['duree_exacte'])) {
            echo "✅ Le champ 'duree_exacte' est présent!\n";
            echo "   Valeur: " . ($response['duree_exacte'] ? 'OUI' : 'NON') . "\n";
        }
    }
} catch (Exception $e) {
    ob_end_clean();
    echo "❌ ERREUR: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
}
