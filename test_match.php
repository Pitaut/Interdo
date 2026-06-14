<?php
// Test simple de match()
$type = 'voiture';

$result = match($type) {
    'voiture' => 'voiture',
    'utilitaire' => 'voiture',
    'camionnette' => 'voiture',
    'moto' => 'moto',
    default => 'autre'
};

echo "Résultat: $result\n";
echo "PHP Version: " . phpversion() . "\n";
