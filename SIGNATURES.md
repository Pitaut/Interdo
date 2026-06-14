# Documentation - Signatures Client

## Vue d'ensemble

Le système de signatures client permet de capturer la signature électronique du client dans deux contextes :

1. **Clôture d'intervention** : Signature pour valider la fin de l'intervention
2. **Vente de forfait** : Signature pour confirmer l'achat du/des forfait(s)

## Fonctionnement technique

### Frontend (JavaScript)

- **Bibliothèque** : `includes/signature-pad.js` - Canvas signature capture
- **Modales** : Deux modales distinctes avec canvas HTML5
  - `#signatureClotureModal` - Pour la clôture d'intervention
  - `#signatureVenteModal` - Pour la vente de forfaits

### Canvas Signature

Le canvas permet de :
- Dessiner avec la souris ou le doigt (tactile)
- Effacer la signature
- Convertir en base64 data URL (format PNG)

### Backend (PHP)

#### Clôture d'intervention
- **Fichier** : `api/interventions.php`
- **Action** : `close_forfait`
- **Paramètre** : `signature_client` (base64 data URL)
- **Stockage** : Colonne `rendez_vous.signature_client` (LONGTEXT)

#### Vente de forfait
- **Fichier** : `api/forfaits.php`
- **Action** : `vendre`
- **Paramètre** : `signature_client` (base64 data URL)
- **Stockage** : Colonne `forfaits_vendus.signature_client` (LONGTEXT)
- **Date** : Colonne `forfaits_vendus.date_signature` (DATETIME) - Remplie automatiquement

## Base de données

### Table `rendez_vous`
```sql
signature_client LONGTEXT DEFAULT NULL COMMENT 'Signature client en base64 (clôture intervention)'
```

### Table `forfaits_vendus`
```sql
signature_client BLOB,
date_signature DATETIME DEFAULT NULL
```

## Migration automatique

Le système inclut une migration automatique dans `migrations/signatures_migration.php` qui :
- S'exécute automatiquement au premier chargement
- Ajoute les colonnes si elles n'existent pas
- Ne modifie rien si les colonnes existent déjà

## Workflow utilisateur

### 1. Clôture d'intervention

1. Utilisateur clique sur "✓ Clôturer" dans le modal RDV
2. Modal de signature s'ouvre
3. Client signe sur le canvas
4. Validation → Signature convertie en base64
5. Envoi au backend avec les données de clôture
6. Signature stockée dans `rendez_vous.signature_client`

### 2. Vente de forfait

1. Utilisateur sélectionne des forfaits dans le panier
2. Clique sur "Valider le panier"
3. Confirmation → Modal de signature s'ouvre
4. Client signe sur le canvas
5. Validation → Signature convertie en base64
6. Envoi au backend pour chaque forfait vendu
7. Signature stockée dans `forfaits_vendus.signature_client`
8. Date stockée dans `forfaits_vendus.date_signature`

## Format de stockage

- **Type** : Data URL base64 PNG
- **Exemple** : `data:image/png;base64,iVBORw0KGgoAAAANS...`
- **Taille** : Variable (typiquement 10-50 KB par signature)
- **Utilisation** : Peut être affiché directement dans une balise `<img src="...">`

## Récupération et affichage

### Afficher une signature stockée
```php
// Récupérer depuis la BDD
$stmt = $pdo->query("SELECT signature_client FROM rendez_vous WHERE id = 123");
$signature = $stmt->fetchColumn();

// Afficher
if ($signature) {
    echo '<img src="' . htmlspecialchars($signature) . '" alt="Signature client" style="border:1px solid #ddd;">';
}
```

## Validation

### Frontend
- Vérification que le canvas n'est pas vide avant validation
- Message d'erreur si pas de signature

### Backend
- Paramètre `signature_client` optionnel (peut être NULL)
- Pas de validation stricte côté serveur pour le moment

## Sécurité

- Les signatures sont stockées en base64 (pas de fichiers)
- LONGTEXT peut stocker jusqu'à 4 GB (largement suffisant)
- Pas de risque d'injection SQL (PDO prepared statements)

## Performance

- Impact minimal sur les performances
- Les signatures sont chargées uniquement quand nécessaire
- Pas de compression appliquée (PNG déjà compressé)

## Évolutions futures possibles

1. Compression des signatures avant stockage
2. Stockage dans des fichiers séparés au lieu de la BDD
3. Validation de la qualité de signature (nb de points)
4. Export PDF avec signature intégrée
5. Double signature (client + intervenant)
6. Horodatage cryptographique

## Fichiers modifiés

### Frontend
- `agenda.php` : Ajout modales + JavaScript signature
- `includes/signature-pad.js` : Bibliothèque signature (nouveau)

### Backend
- `api/interventions.php` : Gestion signature clôture
- `api/forfaits.php` : Gestion signature vente
- `config.php` : Inclusion migration automatique

### Base de données
- `structure.sql` : Définition colonnes signature
- `migrations/signatures_migration.php` : Migration automatique
- `migrations/add_signature_columns.sql` : Script SQL manuel (optionnel)

## Support

Pour toute question ou problème :
1. Vérifier que la migration s'est exécutée (colonnes créées)
2. Consulter les logs PHP pour les erreurs
3. Vérifier la console JavaScript pour les erreurs frontend
4. Tester avec DEBUG_MODE = true dans config.php
