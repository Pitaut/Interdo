<?php
require_once 'config.php';

echo "<h2>Test CURL avec OpenRouteService</h2>";

$testAddress = "53 avenue Victor Hugo 49100 Angers";

$url = 'https://api.openrouteservice.org/geocode/search';
$params = [
    'api_key' => OPENROUTE_API_KEY,
    'text' => $testAddress,
    'size' => 1
];

$fullUrl = $url . '?' . http_build_query($params);

echo "<p><strong>URL testée :</strong> " . htmlspecialchars($fullUrl) . "</p>";

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $fullUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
curl_setopt($ch, CURLOPT_VERBOSE, true);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
$curlInfo = curl_getinfo($ch);
curl_close($ch);

echo "<h3>Résultats :</h3>";
echo "<p><strong>HTTP Code:</strong> $httpCode</p>";
echo "<p><strong>CURL Error:</strong> " . ($curlError ?: "Aucune") . "</p>";
echo "<p><strong>Response:</strong></p>";
echo "<pre>" . htmlspecialchars($response) . "</pre>";

echo "<h3>Informations CURL complètes :</h3>";
echo "<pre>" . print_r($curlInfo, true) . "</pre>";

if ($response) {
    $data = json_decode($response, true);
    echo "<h3>Données décodées :</h3>";
    echo "<pre>" . print_r($data, true) . "</pre>";
}
