# 🚀 Guide Rapide Développeur

**Application** : Agenda Interdo  
**Version** : 3.0  
**Date** : 21 décembre 2025

Guide de référence rapide pour les développeurs travaillant sur le projet.

---

## ⚡ Démarrage en 5 minutes

```bash
# 1. Cloner et accéder
cd c:\wamp64\www
git clone <repo> _Interdo
cd _Interdo

# 2. Créer la base
mysql -u root -e "CREATE DATABASE agenda_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"

# 3. Importer le schéma
mysql -u root agenda_db < structure.sql
mysql -u root agenda_db < database.sql

# 4. Configurer
# Éditer config.php avec vos identifiants MySQL

# 5. Tester
# Ouvrir http://localhost/_Interdo/agenda.php
```

---

## 📂 Structure essentielle

```
_Interdo/
├── agenda.php              # 🎯 Point d'entrée principal
├── api/                    # 🔌 5 contrôleurs REST
│   ├── events.php         # Rendez-vous
│   ├── clients.php        # Clients
│   ├── techniciens.php    # Techniciens
│   ├── forfaits.php       # Forfaits + ventes
│   └── interventions.php  # Clôture
├── config.php             # ⚙️ Configuration BDD
└── includes/              # 🎨 Ressources partagées
```

---

## 🔌 API - Exemples rapides

### Créer un événement

```javascript
fetch('api/events.php?action=create', {
  method: 'POST',
  headers: { 'Content-Type': 'application/json' },
  body: JSON.stringify({
    titre: 'Intervention M. Dupont',
    date_rdv: '2025-12-25',
    heure_debut: '09:00:00',
    heure_fin: '11:00:00',
    client_id: 5,
    id_technicien: 2
  })
})
.then(r => r.json())
.then(data => console.log(data.id)); // ID du nouvel événement
```

### Lister les clients

```javascript
fetch('api/clients.php?action=list')
  .then(r => r.json())
  .then(clients => console.log(clients));
```

### Clôturer une intervention

```javascript
fetch('api/interventions.php?action=close_forfait', {
  method: 'POST',
  headers: { 'Content-Type': 'application/json' },
  body: JSON.stringify({
    rendez_vous_id: 42,
    heure_debut: '09:00:00',
    heure_fin: '10:20:00',
    appliquer_arrondi: 1,  // 1=arrondi sup, 0=arrondi inf
    signature_client: 'data:image/png;base64,...'
  })
})
.then(r => r.json())
.then(data => console.log(data.message));
```

**Documentation complète** : [includes/API_CONSOLIDEE.md](includes/API_CONSOLIDEE.md)

---

## 🗄️ Base de données - Requêtes utiles

### Lister tous les rendez-vous d'un client

```sql
SELECT r.*, c.nom, c.prenom, t.nom as technicien
FROM rendez_vous r
INNER JOIN clients c ON r.client_id = c.id
LEFT JOIN techniciens t ON r.id_technicien = t.id
WHERE c.id = 5
ORDER BY r.date_rdv DESC, r.heure_debut DESC;
```

### Vérifier le solde d'un client

```sql
SELECT 
  c.nom, 
  c.prenom,
  c.heure_bonus as solde_bonus,
  SUM(fv.heures_restantes) as heures_forfaits,
  c.heure_bonus + SUM(fv.heures_restantes) as solde_total
FROM clients c
LEFT JOIN forfaits_vendus fv ON c.id = fv.client_id AND fv.statut = 'actif'
WHERE c.id = 5
GROUP BY c.id;
```

### Historique d'une intervention

```sql
SELECT 
  h.*,
  r.titre,
  fv.forfait_id,
  CASE 
    WHEN h.forfait_vendu_id IS NULL THEN 'Hors forfait'
    ELSE 'Forfait'
  END as type_cloture
FROM historique_consommation h
INNER JOIN rendez_vous r ON h.rendez_vous_id = r.id
LEFT JOIN forfaits_vendus fv ON h.forfait_vendu_id = fv.id
WHERE h.rendez_vous_id = 42;
```

### Clients à risque

```sql
SELECT 
  c.id,
  c.nom,
  c.prenom,
  COALESCE(SUM(fv.heures_restantes), 0) as heures_restantes,
  c.rappel_prochaine_date,
  DATEDIFF(NOW(), c.rappel_prochaine_date) as jours_sans_rappel
FROM clients c
LEFT JOIN forfaits_vendus fv ON c.id = fv.client_id AND fv.statut = 'actif'
GROUP BY c.id
HAVING (
  (heures_restantes <= 0 AND jours_sans_rappel > 30)
  OR (heures_restantes < 2 AND jours_sans_rappel > 60)
  OR (heures_restantes >= 2 AND jours_sans_rappel > 90)
);
```

**Schéma complet** : [structure.sql](structure.sql)

---

## 🐛 Debug rapide

### Activer le mode debug

```php
// config.php
define('DEBUG_MODE', true);
```

### Logs PHP

```bash
# Windows (WAMP)
tail -f C:\wamp64\logs\php_error.log

# Linux
tail -f /var/log/apache2/error.log
```

### Tester une requête SQL

```bash
mysql -u root -p agenda_db
```

```sql
-- Dans MySQL
SET @client_id = 5;
-- Copier-coller la requête à tester
```

### Vérifier la connexion PDO

```php
<?php
require 'config.php';
try {
    $pdo = getDBConnection();
    echo "✓ Connexion réussie\n";
    $stmt = $pdo->query("SELECT COUNT(*) FROM clients");
    echo "Clients: " . $stmt->fetchColumn() . "\n";
} catch (Exception $e) {
    echo "✗ Erreur: " . $e->getMessage() . "\n";
}
?>
```

---

## ⚙️ Tâches courantes

### Ajouter un nouveau statut de rendez-vous

1. **config.php** :
```php
define('STATUTS_RDV', ['planifie', 'en_cours', 'termine', 'annule', 'reporte']);
define('COULEURS_STATUT', [
    'planifie' => '#3788d8',
    'en_cours' => '#f59e0b',
    'termine' => '#10b981',
    'annule' => '#ef4444',
    'reporte' => '#8b5cf6'  // Nouveau
]);
```

2. **structure.sql** :
```sql
ALTER TABLE rendez_vous 
MODIFY COLUMN statut ENUM('planifie','en_cours','termine','annule','reporte') 
DEFAULT 'planifie';
```

3. **agenda.php** : Ajouter option dans le select

### Ajouter un champ à la table clients

1. **Migration SQL** :
```sql
-- migrations/007_add_note_interne.sql
ALTER TABLE clients 
ADD COLUMN note_interne TEXT AFTER rappel_notes;
```

2. **api/clients.php** : Ajouter dans INSERT et UPDATE

3. **clients.php** : Ajouter champ dans le formulaire

### Créer un nouvel endpoint

```php
// api/mon_endpoint.php
<?php
require_once '../config.php';
header('Content-Type: application/json; charset=utf-8');

$action = $_GET['action'] ?? '';

switch ($action) {
    case 'ma_fonction':
        handleMaFonction();
        break;
    default:
        http_response_code(400);
        echo json_encode(['error' => 'Action invalide']);
}

function handleMaFonction() {
    try {
        $pdo = getDBConnection();
        $data = json_decode(file_get_contents('php://input'), true);
        
        // Validation
        if (empty($data['param'])) {
            throw new Exception('Paramètre requis');
        }
        
        // Requête
        $stmt = $pdo->prepare("SELECT * FROM table WHERE col = ?");
        $stmt->execute([$data['param']]);
        $result = $stmt->fetchAll();
        
        echo json_encode(['status' => 'success', 'data' => $result]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => $e->getMessage()]);
    }
}
?>
```

---

## 🧪 Tests

### Test manuel d'un endpoint (PowerShell)

```powershell
# GET
Invoke-RestMethod -Uri 'http://localhost/_Interdo/api/events.php?action=list'

# POST
$body = @{ titre='Test'; date_rdv='2025-12-25' } | ConvertTo-Json
Invoke-RestMethod -Uri 'http://localhost/_Interdo/api/events.php?action=create' `
  -Method Post -Body $body -ContentType 'application/json'
```

### Test manuel (curl)

```bash
# GET
curl "http://localhost/_Interdo/api/events.php?action=list"

# POST
curl -X POST "http://localhost/_Interdo/api/events.php?action=create" \
  -H "Content-Type: application/json" \
  -d '{"titre":"Test","date_rdv":"2025-12-25"}'
```

### Scripts de test automatisés

```powershell
cd scripts
.\test_api_consolidee.ps1
```

---

## 🔑 Règles métier importantes

### 1. Heures forfait = multiples de 30 minutes

```php
// Arrondir à 0.5h
$heures = round($heures * 2) / 2;
```

### 2. Bonus/malus = perspective CLIENT

```php
// Positif = bonus pour le client
// Négatif = malus pour le client
$difference_arrondi = $duree_arrondie - $duree_reelle;
```

### 3. Traçabilité = 100%

Toute intervention terminée DOIT avoir :
- Une entrée dans `historique_consommation`
- Mise à jour du `heure_bonus` du client

### 4. Décompte forfait = FIFO

```sql
ORDER BY fv.date_achat ASC
```

### 5. Affichage = Minutes

```javascript
function afficherEnMinutes(heures) {
  return Math.round(heures * 60) + ' min';
}
```

---

## 🚨 Erreurs courantes

### "forfait_vendu_id cannot be NULL"

**Solution** : Appliquer migration 006
```bash
mysql -u root agenda_db < migrations/006_forfait_vendu_null.sql
```

### "Call to undefined function getDBConnection()"

**Solution** : Ajouter `require_once '../config.php';` en haut du fichier

### Bonus/malus inversés

**Cause** : Mauvaise formule (v2.0)  
**Solution** : Utiliser `$difference = $arrondi - $reel;` (v3.0)

### FullCalendar ne s'affiche pas

**Vérifications** :
1. Fichiers JS présents dans `includes/`
2. Pas d'erreur console (F12)
3. `timeZone` défini dans `calendar.options`

---

## 📋 Checklist avant commit

- [ ] Code testé localement
- [ ] Pas d'erreur PHP (activer DEBUG_MODE)
- [ ] Pas d'erreur JavaScript (console)
- [ ] Requêtes SQL avec PDO préparées
- [ ] Validation des entrées utilisateur
- [ ] Échappement des sorties (`htmlspecialchars`)
- [ ] Commentaires ajoutés si logique complexe
- [ ] Documentation mise à jour si nécessaire

---

## 🔗 Liens rapides

| Document | Usage |
|----------|-------|
| [README.md](README.md) | Vue d'ensemble |
| [DOCUMENTATION_TECHNIQUE.md](DOCUMENTATION_TECHNIQUE.md) | Architecture détaillée |
| [includes/API_CONSOLIDEE.md](includes/API_CONSOLIDEE.md) | Référence API |
| [FLUX_CLOTURE.md](FLUX_CLOTURE.md) | Logique de clôture |
| [CHANGELOG.md](CHANGELOG.md) | Historique versions |
| [GUIDE_DEPLOIEMENT.md](GUIDE_DEPLOIEMENT.md) | Déploiement |

---

## 🎯 Bonnes pratiques

### Code PHP

```php
// ✅ BON
$stmt = $pdo->prepare("SELECT * FROM clients WHERE id = ?");
$stmt->execute([$id]);
$client = $stmt->fetch();

// ❌ MAUVAIS (injection SQL)
$result = $pdo->query("SELECT * FROM clients WHERE id = $id");
```

### Code JavaScript

```javascript
// ✅ BON
fetch('api/events.php?action=list')
  .then(r => {
    if (!r.ok) throw new Error('Erreur HTTP');
    return r.json();
  })
  .then(data => /* ... */)
  .catch(err => console.error(err));

// ❌ MAUVAIS (pas de gestion d'erreur)
fetch('api/events.php?action=list')
  .then(r => r.json())
  .then(data => /* ... */);
```

### SQL

```sql
-- ✅ BON (avec index)
SELECT * FROM rendez_vous 
WHERE date_rdv BETWEEN '2025-12-01' AND '2025-12-31'
AND id_technicien = 2;

-- ❌ MAUVAIS (pas d'index sur YEAR/MONTH)
SELECT * FROM rendez_vous 
WHERE YEAR(date_rdv) = 2025 AND MONTH(date_rdv) = 12;
```

---

## 💡 Astuces

### Rechargement rapide du calendrier

```javascript
// Dans agenda.php
window.calendar.refetchEvents();
```

### Debug d'une requête SQL

```php
// Afficher la requête générée
$stmt = $pdo->prepare("SELECT * FROM clients WHERE nom LIKE ?");
$stmt->execute(['%Dupont%']);
echo $stmt->queryString;  // Affiche la requête
```

### Conversion rapide heures ↔ minutes

```javascript
const heures = 1.5;
const minutes = heures * 60;  // 90

const minutes2 = 80;
const heures2 = minutes2 / 60;  // 1.333...
const heuresArrondi = Math.round(heures2 * 2) / 2;  // 1.5
```

### Formater une date en PHP

```php
$date = '2025-12-25 14:30:00';
$dt = new DateTime($date);
echo $dt->format('d/m/Y H:i');  // 25/12/2025 14:30
```

---

## 📞 Besoin d'aide ?

1. Consulter [INDEX_DOCUMENTATION.md](INDEX_DOCUMENTATION.md)
2. Chercher dans [CHANGELOG.md](CHANGELOG.md)
3. Activer `DEBUG_MODE` et consulter logs
4. Tester requête SQL dans MySQL
5. Vérifier console navigateur (F12)

---

**Guide rapide développeur - Version 3.0**  
**Dernière mise à jour** : 21 décembre 2025
