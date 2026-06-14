# 📊 Gestion de la Durée des Rendez-vous

**Version**: 3.0  
**Date**: 21 décembre 2025  

## ⚠️ Règles Métier Importantes

### 1. Multiples de 30 minutes
**Toutes les heures de forfait doivent être des multiples de 30 minutes (0.5h).**

Exemples valides : 1h00, 1h30, 2h00, 2h30, etc.  
Arrondis automatiques appliqués lors de la création/vente de forfaits.

### 2. Perspective Client
**Le calcul bonus/malus est réalisé du point de vue CLIENT :**
- **Bonus (positif)** : Le client paie PLUS que le temps réel  
  Exemple : 1h10 passé, 1h30 facturé → +20 min de crédit
- **Malus (négatif)** : Le client paie MOINS que le temps réel  
  Exemple : 1h40 passé, 1h30 facturé → -10 min de crédit

Formule : `difference_arrondi = temps_arrondi - temps_reel`

### 3. Affichage
**Toutes les durées sont affichées en MINUTES dans l'interface utilisateur.**
- Plus lisible pour les utilisateurs
- Conversion : 0.33h → 20 min
- Stockage base de données reste en heures décimales

---

## Tables et Champs de la Base de Données

### 🎯 1. Table `rendez_vous`

**Rôle :** Stocke les rendez-vous avec leurs horaires planifiés

**Champs liés à la durée :**
- `id` : Identifiant unique (INT, PRIMARY KEY)
- `date_rdv` : Date du rendez-vous (DATE, ex: "2025-12-07")
- `heure_debut` : Heure de début (TIME, ex: "09:00:00")
- `heure_fin` : Heure de fin (TIME, ex: "11:30:00")
- `duree_reelle` : **Durée réelle de l'intervention en heures** (DECIMAL(10,2), rempli après clôture)
  - Calculé automatiquement : `heure_fin - heure_debut`
  - Représente le temps réellement passé chez le client à la minute près
  - Exemple : 1.30 pour 1h18, 2.50 pour 2h30
- `client_id` : Référence au client (INT, FOREIGN KEY)
- `statut` : État du rendez-vous (ENUM: 'planifie', 'en_cours', 'termine', 'annule')
- `titre` : Titre du rendez-vous (VARCHAR)
- `description` : Description détaillée (TEXT)
- `lieu` : Adresse d'intervention (VARCHAR)
- `id_technicien` : Référence au technicien assigné (INT)

**✅ Important :** Le champ `duree_reelle` stocke la durée calculée lors de la clôture (`heure_fin - heure_debut`). Ce temps correspond exactement au temps passé chez le client, à la minute près.

---

### 📝 2. Table `historique_consommation`

**Rôle :** Enregistre chaque clôture d'intervention avec les détails de consommation et d'arrondi

**Champs liés à la durée :**
- `id` : Identifiant unique (INT, PRIMARY KEY)
- `rendez_vous_id` : Référence au rendez-vous clôturé (INT, FOREIGN KEY)
- `forfait_vendu_id` : Référence au forfait utilisé (INT, FOREIGN KEY, NULL si hors forfait)
- `client_id` : Référence au client (INT, FOREIGN KEY)
- `date_rdv` : Copie de la date du rendez-vous (DATE)
- `heure_debut` : Copie de l'heure de début (TIME)
- `heure_fin` : Copie de l'heure de fin (TIME)
- `temps_reel` : **Durée réelle en heures décimales** (DECIMAL(10,2), ex: 1.30 pour 1h18)
- `temps_arrondi` : **Durée arrondie facturée** (DECIMAL(10,2), ex: 1.50 pour 1h30 ou 1.00 pour 1h00)
- `difference_arrondi` : **Bonus/malus CLIENT en heures** (DECIMAL(10,2), ex: +0.33 pour +20 min bonus, -0.33 pour -20 min malus)
  - **Formule : `temps_arrondi - temps_reel`**
  - **Positif = BONUS pour le client**
  - **Négatif = MALUS pour le client**
- `heures_decomptes` : Heures décomptées du forfait (DECIMAL(10,2), = `temps_arrondi`)
- `heures_avant` : Heures disponibles avant décompte (DECIMAL(10,2))
- `heures_apres` : Heures disponibles après décompte (DECIMAL(10,2))
- `created_at` : Horodatage de la clôture (TIMESTAMP)

**💡 Cette table constitue l'historique complet de toutes les interventions clôturées avec traçabilité des arrondis.**

---

### 💼 3. Table `forfaits_vendus`

**Rôle :** Gère les forfaits clients et leur consommation

**Champs liés à la durée :**
- `id` : Identifiant unique (INT, PRIMARY KEY)
- `client_id` : Référence au client (INT, FOREIGN KEY)
- `forfait_id` : Référence au type de forfait (INT, FOREIGN KEY)
- `heures_totales` : Nombre d'heures totales du forfait (DECIMAL(10,2))
- `heures_restantes` : **Heures disponibles** (DECIMAL(10,2))
  - **Mise à jour automatique** lors de chaque clôture : `heures_restantes = heures_restantes - temps_arrondi`
- `date_achat` : Date d'achat du forfait (DATE)
- `date_expiration` : Date d'expiration (DATE, NULL si illimité)
- `statut` : État du forfait (ENUM: 'actif', 'epuise', 'expire')

---

## ⚙️ Flux de Calcul de Durée

### Étape 1 : Saisie des Heures (Frontend - `agenda.php`)

**Emplacement :** Modal de création/modification de rendez-vous

```javascript
// L'utilisateur saisit/modifie les heures
heure_debut = "09:00"
heure_fin = "11:18"

// Calcul durée en minutes
const debut = new Date('2025-12-07T09:00:00');
const fin = new Date('2025-12-07T11:18:00');
const dureeMinutes = (fin - debut) / 60000; // 138 minutes
const dureeHeures = dureeMinutes / 60; // 2.3 heures
```

Cette durée calculée sera enregistrée dans le champ `duree_reelle` de la table `rendez_vous` lors de la clôture.

**Fichier :** `agenda.php` (lignes ~2372-2390)

---

### Étape 2 : Proposition Arrondi (Frontend - `agenda.php`)

**Emplacement :** Fonction `confirmerClotureAvecHeures()`

```javascript
// Arrondi supérieur (ceil - arrondi vers le haut)
const dureeArrondieSup = Math.ceil(dureeHeures * 2) / 2; // 2.5h

// Arrondi inférieur (floor - arrondi vers le bas)
const dureeArrondieInf = Math.floor(dureeHeures * 2) / 2; // 2.0h

// Calcul bonus/malus ENTREPRISE
// Formule : temps_réel - temps_facturé
const bonusArrondiSup = (dureeHeures - dureeArrondieSup) * 60; 
// 2.3 - 2.5 = -0.2h = -12 min (MALUS entreprise - client vous doit des heures)

const bonusArrondiInf = (dureeHeures - dureeArrondieInf) * 60; 
// 2.3 - 2.0 = +0.3h = +18 min (BONUS entreprise - client ne vous doit pas)
```

**Fichier :** `agenda.php` (lignes ~2390-2400)

**Affichage modal :**
- Carte 🔴 ROUGE si malus (client vous doit)
- Carte 🟢 VERTE si bonus (client ne vous doit pas)

---

### Étape 3 : Clôture avec Arrondi (Backend - `api/interventions.php`)

**Emplacement :** Fonction `handleCloseForfait()` ou `handleCloseHorsForfait()`

```php
// Réception du choix utilisateur
$appliquer_arrondi = true; // true = arrondi SUP, false = arrondi INF

// Calcul durée réelle
$debut = new DateTime($heure_debut); // "09:00:00"
$fin = new DateTime($heure_fin);     // "11:18:00"
$diff_minutes = ($fin->getTimestamp() - $debut->getTimestamp()) / 60; // 138 min
$duree_heures = $diff_minutes / 60; // 2.3 heures

// Application de l'arrondi selon le choix
if ($appliquer_arrondi) {
    // Arrondi SUPÉRIEUR : ceil pour arrondir vers le haut
    $duree_arrondie = ceil($duree_heures * 2) / 2; // 2.5h
} else {
    // Arrondi INFÉRIEUR : floor pour arrondir vers le bas
    $duree_arrondie = floor($duree_heures * 2) / 2; // 2.0h
}

// Calcul bonus/malus ENTREPRISE
// Formule : temps_réel - temps_facturé
$difference_arrondi = $duree_heures - $duree_arrondie; 
// Exemple arrondi SUP : 2.3 - 2.5 = -0.2h (malus)
// Exemple arrondi INF : 2.3 - 2.0 = +0.3h (bonus)
```

**Fichier :** `api/interventions.php` (lignes ~175-190 et ~251-256)

---

### Étape 4 : Enregistrement en Base (Backend - `api/interventions.php`)

**Emplacement :** Fonction `handleCloseForfait()`

```php
// Mise à jour du rendez-vous
$stmt = $pdo->prepare("
    UPDATE rendez_vous 
    SET statut = 'termine',
        heure_debut = ?,
        heure_fin = ?,
        duree_reelle = ?
    WHERE id = ?
");
$stmt->execute([
    $heure_debut,        // "09:00:00"
    $heure_fin,          // "11:18:00"
    $duree_heures,       // 2.30 (temps réel calculé)
    $rendez_vous_id      // 123
]);

// Insertion dans historique_consommation
$stmt = $pdo->prepare("
    INSERT INTO historique_consommation 
    (rendez_vous_id, forfait_vendu_id, client_id,
     temps_reel, temps_arrondi, difference_arrondi,
     heures_decomptes, heures_avant, heures_apres,
     date_rdv, heure_debut, heure_fin)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
");

$stmt->execute([
    $rendez_vous_id,        // 123
    $forfait_principal['id'], // 45 (ou NULL si hors forfait)
    $rdv['client_id'],      // 67
    $duree_heures,          // 2.30 (temps réel)
    $duree_arrondie,        // 2.50 (temps facturé)
    $difference_arrondi,    // -0.20 (malus entreprise)
    $duree_arrondie,        // 2.50 (heures décomptées = temps facturé)
    $heures_avant_total,    // 10.00 (solde avant)
    $heures_avant_total - $duree_arrondie, // 7.50 (solde après)
    $rdv['date_rdv'],       // "2025-12-07"
    $heure_debut,           // "09:00:00"
    $heure_fin              // "11:18:00"
]);

// Mise à jour du forfait
$stmt = $pdo->prepare("
    UPDATE forfaits_vendus 
    SET heures_restantes = heures_restantes - ?
    WHERE id = ?
");
$stmt->execute([$duree_arrondie, $forfait_id]); // -2.50h
```

**Fichier :** `api/interventions.php` (lignes ~260-280)

---

## 🔄 Exemple Complet : Intervention 1h18

### Données Initiales
- **Heure début :** `09:00`
- **Heure fin :** `10:18`
- **Durée réelle :** **1.30h** (1 heure 18 minutes = 78 minutes)
- **Forfait client avant :** 10.00h

---

### Scénario A - Arrondi SUPÉRIEUR 🔴

**Choix technicien :** Bouton "Arrondi SUP"

```
Temps facturé : 1.50h (90 minutes)
Bonus/malus : 1.30 - 1.50 = -0.20h (-12 minutes)
→ MALUS entreprise (client vous doit 12 min)
→ Affichage : Carte ROUGE

Base de données :
├─ rendez_vous.duree_reelle : 1.30
├─ historique_consommation.temps_reel : 1.30
├─ historique_consommation.temps_arrondi : 1.50
├─ historique_consommation.difference_arrondi : -0.20
├─ heures_avant : 10.00
├─ heures_decomptes : 1.50
└─ heures_apres : 8.50

Forfait client après : 8.50h
```

---

### Scénario B - Arrondi INFÉRIEUR 🟢

**Choix technicien :** Bouton "Arrondi INF"

```
Temps facturé : 1.00h (60 minutes)
Bonus/malus : 1.30 - 1.00 = +0.30h (+18 minutes)
→ BONUS entreprise (client ne vous doit pas)
→ Affichage : Carte VERTE

Base de données :
├─ rendez_vous.duree_reelle : 1.30
├─ historique_consommation.temps_reel : 1.30
├─ historique_consommation.temps_arrondi : 1.00
├─ historique_consommation.difference_arrondi : +0.30
├─ heures_avant : 10.00
├─ heures_decomptes : 1.00
└─ heures_apres : 9.00

Forfait client après : 9.00h
```

---

## 📋 Requêtes SQL Utiles

### Consulter l'historique des durées

```sql
SELECT 
    hc.date_rdv,
    hc.heure_debut,
    hc.heure_fin,
    hc.temps_reel,
    hc.temps_arrondi,
    hc.difference_arrondi,
    CONCAT(c.prenom, ' ', c.nom) AS client_nom,
    rv.titre AS intervention
FROM historique_consommation hc
LEFT JOIN clients c ON hc.client_id = c.id
LEFT JOIN rendez_vous rv ON hc.rendez_vous_id = rv.id
ORDER BY hc.created_at DESC
LIMIT 50;
```

---

### Calculer le bonus/malus total d'un client

```sql
SELECT 
    client_id,
    CONCAT(c.prenom, ' ', c.nom) AS client_nom,
    SUM(difference_arrondi) AS total_bonus_malus_heures,
    ROUND(SUM(difference_arrondi * 60), 0) AS total_bonus_malus_minutes,
    COUNT(*) AS nombre_interventions
FROM historique_consommation hc
LEFT JOIN clients c ON hc.client_id = c.id
WHERE client_id = ?
GROUP BY client_id;
```

**Exemple de résultat :**
```
client_id: 67
client_nom: Jean Dupont
total_bonus_malus_heures: +2.50
total_bonus_malus_minutes: +150
nombre_interventions: 12

Interprétation : Le client a un bonus de 2h30 (150 minutes).
Cela signifie que sur 12 interventions, vous avez facturé 2h30 
de moins que le temps réel effectué.
```

---

### Vérifier les heures restantes d'un client

```sql
SELECT 
    fv.id,
    f.nom AS forfait_nom,
    fv.heures_totales,
    fv.heures_restantes,
    (fv.heures_totales - fv.heures_restantes) AS heures_consommees,
    fv.date_achat,
    fv.date_expiration,
    fv.statut
FROM forfaits_vendus fv
LEFT JOIN forfaits f ON fv.forfait_id = f.id
WHERE fv.client_id = ?
AND fv.statut = 'actif'
ORDER BY fv.date_expiration ASC;
```

---

### Statistiques d'arrondi par client

```sql
SELECT 
    CONCAT(c.prenom, ' ', c.nom) AS client_nom,
    COUNT(CASE WHEN hc.difference_arrondi > 0 THEN 1 END) AS nb_bonus,
    COUNT(CASE WHEN hc.difference_arrondi < 0 THEN 1 END) AS nb_malus,
    COUNT(CASE WHEN hc.difference_arrondi = 0 THEN 1 END) AS nb_exact,
    ROUND(SUM(CASE WHEN hc.difference_arrondi > 0 THEN hc.difference_arrondi ELSE 0 END) * 60, 0) AS total_bonus_minutes,
    ROUND(SUM(CASE WHEN hc.difference_arrondi < 0 THEN ABS(hc.difference_arrondi) ELSE 0 END) * 60, 0) AS total_malus_minutes
FROM historique_consommation hc
LEFT JOIN clients c ON hc.client_id = c.id
GROUP BY hc.client_id
ORDER BY client_nom;
```

---

## 🎨 Logique Visuelle (Couleurs)

### Code Couleur Bonus/Malus

```javascript
// Frontend - agenda.php

if (bonusArrondi > 0) {
    // 🟢 VERT - BONUS entreprise
    // Client ne vous doit PAS d'heures
    // Vous avez facturé MOINS que le temps réel
    couleur = "#4caf50"; // Vert
    texte = "Bonus entreprise : +" + bonusArrondi + " min";
} else if (bonusArrondi < 0) {
    // 🔴 ROUGE - MALUS entreprise
    // Client vous doit des heures
    // Vous avez facturé PLUS que le temps réel
    couleur = "#f44336"; // Rouge
    texte = "Malus entreprise : " + bonusArrondi + " min";
} else {
    // ⚪ NEUTRE
    // Temps facturé = temps réel (rare)
    couleur = "#999";
    texte = "Pas d'arrondi (durée exacte)";
}
```

**Fichier :** `agenda.php` (lignes ~2410-2420)

---

## 🔧 Points d'Attention Technique

### 1. Format des Heures

**Champs `heure_debut` et `heure_fin` :**

**Type MySQL :** `TIME`
- Format stocké : `"HH:MM:SS"` (ex: `"09:30:00"`)
- Format affiché : `"HH:MM"` (ex: `"09:30"`)

**Conversion JavaScript → MySQL :**
```javascript
// Input utilisateur : "09:30"
// Envoi API : "09:30:00" ou "09:30"
// MySQL accepte les deux formats
```

**Champ `duree_reelle` :**

**Type MySQL :** `DECIMAL(10,2)`
- Format stocké : heures décimales (ex: `1.30` pour 1h18)
- Calculé lors de la clôture : `heure_fin - heure_debut`
- Stocké dans `rendez_vous.duree_reelle` pour éviter les recalculs

---

### 2. Calcul de Durée

**Attention aux fuseaux horaires :**
```javascript
// ❌ INCORRECT - Peut causer des décalages
const debut = new Date(dateString + 'T' + heureDebut);

// ✅ CORRECT - Construction explicite locale
const debut = new Date();
debut.setHours(9, 30, 0, 0);
```

**Stockage :**
La durée est calculée en JavaScript et enregistrée dans **deux endroits** :
- `rendez_vous.duree_reelle` : durée exacte passée chez le client
- `historique_consommation.temps_reel` : même valeur pour l'historique

**Fichier :** `agenda.php` utilise la méthode correcte avec `Date` JavaScript

---

### 3. Arrondis par Tranches de 30 Minutes

**Formule JavaScript :**
```javascript
// Arrondi supérieur
Math.ceil(dureeHeures * 2) / 2

// Exemple : 2.3h → ceil(4.6) / 2 → 5 / 2 → 2.5h

// Arrondi inférieur
Math.floor(dureeHeures * 2) / 2

// Exemple : 2.3h → floor(4.6) / 2 → 4 / 2 → 2.0h
```

**Formule PHP équivalente :**
```php
// Arrondi supérieur
ceil($duree_heures * 2) / 2

// Arrondi inférieur
floor($duree_heures * 2) / 2
```

---

### 4. Précision des Décimales

**MySQL :** `DECIMAL(10,2)`
- Précision : 2 décimales
- Stockage : 1.30 (1h18), 2.50 (2h30)

**JavaScript :** `toFixed(2)`
```javascript
const duree = 1.3333333;
const dureeFormatee = duree.toFixed(2); // "1.33"
```

---

### 5. Gestion des Cas Limites

**Durée = Multiple Exact de 30 Minutes**
```
Exemple : 1h30 exactement
- Arrondi SUP : 1.50h
- Arrondi INF : 1.50h
- Bonus/Malus : 0.00h

Le modal s'affiche quand même pour permettre au technicien 
de choisir (cohérence de l'interface).
```

**Fichier :** `agenda.php` - La condition `if (differenceMinutes > 0)` a été **supprimée** pour toujours proposer le choix

---

## 📊 Schéma de Flux Complet

```
┌─────────────────────────────────────────────────────────────┐
│ 1. CRÉATION RENDEZ-VOUS                                    │
├─────────────────────────────────────────────────────────────┤
│ Frontend (agenda.php)                                       │
│ ├─ Utilisateur saisit : date, heure_debut, heure_fin       │
│ └─ Envoi API : POST /api/events.php?action=create          │
│                                                             │
│ Backend (api/events.php)                                    │
│ └─ INSERT INTO rendez_vous (date_rdv, heure_debut, ...)   │
│    → Durée NON stockée                                      │
└─────────────────────────────────────────────────────────────┘
                            ↓
┌─────────────────────────────────────────────────────────────┐
│ 2. CLÔTURE INTERVENTION                                     │
├─────────────────────────────────────────────────────────────┤
│ Frontend (agenda.php)                                       │
│ ├─ Clic bouton "✓ Clôturer"                                │
│ ├─ Fonction clotureIntervention(rdvId)                     │
│ ├─ Affichage modal saisie heures (si modifiées)            │
│ └─ Calcul durée réelle : heure_fin - heure_debut           │
│    → dureeHeures = 2.30 (2h18)                              │
└─────────────────────────────────────────────────────────────┘
                            ↓
┌─────────────────────────────────────────────────────────────┐
│ 3. PROPOSITION ARRONDI                                      │
├─────────────────────────────────────────────────────────────┤
│ Frontend (agenda.php - confirmerClotureAvecHeures)          │
│ ├─ Calcul arrondi SUP : ceil(2.30 * 2) / 2 = 2.50h        │
│ ├─ Calcul arrondi INF : floor(2.30 * 2) / 2 = 2.00h       │
│ ├─ Calcul bonus/malus SUP : 2.30 - 2.50 = -0.20h (malus)  │
│ ├─ Calcul bonus/malus INF : 2.30 - 2.00 = +0.30h (bonus)  │
│ └─ Affichage modal avec 2 cartes colorées                  │
│    ├─ 🔴 Arrondi SUP : 2h30 (malus -12 min)                │
│    └─ 🟢 Arrondi INF : 2h00 (bonus +18 min)                │
└─────────────────────────────────────────────────────────────┘
                            ↓
┌─────────────────────────────────────────────────────────────┐
│ 4. CHOIX TECHNICIEN                                         │
├─────────────────────────────────────────────────────────────┤
│ Frontend                                                     │
│ ├─ Clic "Arrondi SUP" → appliquer_arrondi = true           │
│ └─ Clic "Arrondi INF" → appliquer_arrondi = false          │
│                                                             │
│ Envoi API : POST /api/interventions.php?action=close_...   │
│ {                                                           │
│   rendez_vous_id: 123,                                      │
│   heure_debut: "09:00:00",                                  │
│   heure_fin: "11:18:00",                                    │
│   appliquer_arrondi: true  // ou false                      │
│ }                                                           │
└─────────────────────────────────────────────────────────────┘
                            ↓
┌─────────────────────────────────────────────────────────────┐
│ 5. TRAITEMENT BACKEND                                       │
├─────────────────────────────────────────────────────────────┤
│ Backend (api/interventions.php)                             │
│ ├─ Recalcul durée réelle : 2.30h                           │
│ ├─ Application arrondi selon choix :                        │
│ │  ├─ true  → ceil()  → 2.50h (arrondi SUP)               │
│ │  └─ false → floor() → 2.00h (arrondi INF)               │
│ ├─ Calcul bonus/malus : temps_réel - temps_facturé         │
│ │  → Exemple SUP : 2.30 - 2.50 = -0.20h                    │
│ └─ Vérification heures forfait disponibles                  │
└─────────────────────────────────────────────────────────────┘
                            ↓
┌─────────────────────────────────────────────────────────────┐
│ 6. ENREGISTREMENT BASE DE DONNÉES                           │
├─────────────────────────────────────────────────────────────┤
│ Backend (api/interventions.php)                             │
│                                                             │
│ A. Table historique_consommation :                          │
│    INSERT (rendez_vous_id, forfait_vendu_id, client_id,    │
│            temps_reel=2.30, temps_arrondi=2.50,            │
│            difference_arrondi=-0.20, ...)                   │
│                                                             │
│ B. Table forfaits_vendus :                                  │
│    UPDATE heures_restantes = heures_restantes - 2.50       │
│    WHERE id = forfait_id                                    │
│                                                             │
│ C. Table rendez_vous :                                      │
│    UPDATE statut = 'termine'                                │
│    WHERE id = rendez_vous_id                                │
└─────────────────────────────────────────────────────────────┘
                            ↓
┌─────────────────────────────────────────────────────────────┐
│ 7. RETOUR UTILISATEUR                                       │
├─────────────────────────────────────────────────────────────┤
│ Frontend (agenda.php - executerCloture)                     │
│ ├─ Affichage message succès                                │
│ ├─ Si malus (< 0) :                                         │
│ │  "Malus entreprise : -12 minutes"                         │
│ ├─ Si bonus (> 0) :                                         │
│ │  "Bonus entreprise : +18 minutes"                         │
│ └─ Rechargement calendrier                                  │
└─────────────────────────────────────────────────────────────┘
```

---

## 📁 Fichiers Concernés

### Frontend
- **`agenda.php`** (principal)
  - Lignes ~2372-2450 : Fonction `confirmerClotureAvecHeures()` (modal arrondi)
  - Lignes ~2550-2650 : Fonction `repondreArrondi()` (envoi choix)
  - Lignes ~2600-2650 : Fonction `executerCloture()` (affichage résultat)

### Backend
- **`api/interventions.php`**
  - Lignes ~137-300 : Fonction `handleCloseForfait()` (clôture avec forfait)
  - Lignes ~50-130 : Fonction `handleCheckHeures()` (vérification heures disponibles)
  - Lignes ~317-395 : Fonction `handleCloseHorsForfait()` (clôture hors forfait)

### Base de Données
- **`database.sql`** : Schéma complet des tables

---

## 🎯 Résumé

| Aspect | Détail |
|--------|--------|
| **Stockage durée initiale** | Table `rendez_vous` : `heure_debut` + `heure_fin` (pas de durée stockée) |
| **Calcul durée réelle** | Frontend + Backend : `heure_fin - heure_debut` en temps réel |
| **Arrondi** | Tranches de 30 minutes (0.5h) : `ceil()` ou `floor()` |
| **Choix technicien** | Modal avec 2 boutons : "Arrondi SUP" / "Arrondi INF" |
| **Formule bonus/malus** | `temps_réel - temps_facturé` |
| **Interprétation** | Positif = BONUS 🟢 / Négatif = MALUS 🔴 |
| **Stockage historique** | Table `historique_consommation` : tout est tracé |
| **Mise à jour forfait** | Table `forfaits_vendus.heures_restantes` décrémentée |

---

*Document généré le 7 décembre 2025*
*Projet : Agenda Interdo - Gestion des interventions*
