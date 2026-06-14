<?php
/**
 * Script de test pour l'API interventions.php
 */
require_once 'config.php';

// Récupérer un rendez-vous de test
$pdo = getDBConnection();
$stmt = $pdo->query("
    SELECT r.id, r.titre, r.date_rdv, r.heure_debut, r.heure_fin, r.statut, r.client_id, c.nom as client_nom
    FROM rendez_vous r
    LEFT JOIN clients c ON r.client_id = c.id
    WHERE r.statut != 'termine'
    ORDER BY r.id DESC
    LIMIT 1
");
$rdv = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$rdv) {
    echo "❌ Aucun rendez-vous disponible pour les tests\n";
    
    // Chercher n'importe quel rendez-vous
    $stmt = $pdo->query("SELECT id, titre, date_rdv, statut FROM rendez_vous ORDER BY id DESC LIMIT 1");
    $rdv = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($rdv) {
        echo "Rendez-vous trouvé (statut: {$rdv['statut']}): ID {$rdv['id']} - {$rdv['titre']}\n";
        echo "Vous pouvez tester avec cet ID mais il est déjà en statut '{$rdv['statut']}'\n";
    }
    exit;
}

echo "✅ Rendez-vous de test trouvé:\n";
echo "   ID: {$rdv['id']}\n";
echo "   Titre: {$rdv['titre']}\n";
echo "   Client: {$rdv['client_nom']} (ID: {$rdv['client_id']})\n";
echo "   Date: {$rdv['date_rdv']}\n";
echo "   Heures: {$rdv['heure_debut']} → {$rdv['heure_fin']}\n";
echo "   Statut: {$rdv['statut']}\n";
echo "\n";

// Vérifier les forfaits du client
if ($rdv['client_id']) {
    $stmt = $pdo->prepare("
        SELECT SUM(heures_restantes) as total_heures 
        FROM forfaits_vendus 
        WHERE client_id = ? AND heures_restantes > 0
    ");
    $stmt->execute([$rdv['client_id']]);
    $forfait = $stmt->fetch(PDO::FETCH_ASSOC);
    
    echo "Forfaits disponibles: " . ($forfait['total_heures'] ?? 0) . " heures\n\n";
}

echo "═══════════════════════════════════════════════════════════════\n";
echo "Commandes PowerShell pour tester:\n";
echo "═══════════════════════════════════════════════════════════════\n\n";

echo "# 1. Vérifier les heures disponibles\n";
echo '$body = @{' . "\n";
echo "    rendez_vous_id = {$rdv['id']}\n";
echo "    heure_debut = '{$rdv['date_rdv']}T{$rdv['heure_debut']}'\n";
echo "    heure_fin = '{$rdv['date_rdv']}T{$rdv['heure_fin']}'\n";
echo '    appliquer_arrondi = $true' . "\n";
echo '} | ConvertTo-Json' . "\n\n";
echo 'Invoke-RestMethod -Uri "http://localhost/_Interdo/api/interventions.php?action=check_heures" -Method Post -Body $body -ContentType "application/json"' . "\n\n";

echo "# 2. Clôturer avec forfait\n";
echo '$body = @{' . "\n";
echo "    rendez_vous_id = {$rdv['id']}\n";
echo "    heure_debut = '{$rdv['date_rdv']}T{$rdv['heure_debut']}'\n";
echo "    heure_fin = '{$rdv['date_rdv']}T{$rdv['heure_fin']}'\n";
echo '    appliquer_arrondi = $true' . "\n";
echo '    signature_client = "data:image/png;base64,test"' . "\n";
echo '} | ConvertTo-Json' . "\n\n";
echo 'Invoke-RestMethod -Uri "http://localhost/_Interdo/api/interventions.php?action=close_forfait" -Method Post -Body $body -ContentType "application/json"' . "\n\n";
