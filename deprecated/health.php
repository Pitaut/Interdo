<?php
header('Content-Type: application/json');
// Endpoint de test rapide pour vérifier que PHP répond via HTTP (ne touche pas à la DB)
echo json_encode([
    'status' => 'ok',
    'time' => date('c'),
    'app' => defined('APP_NAME') ? APP_NAME : 'Agenda'
]);
exit;
