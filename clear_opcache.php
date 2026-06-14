<?php
// Script pour vider le cache opcache (accessible via HTTP)
header('Content-Type: application/json');

$result = [
    'opcache_enabled' => function_exists('opcache_reset'),
    'cache_reset' => false,
    'timestamp' => date('Y-m-d H:i:s')
];

if (function_exists('opcache_reset')) {
    $result['cache_reset'] = opcache_reset();
    $result['message'] = 'Cache opcache vidé avec succès';
} else {
    $result['message'] = 'opcache non disponible';
}

// Forcer le rechargement du fichier interventions.php
if (function_exists('opcache_invalidate')) {
    $file = __DIR__ . '/api/interventions.php';
    opcache_invalidate($file, true);
    $result['interventions_invalidated'] = true;
}

echo json_encode($result, JSON_PRETTY_PRINT);
