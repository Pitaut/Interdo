# CHANGES (récapitulatif des modifications)

**Dernière mise à jour** : 21 décembre 2025  
**Version actuelle** : 3.0

---

## Version 3.0 - 21 décembre 2025

### 🔴 Corrections critiques

#### 1. Calcul bonus/malus inversé
- **Fichier** : `api/interventions.php` (ligne 259)
- **Problème** : Calcul du point de vue entreprise au lieu du client
- **Solution** : `$difference_arrondi = $duree_arrondie - $duree_heures;`
- **Impact** : Client crédit +20 min au lieu de -20 min pour 1h30 acheté / 1h10 passé

#### 2. Traçabilité incomplète
- **Fichiers** : `api/interventions.php`, Migration 006
- **Problème** : Interventions hors forfait non tracées
- **Solution** : `forfait_vendu_id` accepte NULL + création systématique d'historique
- **Impact** : 100% des interventions tracées

#### 3. Bonus client non mis à jour (hors forfait)
- **Fichier** : `api/interventions.php` (lignes 302-308, 410-414)
- **Problème** : `heure_bonus` non mis à jour en clôture hors forfait
- **Solution** : Mise à jour systématique dans les deux flux
- **Impact** : Cohérence garantie des soldes clients

#### 4. Heures forfait non multiples de 30 minutes
- **Fichier** : `api/forfaits.php` (lignes 237, 350)
- **Problème** : Possibilité de créer forfaits avec 1.25h, 2.75h, etc.
- **Solution** : `round($heures * 2) / 2` à la vente et au décompte
- **Impact** : Uniquement 1h, 1h30, 2h, 2h30, etc.

#### 5. Bug navigation calendrier (lundi)
- **Fichier** : `agenda.php` (lignes 3377-3395)
- **Problème** : Clic sur lundi affichait mauvaise semaine
- **Solution** : Détection `dayOfWeek === 1` avec +1 jour
- **Impact** : Navigation fiable 7j/7

### ✨ Nouvelles fonctionnalités

#### 1. Affichage en minutes
- **Fichiers** : `agenda.php`, `gestion.php`, `statistiques.php`
- **Fonctionnalité** : Conversion 0.33h → 20 min
- **Impact** : Lisibilité améliorée

#### 2. Rappel dernier mode de règlement
- **Nouveau endpoint** : `api/forfaits.php?action=dernier_mode_reglement`
- **Fichiers** : `api/forfaits.php`, `gestion.php`
- **Impact** : Pré-sélection automatique, gain de temps

#### 3. Affichage dernier forfait acheté
- **Fichier** : `api/forfaits.php` (lignes 309-335)
- **Fonctionnalité** : Visible même si épuisé/expiré
- **Impact** : Meilleur contexte client

#### 4. Clients à risque 3 niveaux
- **Fichier** : `statistiques.php` (lignes 217-250)
- **Critères** : 0h/30j (🔴) OU <2h/60j (🟠) OU ≥2h/90j (🟡)
- **Impact** : Détection proactive

#### 5. Modal rappel avec bouton "Prendre rendez-vous"
- **Fichier** : `statistiques.php` (lignes 886-1045)
- **Fonctionnalité** : Ouverture agenda dans nouvel onglet
- **Impact** : Workflow intégré

#### 6. Badge bonus/malus sur la même ligne
- **Fichier** : `agenda.php` (lignes 1536-1565)
- **Fonctionnalité** : Interface plus compacte
- **Impact** : Lecture plus rapide

### 🔧 Améliorations techniques
- Correction colonne `c.date_creation` → `c.created_at` (statistiques.php)
- Refactoring `api/interventions.php`
- Optimisation requêtes SQL statistiques

### 📚 Documentation
- ✨ Nouveau : `README.md` (documentation principale complète)
- ✨ Nouveau : `CHANGELOG.md` (historique des versions)
- ✨ Nouveau : `GUIDE_DEPLOIEMENT.md` (guide complet déploiement)
- ✅ Mis à jour : `DOCUMENTATION_TECHNIQUE.md` v3.0
- ✅ Mis à jour : `DOCUMENTATION_DUREE_RENDEZ_VOUS.md` v3.0
- ✅ Mis à jour : `FLUX_CLOTURE.md` v3.0
- ✅ Mis à jour : `includes/API_CONSOLIDEE.md` v3.0

### 🗄️ Migrations
- **Migration 006** : `forfait_vendu_id` NULL autorisé dans `historique_consommation`

### 🧪 Tests validés
- ✅ Clôture avec forfait (perspective client)
- ✅ Clôture hors forfait (traçabilité)
- ✅ Arrondis multiples 30 minutes
- ✅ Navigation calendrier (tous jours)
- ✅ Clients à risque (3 niveaux)
- ✅ Modal rappel et bouton agenda
- ✅ Cohérence soldes clients

---

## Version 2.0 - 22 novembre 2025

Résumé des modifications réalisées dans ce dépôt `/_Interdo` :

- `add_event.php`
  - Ajout de validations côté serveur pour les requêtes JSON et formulaire :
    - longueur maximale du `titre` (255), `lieu` (255) et `description` (2000)
    - validation du format ISO pour `start` (YYYY-MM-DDTHH:MM:SS)
    - validation du `statut` contre l'enum `STATUTS_RDV`
    - contrôle `start < end` lorsque les deux sont fournis
  - Insertion désormais complète des champs `statut`, `lieu`, `description` lors de la création.

- `update_event.php`
  - Ajout de validations serveur similaires à `add_event.php` : longueurs, format ISO, `statut` autorisé et vérification que la fin est après le début.
  - Support de la mise à jour de `lieu`, `description` et `statut` via JSON.

- `agenda.php`
  - Correction d'un bug JavaScript provoquant une erreur TypeError lors de l'ouverture du modal : la référence DOM `eventStatut` a été remplacée par l'ID existant `eventStatutText`.
  - Ajout / amélioration du modal pour l'édition (champs cachés affichables, sauvegarde via `update_event.php`, suppression via `delete_event.php`).

- `scripts/test_endpoints.ps1`
  - Script PowerShell pour exécuter CREATE → READ → UPDATE → DELETE sur les endpoints locaux.
  - Corrections pour éviter des problèmes d'encodage / quoting avec PowerShell 5.1 (utilisation de guillemets doubles, suppression de texte accentué dans les messages affichés, conversion JSON explicite).

Actions réalisées et résultats

- Tests automatisés exécutés via `run_tests.bat` (qui lance `scripts/test_endpoints.ps1` avec `-ExecutionPolicy Bypass`).
  - CREATE: `add_event.php` a renvoyé `{ "status": "created", "id": "13" }` (exemple récent).
  - READ: `agenda.php?action=get_events` a renvoyé la liste d'événements (JSON).
  - UPDATE: `update_event.php` a renvoyé `{ "status": "updated" }`.
  - DELETE: `delete_event.php` a renvoyé `{ "status": "deleted" }`.
  - Le cycle CRUD s'est exécuté avec succès lors des tests locaux.

Recommandations / prochaines étapes

- Commit des modifications locales (git) avec ce message suggéré :

  "Serveur: validations pour add/update; correction modal agenda; fix tests PowerShell"

  Commandes PowerShell (à lancer depuis `C:\wamp64\www\_Interdo`) :

```powershell
git add add_event.php update_event.php agenda.php scripts/test_endpoints.ps1
git commit -m "Serveur: validations pour add/update; correction modal agenda; fix tests PowerShell"
```

- Si `git` n'est pas installé sur la machine, installez-le (https://git-scm.com/download/win) ou utilisez les instructions ci-dessus après installation.

- Optionnel : sauvegarder la sortie complète des tests dans `scripts/test_results.txt` (je peux le faire si souhaité).

Notes

- Les validations s'alignent sur le schéma SQL attendu (longueurs) et l'enum `STATUTS_RDV` défini dans `config.php`.
- Le front (FullCalendar) continue d'utiliser les formats ISO (`YYYY-MM-DD` / `YYYY-MM-DDTHH:MM:SS`). Si vous avez besoin d'accepter d'autres formats côté serveur, dites-le et j'adapterai la validation.

---

Si vous voulez, je peux :
- créer `scripts/test_results.txt` avec la sortie complète des tests;
- créer un patch/diff si vous préférez appliquer et committer manuellement;
- exécuter un test headless (Puppeteer) pour vérifier l'ouverture du modal et l'absence d'erreurs JavaScript automatiquement.
