<?php
// Vider le cache opcache
if (function_exists('opcache_reset')) {
    opcache_reset();
    echo "✅ Cache opcache vidé\n";
} else {
    echo "⚠️  opcache non activé\n";
}

// Vérifier la version du fichier interventions.php
$file = __DIR__ . '/api/interventions.php';
echo "Dernière modification de interventions.php: " . date('Y-m-d H:i:s', filemtime($file)) . "\n";
