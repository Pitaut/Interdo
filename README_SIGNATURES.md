# Système de Signature Client - Guide Rapide

## 📝 Nouvelles fonctionnalités

### 1. Signature à la clôture d'intervention
Lors de la clôture d'un rendez-vous, un modal demande au client de signer pour confirmer la fin de l'intervention.

**Workflow :**
1. Clic sur "✓ Clôturer" dans le détail d'un RDV
2. Modal de signature s'ouvre automatiquement
3. Le client signe avec souris ou doigt (tactile)
4. Bouton "Effacer" permet de recommencer
5. Bouton "✓ Valider" enregistre et clôture

### 2. Signature à la vente de forfait
Lors de la vente d'un ou plusieurs forfaits, un modal demande au client de signer pour confirmer l'achat.

**Workflow :**
1. Sélection forfaits dans le panier
2. Clic sur "Valider le panier"
3. Confirmation du montant
4. Modal de signature s'ouvre
5. Le client signe
6. Validation → Forfaits vendus avec signature

## 🎨 Interface

### Canvas de signature
- **Fond blanc** avec bordure
- **Trait noir** fluide
- **Support tactile** (smartphones/tablettes)
- **Bouton effacer** pour recommencer
- **Validation obligatoire** (empêche validation sans signature)

## 💾 Stockage

### Base de données
- **rendez_vous.signature_client** : Signature clôture intervention (LONGTEXT)
- **forfaits_vendus.signature_client** : Signature vente forfait (BLOB)
- **forfaits_vendus.date_signature** : Date/heure de signature (DATETIME)

### Format
- Data URL base64 PNG
- Exemple : `data:image/png;base64,iVBORw0KGgo...`
- Taille moyenne : 10-50 KB par signature
- Directement affichable dans `<img src="...">`

## 🔧 Installation/Migration

### Migration automatique
La migration s'exécute automatiquement au premier chargement de page :
- Ajoute les colonnes si manquantes
- Ne modifie rien si déjà présentes
- Aucune action manuelle requise

### Migration manuelle (optionnel)
Si besoin d'exécuter manuellement :
```bash
mysql -u root agenda_db < migrations/add_signature_columns.sql
```

## 📁 Fichiers ajoutés/modifiés

### Nouveaux fichiers
- `includes/signature-pad.js` - Bibliothèque canvas signature
- `migrations/signatures_migration.php` - Migration auto
- `migrations/add_signature_columns.sql` - Script SQL manuel
- `SIGNATURES.md` - Documentation complète
- `README_SIGNATURES.md` - Ce fichier

### Fichiers modifiés
- `agenda.php` - Modales + JavaScript signature
- `api/interventions.php` - Enregistrement signature clôture
- `api/forfaits.php` - Enregistrement signature vente
- `config.php` - Inclusion migration auto
- `structure.sql` - Ajout colonnes signature

## 🧪 Test

### Test clôture intervention
1. Ouvrir un RDV existant
2. Cliquer "✓ Clôturer"
3. Modal signature apparaît
4. Signer et valider
5. Vérifier en BDD : `SELECT signature_client FROM rendez_vous WHERE id=X`

### Test vente forfait
1. Depuis une clôture échouée (forfait insuffisant)
2. Ou depuis la page gestion
3. Sélectionner forfaits
4. Valider panier
5. Modal signature apparaît
6. Signer et valider
7. Vérifier en BDD : `SELECT signature_client FROM forfaits_vendus WHERE id=X`

## 🎯 Utilisation en production

### Afficher une signature
```php
<?php
$stmt = $pdo->query("SELECT signature_client FROM rendez_vous WHERE id = 123");
$signature = $stmt->fetchColumn();

if ($signature) {
    echo '<img src="' . htmlspecialchars($signature) . '" 
               alt="Signature client" 
               style="max-width:300px; border:1px solid #ddd;">';
} else {
    echo 'Pas de signature';
}
?>
```

## ⚡ Performance

- **Impact minimal** : Les signatures ne se chargent que quand nécessaire
- **Pas de compression** : PNG déjà optimisé
- **Stockage BDD** : Pas de fichiers à gérer
- **Taille maîtrisée** : Canvas 500x200px maximum

## 🔐 Sécurité

- ✅ Stockage base64 sécurisé
- ✅ PDO prepared statements (anti-injection)
- ✅ Pas de upload de fichiers
- ✅ Validation frontend (canvas non vide)

## 📊 Statistiques

Pour voir le taux de signatures :
```sql
-- Interventions avec signature
SELECT 
    COUNT(*) as total,
    SUM(CASE WHEN signature_client IS NOT NULL THEN 1 ELSE 0 END) as avec_signature,
    ROUND(SUM(CASE WHEN signature_client IS NOT NULL THEN 1 ELSE 0 END) * 100.0 / COUNT(*), 2) as taux_pct
FROM rendez_vous
WHERE statut = 'termine';

-- Forfaits avec signature
SELECT 
    COUNT(*) as total,
    SUM(CASE WHEN signature_client IS NOT NULL THEN 1 ELSE 0 END) as avec_signature
FROM forfaits_vendus;
```

## 🐛 Dépannage

### La signature ne s'affiche pas
- Vérifier console JavaScript (F12)
- Vérifier que `signature-pad.js` est bien chargé
- Vérifier le CSS du modal (display:block)

### La signature ne s'enregistre pas
- Vérifier logs PHP
- Vérifier que la colonne existe en BDD
- Exécuter la migration manuellement si besoin

### Canvas ne réagit pas au tactile
- Ajouter `touch-action:none` au canvas
- Vérifier support tactile du navigateur

## 📞 Support

Pour toute question, consulter :
1. `SIGNATURES.md` - Documentation technique complète
2. Console navigateur (F12) - Erreurs JavaScript
3. Logs PHP - Erreurs backend
4. `config.php` - Activer DEBUG_MODE pour plus de détails
