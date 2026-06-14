# 📋 Système de Gestion des Forfaits d'Heures

## Vue d'ensemble

Ce système permet de gérer les forfaits d'heures vendus aux clients avec :
- Décompte automatique lors de la clôture d'interventions
- Arrondi au 30 minutes supérieures
- Suivi des bonus/malus d'arrondis
- Historique détaillé de consommation

---

## 📁 Fichiers créés/modifiés

### Nouveaux fichiers SQL

1. **`type_forfait.sql`** - Types de forfaits disponibles (déjà existait)
   - Table avec nom, nombre_heures, prix, description

2. **`forfaits_vendus.sql`** - Forfaits vendus aux clients (déjà existait)
   - Table avec client_id, heures_total, heures_restantes, statut

3. **`historique_consommation.sql`** - Traçabilité des consommations
   - Table pour enregistrer chaque décompte d'heures
   - Champs : temps_reel, temps_arrondi, difference_arrondi

4. **`migrations/002_add_heure_bonus_to_clients.sql`**
   - Ajoute le champ `heure_bonus` à la table `clients`
   - Stocke le cumul des arrondis (positif = bonus, négatif = malus)

### Nouveaux endpoints PHP

5. **`add_type_forfait.php`**
   - Créer un nouveau type de forfait
   - POST JSON : `{nom, nombre_heures, prix, description, actif}`

6. **`vendre_forfait.php`**
   - Vendre un forfait à un client
   - POST JSON : `{client_id, type_forfait_id, intervenant_id?, date_debut?, date_fin?}`
   - Initialise heures_restantes = heures_total

7. **`close_intervention.php`** ⭐ Cœur du système
   - Clôturer une intervention avec décompte automatique
   - POST JSON : `{rendez_vous_id}`
   - Logique :
     1. Calcule temps réel (heure_fin - heure_debut)
     2. Arrondit au 30 min supérieur
     3. Décompte du forfait actif
     4. Met à jour `heure_bonus` du client
     5. Enregistre dans `historique_consommation`
     6. Marque le forfait comme 'termine' si heures_restantes = 0

8. **`load_forfaits.php`**
   - Charger les forfaits d'un client
   - GET : `?client_id=X`
   - Retourne forfaits, heures restantes, bonus

### Fichiers modifiés

9. **`update_event.php`**
   - Ajout de la clôture automatique quand statut passe à 'termine'
   - Appelle `close_intervention.php` via cURL
   - Retourne les infos de clôture dans la réponse

10. **`agenda.php`**
    - Ajout de `loadClientForfait(clientId)` en JavaScript
    - Affichage des infos de forfait dans la section "Infos contrat"
    - Mise à jour automatique lors de la sélection d'un client
    - Affiche : nom du forfait, heures restantes, bonus/malus

### Script de test

11. **`test_forfaits.ps1`**
    - Script PowerShell pour tester le système
    - Crée des types de forfaits de démonstration
    - Vend un forfait à un client
    - Instructions pour tester la clôture

---

## 🔄 Flux de fonctionnement

### 1. Configuration initiale

```powershell
# Appliquer la migration
mysql -u root agenda_interventions < migrations/002_add_heure_bonus_to_clients.sql

# Créer les tables (auto-créées au premier appel)
# - type_forfait
# - forfaits_vendus  
# - historique_consommation

# Lancer le script de test
.\test_forfaits.ps1
```

### 2. Créer un type de forfait

```powershell
Invoke-RestMethod -Uri 'http://localhost/_Interdo/add_type_forfait.php' `
  -Method Post `
  -Body (@{
    nom = "Forfait Standard 10h"
    nombre_heures = 10
    prix = 480
    description = "Forfait standard"
    actif = $true
  } | ConvertTo-Json) `
  -ContentType 'application/json'
```

### 3. Vendre un forfait

```powershell
Invoke-RestMethod -Uri 'http://localhost/_Interdo/vendre_forfait.php' `
  -Method Post `
  -Body (@{
    client_id = 1
    type_forfait_id = 2
    date_debut = "2025-11-23"
  } | ConvertTo-Json) `
  -ContentType 'application/json'
```

### 4. Clôturer une intervention

**Méthode automatique** (recommandée) :
- Dans l'agenda, modifier le rendez-vous
- Passer le statut à "Terminé"
- La clôture se fait automatiquement via `update_event.php`

**Méthode manuelle** :
```powershell
Invoke-RestMethod -Uri 'http://localhost/_Interdo/close_intervention.php' `
  -Method Post `
  -Body (@{rendez_vous_id = 10} | ConvertTo-Json) `
  -ContentType 'application/json'
```

---

## ⚙️ Logique de calcul

### Arrondi au 30 minutes supérieures

```
Temps réel : 1h15 → Arrondi : 1h30 → Différence : +15 min (0.25h)
Temps réel : 1h31 → Arrondi : 2h00 → Différence : +29 min (0.48h)
Temps réel : 2h00 → Arrondi : 2h00 → Différence : 0 min
```

### Mise à jour du heure_bonus

```sql
UPDATE clients 
SET heure_bonus = heure_bonus + difference_arrondi
WHERE id = client_id
```

- Si différence positive → bonus cumulé
- Si différence négative → malus cumulé
- Affiché en minutes dans l'interface

### Décompte du forfait

1. Cherche le forfait actif avec le plus d'heures restantes
2. Vérifie si assez d'heures disponibles
3. Si insuffisant → retourne erreur avec `besoin_nouveau_forfait: true`
4. Sinon → `heures_restantes = heures_restantes - temps_arrondi`
5. Si `heures_restantes <= 0` → statut = 'termine'

---

## 📊 Structure des données

### Table `type_forfait`
```sql
id, nom, nombre_heures, prix, description, actif, created_at, updated_at
```

### Table `forfaits_vendus`
```sql
id, client_id, type_forfait_id, heures_total, heures_restantes, 
tarif, intervenant_id, statut, date_debut, date_fin, created_at
```

### Table `historique_consommation`
```sql
id, rendez_vous_id, forfait_vendu_id, client_id,
temps_reel, temps_arrondi, difference_arrondi, heures_decomptes,
heures_avant, heures_apres, date_rdv, heure_debut, heure_fin, created_at
```

### Champ `clients.heure_bonus`
```sql
DECIMAL(10,2) DEFAULT 0.00
-- Positif : client a du bonus
-- Négatif : client a du malus
```

---

## 🎯 Affichage dans l'interface

### Section "Infos contrat" (agenda.php)

Affiche dynamiquement :
- **Nom du forfait actif** (ex: "Forfait Standard 10h")
- **Heures achetées / restantes** (ex: "10h achetées - 7.5h restantes")
- **Total heures restantes** avec code couleur :
  - Vert si > 2h
  - Orange si ≤ 2h
- **Bonus/Malus** en minutes :
  - Vert si positif (Bonus : +15 minute(s))
  - Orange si négatif (Malus : -10 minute(s))

### Mise à jour automatique

- Lors de l'ouverture d'un événement avec client
- Lors de la sélection d'un client dans le formulaire
- Après clôture automatique d'une intervention

---

## 🚨 Gestion des erreurs

### Clôture sans forfait actif

```json
{
  "error": "Aucun forfait actif trouvé pour ce client",
  "besoin_nouveau_forfait": true,
  "temps_reel": 1.5,
  "temps_arrondi": 1.5
}
```

### Heures insuffisantes

```json
{
  "error": "Heures insuffisantes dans le forfait",
  "besoin_nouveau_forfait": true,
  "heures_restantes": 0.5,
  "heures_necessaires": 2,
  "heures_manquantes": 1.5
}
```

### Intervention déjà clôturée

```json
{
  "error": "Cette intervention a déjà été clôturée"
}
```

---

## 📝 Exemples de tests

### 1. Créer et vendre un forfait
```powershell
# Créer le type
$forfait = Invoke-RestMethod -Uri 'http://localhost/_Interdo/add_type_forfait.php' `
  -Method Post -Body (@{nom="Test 5h";nombre_heures=5;prix=250} | ConvertTo-Json) `
  -ContentType 'application/json'

# Vendre au client 1
$vente = Invoke-RestMethod -Uri 'http://localhost/_Interdo/vendre_forfait.php' `
  -Method Post -Body (@{client_id=1;type_forfait_id=$forfait.id} | ConvertTo-Json) `
  -ContentType 'application/json'

Write-Host "Forfait vendu : $($vente.heures_total)h restantes"
```

### 2. Créer un RDV et le clôturer
```powershell
# 1. Créer un RDV via l'interface agenda.php
# 2. Noter l'ID (ex: 15)
# 3. Le clôturer manuellement

$cloture = Invoke-RestMethod -Uri 'http://localhost/_Interdo/close_intervention.php' `
  -Method Post -Body (@{rendez_vous_id=15} | ConvertTo-Json) `
  -ContentType 'application/json' | ConvertTo-Json -Depth 5

Write-Host $cloture
```

### 3. Vérifier les forfaits d'un client
```powershell
$forfaits = Invoke-RestMethod -Uri 'http://localhost/_Interdo/load_forfaits.php?client_id=1'
$forfaits | ConvertTo-Json -Depth 5
```

---

## ✅ Checklist de vérification

- [x] Tables créées (type_forfait, forfaits_vendus, historique_consommation)
- [x] Migration heure_bonus appliquée sur table clients
- [x] Endpoint add_type_forfait.php fonctionnel
- [x] Endpoint vendre_forfait.php fonctionnel
- [x] Endpoint close_intervention.php avec arrondi 30min
- [x] Endpoint load_forfaits.php fonctionnel
- [x] Clôture automatique dans update_event.php (statut=termine)
- [x] Affichage forfait dans agenda.php (section Infos contrat)
- [x] Gestion du heure_bonus (cumul arrondis)
- [x] Script de test test_forfaits.ps1

---

## 🔮 Améliorations futures possibles

1. **Interface de gestion des forfaits**
   - Page dédiée pour créer/modifier les types de forfaits
   - Page pour vendre des forfaits aux clients
   - Historique de consommation par client

2. **Notifications**
   - Alerter quand un forfait a moins de 2h restantes
   - Email au client quand le forfait est épuisé

3. **Rapports**
   - Graphiques de consommation par client
   - Statistiques sur les arrondis (bonus/malus moyens)
   - Revenus générés par les forfaits

4. **Gestion avancée**
   - Renouvellement automatique de forfait
   - Transfert d'heures entre forfaits
   - Forfaits avec date d'expiration

---

## 📞 Support

Pour toute question sur ce système :
- Consulter ce fichier README.md
- Examiner les exemples dans test_forfaits.ps1
- Vérifier les logs d'erreur PHP (voir config.php DEBUG_MODE)
