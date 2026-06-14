# 📚 Index de la Documentation - Agenda Interdo

**Version** : 3.0  
**Date** : 21 décembre 2025

Ce fichier centralise toute la documentation disponible de l'application.

---

## 🎯 Pour bien démarrer

### Nouveau sur le projet ?

1. **[README.md](README.md)** 📖 - **À LIRE EN PREMIER**
   - Vue d'ensemble complète de l'application
   - Installation et démarrage rapide
   - Fonctionnalités principales
   - Structure du projet
   - Évolutions version 3.0

2. **[GUIDE_DEPLOIEMENT.md](GUIDE_DEPLOIEMENT.md)** 🚀
   - Installation depuis zéro
   - Mise à jour depuis version 2.0
   - Configuration serveur (Apache/Nginx)
   - Vérifications post-déploiement
   - Dépannage courant

3. **[CHANGELOG.md](CHANGELOG.md)** 📋
   - Historique détaillé des versions
   - Corrections critiques v3.0
   - Nouvelles fonctionnalités
   - Breaking changes et migrations

---

## 📘 Documentation technique

### Architecture et API

#### [DOCUMENTATION_TECHNIQUE.md](DOCUMENTATION_TECHNIQUE.md)
- Architecture du projet (structure, flux de données)
- Schéma complet de la base de données
- Configuration et déploiement
- Sécurité et bonnes pratiques
- **Public cible** : Développeurs, administrateurs système

#### [includes/API_CONSOLIDEE.md](includes/API_CONSOLIDEE.md)
- Architecture API REST (5 contrôleurs)
- Documentation complète de tous les endpoints
- Exemples de requêtes/réponses
- Migration des anciens endpoints
- **Public cible** : Développeurs frontend/backend

### Flux métier

#### [FLUX_CLOTURE.md](FLUX_CLOTURE.md)
- Flux complet de clôture d'intervention
- Étapes détaillées (confirmation heures → signature)
- Cas avec/sans forfait
- Gestion des arrondis et signatures
- **Public cible** : Développeurs, chefs de projet

#### [TRACABILITE_INTERVENTIONS.md](TRACABILITE_INTERVENTIONS.md)
- Garanties de traçabilité complète
- Flux forfait vs hors forfait
- Structure table `historique_consommation`
- Migration 006 (forfait_vendu_id NULL)
- **Public cible** : Développeurs, auditeurs

#### [DOCUMENTATION_DUREE_RENDEZ_VOUS.md](DOCUMENTATION_DUREE_RENDEZ_VOUS.md)
- Gestion des durées et arrondis
- Tables et champs liés aux durées
- Flux de calcul (temps réel → arrondi → bonus/malus)
- Règles métier (multiples 30 min, perspective client)
- **Public cible** : Développeurs, comptables

### Fonctionnalités spécifiques

#### [README_SIGNATURES.md](README_SIGNATURES.md) / [SIGNATURES.md](SIGNATURES.md)
- Système de signatures électroniques
- Implémentation technique
- Canvas HTML5 + signature-pad.js
- Stockage base64 en base de données
- **Public cible** : Développeurs

---

## 🔄 Historique et migrations

### [CHANGELOG.md](CHANGELOG.md)
- **Version 3.0** (21 décembre 2025) : Corrections critiques + nouvelles fonctionnalités
- **Version 2.0** (30 novembre 2025) : Architecture API consolidée
- **Version 1.0** : Version initiale

### [includes/CHANGES.md](includes/CHANGES.md)
- Récapitulatif des modifications par version
- Tests effectués et résultats
- Commandes Git pour commits

### Migrations SQL

Dossier `migrations/` :
- `001_*.sql` : Ajout client_id et colonnes clients
- `002_*.sql` : Ajout heure_bonus
- `004_*.sql` : Ajout avance_imme
- `005_*.sql` : Ajout duree_reelle
- `006_*.sql` : ⭐ **forfait_vendu_id NULL** (critique v3.0)
- `add_signature_columns.sql` : Colonnes signatures

---

## 🗂️ Documentation de migration (historique)

### [includes/MIGRATION_FRONTEND.md](includes/MIGRATION_FRONTEND.md)
- Migration vers API consolidée (v2.0)
- Changements d'endpoints
- Guides de mise à jour du frontend

### [includes/MIGRATION_STYLES.md](includes/MIGRATION_STYLES.md)
- Migration des styles CSS
- Consolidation vers `common_styles.css`

### [includes/README_FORFAITS.md](includes/README_FORFAITS.md)
- Documentation du système de forfaits
- Gestion des ventes et paiements

---

## 🔧 Fichiers de configuration

### [config.php](config.php)
**Configuration principale de l'application**

Contient :
- Constantes de connexion base de données
- Configuration timezone
- Mode debug
- Couleurs des statuts
- Fonction `getDBConnection()`

**⚠️ Sensible** : Ne pas versionner avec identifiants réels

### [structure.sql](structure.sql)
**Schéma complet de la base de données**

Tables principales :
- `rendez_vous` : Événements du calendrier
- `clients` : Informations clients
- `techniciens` : Techniciens assignables
- `forfaits_vendus` : Forfaits achetés
- `historique_consommation` : Traçabilité interventions
- `facturation_hors_forfait` : Factures hors forfait
- `type_forfait` : Types de forfaits disponibles

### [database.sql](database.sql)
**Données de démonstration**

Inclut :
- Clients exemples
- Techniciens exemples
- Types de forfaits
- Rendez-vous de test

---

## 📄 Pages principales de l'application

### Frontend (Pages PHP)

| Fichier | Description | Documentation |
|---------|-------------|---------------|
| `agenda.php` | 🎯 Page principale - Calendrier FullCalendar | README.md § Calendrier |
| `clients.php` | Gestion des clients | README.md § Gestion clients |
| `techniciens.php` | Gestion des techniciens | README.md |
| `forfaits.php` | Gestion types de forfaits | README_FORFAITS.md |
| `gestion.php` | Gestion financière (ventes, paiements) | README.md § Forfaits |
| `statistiques.php` | Statistiques et clients à risque | README.md § Statistiques |
| `client_dashboard.php` | Tableau de bord client | - |

### Backend (API REST)

| Fichier | Endpoints | Documentation |
|---------|-----------|---------------|
| `api/events.php` | CRUD rendez-vous | API_CONSOLIDEE.md |
| `api/clients.php` | CRUD clients | API_CONSOLIDEE.md |
| `api/techniciens.php` | CRUD techniciens | API_CONSOLIDEE.md |
| `api/forfaits.php` | Gestion forfaits + ventes | API_CONSOLIDEE.md |
| `api/interventions.php` | Clôture interventions | FLUX_CLOTURE.md |

---

## 🎨 Ressources frontend

### Styles CSS

| Fichier | Description |
|---------|-------------|
| `includes/common_styles.css` | Styles communs à toutes les pages |
| `includes/style.css` | Styles spécifiques FullCalendar |

### JavaScript

| Fichier | Description | Version |
|---------|-------------|---------|
| `includes/index.global.min.js` | FullCalendar core | 6.1.15 |
| `includes/fr.global.min.js` | Locale française | 6.1.15 |
| `includes/signature-pad.js` | Signatures tactiles | - |

### Composants partagés

| Fichier | Description |
|---------|-------------|
| `includes/header.php` | En-tête commun des pages |

---

## 🧪 Tests et scripts

### Scripts PowerShell (Windows)

| Script | Description |
|--------|-------------|
| `scripts/test_api_consolidee.ps1` | Test complet de l'API |
| `scripts/test_endpoints.ps1` | Test CRUD endpoints |
| `scripts/test_agenda.ps1` | Test page agenda |
| `scripts/test_techniciens.ps1` | Test gestion techniciens |

**Usage** :
```powershell
cd scripts
.\test_api_consolidee.ps1
```

### Scripts PHP

| Script | Description |
|--------|-------------|
| `scripts/show_event.php` | Affichage détails événement |
| `scripts/migrate_add_client_id.php` | Migration ajout client_id |
| `scripts/migrate_add_client_fk.php` | Migration foreign keys |

---

## 🗃️ Dépréciés (ne pas utiliser)

### Dossier `deprecated/`

**Anciens endpoints** (remplacés par API consolidée v2.0) :
- `add_event.php`, `update_event.php`, `delete_event.php`
- `add_client.php`, `update_client.php`, `delete_client.php`
- `add_technicien.php`, `update_technicien.php`, `delete_technicien.php`
- `vendre_forfait.php`, `marquer_paye.php`
- `close_intervention.php`, `close_hors_forfait.php`
- Et 28 autres fichiers...

**⚠️ Important** : Ces fichiers sont conservés pour référence historique uniquement. Utiliser les nouveaux endpoints dans `api/`.

---

## 📊 Diagrammes et schémas

### Flux de données

```
Frontend (agenda.php, clients.php, etc.)
    ↓ fetch() / AJAX
API REST (api/events.php, api/clients.php, etc.)
    ↓ PDO
Base de données MySQL (agenda_db)
```

### Flux de clôture

```
1. Confirmation heures
2. Choix arrondi
3. Vérification disponibilité
   ├─ Suffisant → 4a. Signature clôture
   └─ Insuffisant → 4b. Vente forfait puis signature
5. Décompte/Facturation
6. Mise à jour historique
```

Voir [FLUX_CLOTURE.md](FLUX_CLOTURE.md) pour détails complets.

---

## 🔍 Comment trouver l'information ?

### Je veux...

#### Installer l'application
→ **[GUIDE_DEPLOIEMENT.md](GUIDE_DEPLOIEMENT.md)** § Installation depuis zéro

#### Mettre à jour vers v3.0
→ **[GUIDE_DEPLOIEMENT.md](GUIDE_DEPLOIEMENT.md)** § Mise à jour depuis v2.0  
→ **[CHANGELOG.md](CHANGELOG.md)** pour voir les changements

#### Comprendre comment fonctionne la clôture
→ **[FLUX_CLOTURE.md](FLUX_CLOTURE.md)**

#### Développer une nouvelle fonctionnalité
→ **[DOCUMENTATION_TECHNIQUE.md](DOCUMENTATION_TECHNIQUE.md)**  
→ **[includes/API_CONSOLIDEE.md](includes/API_CONSOLIDEE.md)**

#### Comprendre les calculs de durées et bonus/malus
→ **[DOCUMENTATION_DUREE_RENDEZ_VOUS.md](DOCUMENTATION_DUREE_RENDEZ_VOUS.md)**

#### Vérifier que toutes les interventions sont tracées
→ **[TRACABILITE_INTERVENTIONS.md](TRACABILITE_INTERVENTIONS.md)**

#### Appeler un endpoint API
→ **[includes/API_CONSOLIDEE.md](includes/API_CONSOLIDEE.md)**

#### Voir l'historique des changements
→ **[CHANGELOG.md](CHANGELOG.md)**  
→ **[includes/CHANGES.md](includes/CHANGES.md)**

#### Dépanner un problème
→ **[GUIDE_DEPLOIEMENT.md](GUIDE_DEPLOIEMENT.md)** § Dépannage  
→ **[README.md](README.md)** § Support

---

## 📞 Support et contribution

### Avant de poser une question

1. ✅ Consulter [README.md](README.md)
2. ✅ Chercher dans [CHANGELOG.md](CHANGELOG.md)
3. ✅ Vérifier [GUIDE_DEPLOIEMENT.md](GUIDE_DEPLOIEMENT.md) § Dépannage
4. ✅ Activer `DEBUG_MODE` dans config.php
5. ✅ Consulter les logs Apache/PHP

### Contribution

Voir [README.md](README.md) § Contribution pour :
- Processus de contribution
- Conventions de code
- Tests requis

---

## 📅 Mises à jour de la documentation

| Date | Version | Modifications |
|------|---------|---------------|
| 21/12/2025 | 3.0 | Documentation complète v3.0 créée |
| 30/11/2025 | 2.0 | API consolidée documentée |
| - | 1.0 | Documentation initiale |

---

## 🎓 Glossaire

**Termes techniques utilisés dans la documentation** :

- **API REST** : Interface de programmation permettant aux pages frontend de communiquer avec la base de données
- **CRUD** : Create, Read, Update, Delete (opérations de base)
- **Endpoint** : Point d'entrée d'une API (ex: `api/events.php?action=list`)
- **FullCalendar** : Bibliothèque JavaScript pour afficher un calendrier interactif
- **PDO** : PHP Data Objects, méthode sécurisée pour accéder à MySQL
- **FIFO** : First In, First Out (premier entré, premier sorti) - ordre de décompte des forfaits
- **Base64** : Format d'encodage pour stocker les signatures en texte
- **Migration** : Script SQL pour modifier la structure de la base de données

**Termes métier** :

- **Forfait** : Ensemble d'heures achetées à l'avance par un client
- **Intervention** : Rendez-vous technique chez un client
- **Clôture** : Action de terminer une intervention et décompter les heures
- **Bonus/Malus** : Différence entre temps facturé et temps réel (perspective client)
- **Hors forfait** : Intervention facturée à l'heure sans utiliser de forfait
- **Client à risque** : Client avec forfait épuisé ou peu d'heures restantes
- **Arrondi** : Ajustement du temps facturé (supérieur ou inférieur)
- **Traçabilité** : Historique complet de toutes les interventions

---

**Index de documentation - Version 3.0**  
**Dernière mise à jour** : 21 décembre 2025

Pour toute suggestion d'amélioration de cette documentation, consulter [README.md](README.md) § Contribution.
