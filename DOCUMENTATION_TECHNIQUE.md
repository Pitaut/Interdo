# Documentation Technique - Application Agenda Interdo

**Version**: 3.0  
**Date**: 21 décembre 2025  
**Auteur**: Équipe Développement  
**Framework**: PHP 8.3 / MySQL 9.1 / FullCalendar 6.1.15

---

## Table des Matières

1. [Vue d'ensemble](#1-vue-densemble)
2. [Architecture du projet](#2-architecture-du-projet)
3. [Base de données](#3-base-de-données)
4. [API REST Consolidée](#4-api-rest-consolidée)
5. [Pages Frontend](#5-pages-frontend)
6. [Fonctionnalités détaillées](#6-fonctionnalités-détaillées)
7. [Configuration et déploiement](#7-configuration-et-déploiement)
8. [Sécurité](#8-sécurité)
9. [Maintenance](#9-maintenance)

---

## 1. Vue d'ensemble

### 1.1 Objectif de l'application

Application web de gestion d'agenda et d'interventions pour techniciens, permettant :
- Planification et suivi des rendez-vous clients
- Gestion des forfaits horaires et facturation
- Suivi des consommations horaires avec système de bonus/malus
- Gestion des clients et techniciens
- Statistiques et rapports

### 1.2 Technologies utilisées

| Composant | Technologie | Version |
|-----------|-------------|---------|
| Serveur web | Apache | 2.4.62 |
| Langage backend | PHP | 8.3.14 |
| Base de données | MySQL | 9.1.0 |
| Frontend | JavaScript Vanilla | ES6+ |
| Calendrier | FullCalendar | 6.1.15 |
| Environnement dev | WAMP | Windows |

### 1.3 Caractéristiques principales

- **Architecture RESTful** : API consolidée en 5 contrôleurs
- **Interface responsive** : Compatible desktop/tablette
- **Temps réel** : Mise à jour dynamique du calendrier
- **Calculs automatiques** : Décompte horaire, arrondis, bonus
- **Multi-techniciens** : Gestion des couleurs par technicien

---

## 2. Architecture du projet

### 2.1 Structure des dossiers

```
c:\wamp64\www\_Interdo\
│
├── .github/                    # Configuration GitHub
│   └── copilot-instructions.md # Instructions pour IA
│
├── api/                        # API REST consolidée
│   ├── .htaccess              # Protection du dossier
│   ├── events.php             # Gestion des rendez-vous
│   ├── clients.php            # Gestion des clients
│   ├── techniciens.php        # Gestion des techniciens
│   ├── forfaits.php           # Gestion des forfaits
│   └── interventions.php      # Clôture d'interventions
│
├── includes/                   # Ressources partagées
│   ├── common_styles.css      # Styles communs
│   ├── style.css              # Styles FullCalendar
│   ├── fr.global.min.js       # Locale française FullCalendar
│   ├── index.global.min.js    # Bibliothèque FullCalendar
│   ├── API_CONSOLIDEE.md      # Documentation API
│   ├── CHANGES.md             # Journal des modifications
│   ├── MIGRATION_FRONTEND.md  # Guide de migration
│   ├── MIGRATION_STYLES.md    # Migration des styles
│   └── README_FORFAITS.md     # Documentation forfaits
│
├── deprecated/                 # Anciens endpoints (archivés)
│   ├── add_event.php
│   ├── update_event.php
│   └── ... (28 fichiers)
│
├── migrations/                 # Scripts de migration SQL
│   └── *.sql
│
├── scripts/                    # Scripts utilitaires
│   └── *.ps1, *.bat
│
├── config.php                  # Configuration globale
├── database.sql                # Schéma complet de la base
│
├── agenda.php                  # Page principale (calendrier)
├── clients.php                 # Gestion des clients
├── techniciens.php             # Gestion des techniciens
├── forfaits.php                # Gestion des types de forfaits
├── gestion.php                 # Gestion financière
├── statistiques.php            # Statistiques et rapports
├── client_dashboard.php        # Tableau de bord client
│
└── DOCUMENTATION_TECHNIQUE.md  # Ce document
```

### 2.2 Flux de données

```
┌─────────────┐
│  Navigateur │
└──────┬──────┘
       │ HTTP Request
       ▼
┌─────────────────┐
│  Pages PHP      │ ← agenda.php, clients.php, etc.
│  (Frontend)     │
└──────┬──────────┘
       │ fetch() / AJAX
       ▼
┌─────────────────┐
│  API REST       │ ← api/events.php, api/clients.php, etc.
│  (Backend)      │
└──────┬──────────┘
       │ PDO Prepared Statements
       ▼
┌─────────────────┐
│  MySQL DB       │ ← agenda_db
│  (Données)      │
└─────────────────┘
```

---

## 3. Base de données

### 3.1 Schéma de la base

**Base de données** : `agenda_db`  
**Charset** : `utf8mb4_unicode_ci`  
**Nombre de tables** : 7

### 3.2 Tables principales

#### 3.2.1 `rendez_vous`
Stocke tous les événements du calendrier.

| Colonne | Type | Description |
|---------|------|-------------|
| `id` | INT (PK) | Identifiant unique |
| `titre` | VARCHAR(255) | Titre du rendez-vous |
| `description` | TEXT | Description détaillée |
| `date_rdv` | DATE | Date du rendez-vous |
| `heure_debut` | TIME | Heure de début |
| `heure_fin` | TIME | Heure de fin |
| `lieu` | VARCHAR(255) | Lieu de l'intervention |
| `id_technicien` | INT | FK vers `techniciens.id` |
| `client_id` | INT | FK vers `clients.id` |
| `statut` | ENUM | 'planifie', 'en_cours', 'termine', 'annule' |
| `created_at` | TIMESTAMP | Date de création |
| `updated_at` | TIMESTAMP | Date de modification |

**Index** :
- `idx_date_rdv` sur `date_rdv`
- `idx_heure_debut` sur `heure_debut`
- `idx_id_technicien` sur `id_technicien`
- `idx_rendez_vous_client_id` sur `client_id`

#### 3.2.2 `clients`
Informations sur les clients.

| Colonne | Type | Description |
|---------|------|-------------|
| `id` | INT (PK) | Identifiant unique |
| `nom` | VARCHAR(100) | Nom de famille |
| `prenom` | VARCHAR(100) | Prénom |
| `email` | VARCHAR(100) | Email |
| `telephone_fixe` | VARCHAR(20) | Téléphone fixe |
| `telephone_mobile` | VARCHAR(20) | Téléphone mobile |
| `adresse` | TEXT | Adresse complète |
| `code_postal` | VARCHAR(20) | Code postal |
| `ville` | VARCHAR(100) | Ville |
| `pays` | VARCHAR(100) | Pays |
| `etage` | VARCHAR(20) | Étage |
| `code_entree` | VARCHAR(50) | Code d'entrée |
| `heure_bonus` | DECIMAL(10,2) | **Solde bonus/malus du point de vue CLIENT** (positif = crédit client) |
| `tarif_horaire` | DECIMAL(10,2) | Tarif horaire hors forfait (défaut: 50.00€) |
| `avance_imme` | TINYINT(1) | Avance immédiate activée (0=non, 1=oui) |
| `created_at` | TIMESTAMP | Date de création |

**Champs de rappel** :
- `rappel_actif` : TINYINT(1)
- `rappel_frequence` : ENUM('quotidien', 'hebdomadaire', 'mensuel')
- `rappel_prochaine_date` : DATE
- `rappel_notes` : TEXT

**Index** :
- `idx_avance_imme` sur `avance_imme`

#### 3.2.3 `techniciens`
Informations sur les techniciens/intervenants.

| Colonne | Type | Description |
|---------|------|-------------|
| `id` | INT (PK) | Identifiant unique |
| `nom` | VARCHAR(100) | Nom de famille |
| `prenom` | VARCHAR(100) | Prénom |
| `email` | VARCHAR(100) | Email |
| `telephone_fixe` | VARCHAR(20) | Téléphone fixe |
| `telephone_mobile` | VARCHAR(20) | Téléphone mobile |
| `adresse` | TEXT | Adresse |
| `code_postal` | VARCHAR(20) | Code postal |
| `ville` | VARCHAR(100) | Ville |
| `pays` | VARCHAR(100) | Pays |
| `date_entree` | DATE | Date d'embauche |
| `date_sortie` | DATE | Date de sortie |
| `actif` | TINYINT(1) | Actif (1) ou inactif (0) |
| `couleur` | VARCHAR(20) | Couleur pour le calendrier (hex) |
| `salaire_horaire` | DECIMAL(10,2) | Salaire horaire |
| `created_at` | TIMESTAMP | Date de création |

#### 3.2.4 `type_forfait`
Types de forfaits disponibles.

| Colonne | Type | Description |
|---------|------|-------------|
| `id` | INT (PK) | Identifiant unique |
| `type_forfait` | VARCHAR(100) | Nom du forfait |
| `prix_forfait` | DECIMAL(10,2) | Prix du forfait |
| `nbr_heure_forfait` | DECIMAL(10,2) | Nombre d'heures incluses |
| `actif` | TINYINT(1) | Disponible à la vente |
| `date_creation` | TIMESTAMP | Date de création |

**Exemples** :
- Forfait 5h : 200€
- Forfait 10h : 380€
- 30 minutes : 30€

#### 3.2.5 `forfaits_vendus`
Forfaits achetés par les clients.

| Colonne | Type | Description |
|---------|------|-------------|
| `id` | INT (PK) | Identifiant unique |
| `client_id` | INT | FK vers `clients.id` |
| `type_forfait_id` | INT | FK vers `type_forfait.id` |
| `heures_total` | DECIMAL(10,2) | Heures totales achetées |
| `heures_restantes` | DECIMAL(10,2) | Heures restantes |
| `tarif` | DECIMAL(10,2) | Prix payé |
| `date_debut` | DATE | Date de début de validité |
| `date_fin` | DATE | Date de fin de validité |
| `paye` | TINYINT(1) | Payé (1) ou non (0) |
| `date_paiement` | DATETIME | Date du paiement |
| `created_at` | TIMESTAMP | Date de création |

**Index** :
- `idx_client` sur `client_id`
- `idx_type_forfait` sur `type_forfait_id`

#### 3.2.6 `historique_consommation`
Historique des décomptes sur forfaits.

| Colonne | Type | Description |
|---------|------|-------------|
| `id` | INT (PK) | Identifiant unique |
| `rendez_vous_id` | INT | FK vers `rendez_vous.id` |
| `forfait_vendu_id` | INT | FK vers `forfaits_vendus.id` |
| `client_id` | INT | FK vers `clients.id` |
| `temps_reel` | DECIMAL(10,2) | Temps réel en heures |
| `temps_arrondi` | DECIMAL(10,2) | Temps arrondi (au 1/4h supérieur) |
| `difference_arrondi` | DECIMAL(10,2) | Différence entre arrondi et réel |
| `heures_decomptes` | DECIMAL(10,2) | Heures décomptées du forfait |
| `heures_avant` | DECIMAL(10,2) | Solde avant décompte |
| `heures_apres` | DECIMAL(10,2) | Solde après décompte |
| `date_rdv` | DATE | Date du rendez-vous |
| `heure_debut` | TIME | Heure de début |
| `heure_fin` | TIME | Heure de fin |
| `created_at` | TIMESTAMP | Date de création |

**Index** :
- `idx_forfait` sur `forfait_vendu_id`
- `idx_client` sur `client_id`
- `idx_rdv` sur `rendez_vous_id`

#### 3.2.7 `facturation_hors_forfait`
Interventions facturées hors forfait.

| Colonne | Type | Description |
|---------|------|-------------|
| `id` | INT (PK) | Identifiant unique |
| `rendez_vous_id` | INT | FK vers `rendez_vous.id` |
| `client_id` | INT | FK vers `clients.id` |
| `date_intervention` | DATE | Date de l'intervention |
| `heure_debut` | TIME | Heure de début |
| `heure_fin` | TIME | Heure de fin |
| `duree_reelle` | DECIMAL(10,2) | Durée réelle en heures |
| `quantite` | DECIMAL(10,2) | Quantité facturée (1h + multiples de 30min) |
| `tarif_horaire` | DECIMAL(10,2) | Tarif horaire appliqué |
| `montant_total` | DECIMAL(10,2) | Montant total à facturer |
| `paye` | TINYINT(1) | Payé (1) ou non (0) |
| `date_paiement` | DATETIME | Date du paiement |
| `created_at` | TIMESTAMP | Date de création |

**Index** :
- `idx_client` sur `client_id`
- `idx_rdv` sur `rendez_vous_id`
- `idx_paye` sur `paye`

### 3.3 Relations entre tables

```
clients (1) ──────< (N) rendez_vous
clients (1) ──────< (N) forfaits_vendus
clients (1) ──────< (N) historique_consommation
clients (1) ──────< (N) facturation_hors_forfait

techniciens (1) ──< (N) rendez_vous

type_forfait (1) ──< (N) forfaits_vendus

forfaits_vendus (1) ──< (N) historique_consommation

rendez_vous (1) ──── (0..1) historique_consommation
rendez_vous (1) ──── (0..1) facturation_hors_forfait
```

---

## 4. API REST Consolidée

### 4.1 Architecture API

L'API est organisée en **5 contrôleurs RESTful** dans le dossier `/api/` :

1. **events.php** - Gestion des rendez-vous
2. **clients.php** - Gestion des clients
3. **techniciens.php** - Gestion des techniciens
4. **forfaits.php** - Gestion des forfaits (types + ventes)
5. **interventions.php** - Clôture et facturation

### 4.2 Format des échanges

- **Requêtes** : JSON (Content-Type: application/json)
- **Réponses** : JSON
- **Codes HTTP** : 200 (OK), 201 (Created), 400 (Bad Request), 404 (Not Found), 500 (Error)

### 4.3 Endpoints détaillés

#### 4.3.1 API Events (`api/events.php`)

**Liste des événements**
```
GET /api/events.php?action=list
GET /api/events.php?action=list&start=2025-11-01&end=2025-11-30
GET /api/events.php?action=get_events&start=2025-11-01&end=2025-11-30
```

Paramètres :
- `start` (optionnel) : Date de début (YYYY-MM-DD)
- `end` (optionnel) : Date de fin (YYYY-MM-DD)

Réponse :
```json
[
  {
    "id": 1,
    "titre": "Intervention M. Dupont",
    "date_rdv": "2025-11-22",
    "heure_debut": "09:00:00",
    "heure_fin": "11:00:00",
    "lieu": "Paris 15e",
    "description": "Installation",
    "statut": "planifie",
    "id_technicien": 3,
    "client_id": 5,
    "tech_couleur": "#3498db",
    "tech_nom": "Martin",
    "tech_prenom": "Pierre",
    "client_prenom": "Jean",
    "client_nom": "Dupont"
  }
]
```

**Détails d'un événement**
```
GET /api/events.php?action=get&id=1
GET /api/events.php?action=get_event_details&id=1
```

Réponse :
```json
{
  "id": 1,
  "titre": "Intervention M. Dupont",
  "date_rdv": "2025-11-22",
  "heure_debut": "09:00:00",
  "heure_fin": "11:00:00",
  "lieu": "Paris 15e",
  "description": "Installation routeur",
  "statut": "planifie",
  "id_technicien": 3,
  "client_id": 5,
  "client": {
    "id": 5,
    "nom": "Dupont",
    "prenom": "Jean",
    "telephone_mobile": "0612345678",
    "adresse": "10 rue de la Paix"
  },
  "technicien": {
    "id": 3,
    "nom": "Martin",
    "prenom": "Pierre",
    "couleur": "#3498db"
  }
}
```

**Créer un événement**
```
POST /api/events.php?action=create
Content-Type: application/json

{
  "title": "Nouveau RDV",
  "start": "2025-11-25T14:00:00",
  "end": "2025-11-25T15:30:00",
  "lieu": "Lyon",
  "description": "Maintenance",
  "statut": "planifie",
  "id_technicien": 3,
  "client_id": 5
}
```

Réponse :
```json
{
  "status": "created",
  "id": 42
}
```

**Mettre à jour un événement**
```
POST /api/events.php?action=update
Content-Type: application/json

{
  "id": 42,
  "title": "RDV modifié",
  "start": "2025-11-25T15:00:00",
  "end": "2025-11-25T16:00:00",
  "statut": "termine"
}
```

Réponse :
```json
{
  "status": "updated"
}
```

**Supprimer un événement**
```
POST /api/events.php?action=delete
Content-Type: application/json

{
  "id": 42
}
```

Réponse :
```json
{
  "status": "deleted"
}
```

#### 4.3.2 API Clients (`api/clients.php`)

**Liste des clients**
```
GET /api/clients.php?action=list
GET /api/clients.php?action=list&q=dupont
```

Paramètres :
- `q` (optionnel) : Recherche par nom, prénom, email

Réponse :
```json
{
  "clients": [
    {
      "id": 5,
      "nom": "Dupont",
      "prenom": "Jean",
      "email": "jean.dupont@email.com",
      "telephone_mobile": "0612345678",
      "adresse": "10 rue de la Paix, 75015 Paris",
      "heure_bonus": -0.12,
      "tarif_horaire": 50.00,
      "display": "Jean Dupont"
    }
  ]
}
```

**Détails d'un client**
```
GET /api/clients.php?action=get&id=5
```

**Créer un client**
```
POST /api/clients.php?action=create
Content-Type: application/json

{
  "nom": "Martin",
  "prenom": "Sophie",
  "email": "sophie.martin@email.com",
  "telephone_mobile": "0698765432",
  "adresse": "20 avenue de la République",
  "code_postal": "69001",
  "ville": "Lyon",
  "tarif_horaire": 55.00
}
```

Réponse :
```json
{
  "status": "created",
  "id": 15
}
```

**Mettre à jour un client**
```
POST /api/clients.php?action=update
Content-Type: application/json

{
  "id": 15,
  "telephone_mobile": "0698765433",
  "email": "s.martin@email.com"
}
```

**Supprimer un client**
```
POST /api/clients.php?action=delete
Content-Type: application/json

{
  "id": 15
}
```

**Mettre à jour le rappel**
```
POST /api/clients.php?action=update_rappel
Content-Type: application/json

{
  "client_id": 5,
  "rappel_actif": true,
  "rappel_frequence": "mensuel",
  "rappel_prochaine_date": "2025-12-01",
  "rappel_notes": "Contacter pour maintenance annuelle"
}
```

#### 4.3.3 API Techniciens (`api/techniciens.php`)

**Liste des techniciens**
```
GET /api/techniciens.php?action=list
```

Réponse :
```json
[
  {
    "id": 3,
    "nom": "Martin",
    "prenom": "Pierre",
    "email": "p.martin@interdo.fr",
    "telephone_mobile": "0655443322",
    "couleur": "#3498db",
    "actif": 1,
    "salaire_horaire": 25.00
  }
]
```

**Créer un technicien**
```
POST /api/techniciens.php?action=create
Content-Type: application/json

{
  "nom": "Durand",
  "prenom": "Luc",
  "email": "l.durand@interdo.fr",
  "telephone_mobile": "0677889900",
  "couleur": "#e74c3c",
  "salaire_horaire": 28.00,
  "actif": 1
}
```

**Mettre à jour un technicien**
```
POST /api/techniciens.php?action=update
Content-Type: application/json

{
  "id": 3,
  "couleur": "#2ecc71",
  "salaire_horaire": 30.00
}
```

**Supprimer un technicien**
```
POST /api/techniciens.php?action=delete
Content-Type: application/json

{
  "id": 3
}
```

#### 4.3.4 API Forfaits (`api/forfaits.php`)

**Liste des types de forfaits**
```
GET /api/forfaits.php?action=list_types
GET /api/forfaits.php?action=get_types
```

Réponse :
```json
[
  {
    "id": 1,
    "type_forfait": "Forfait 5h",
    "prix_forfait": 200.00,
    "nbr_heure_forfait": 5.00,
    "actif": 1
  },
  {
    "id": 2,
    "type_forfait": "Forfait 10h",
    "prix_forfait": 380.00,
    "nbr_heure_forfait": 10.00,
    "actif": 1
  }
]
```

**Créer un type de forfait**
```
POST /api/forfaits.php?action=create_type
Content-Type: application/json

{
  "type_forfait": "Forfait 20h",
  "prix_forfait": 700.00,
  "nbr_heure_forfait": 20.00,
  "actif": 1
}
```

**Modifier un type de forfait**
```
POST /api/forfaits.php?action=update_type
Content-Type: application/json

{
  "id": 1,
  "prix_forfait": 210.00
}
```

**Activer/Désactiver un type**
```
POST /api/forfaits.php?action=toggle_type
Content-Type: application/json

{
  "id": 1
}
```

**Liste des forfaits d'un client**
```
GET /api/forfaits.php?action=list&client_id=5
```

Réponse :
```json
{
  "forfaits": [
    {
      "id": 17,
      "client_id": 5,
      "type_forfait_id": 1,
      "heures_total": 5.00,
      "heures_restantes": 4.00,
      "tarif": 200.00,
      "type_forfait_nom": "Forfait 5h",
      "type_forfait_heures": 5.00,
      "type_forfait_prix": 200.00
    }
  ],
  "forfait_actif": {
    "id": 17,
    "heures_restantes": 4.00,
    "type_forfait_nom": "Forfait 5h"
  },
  "total_heures_restantes": 4.00,
  "total_heures_consommees": 1.00,
  "heure_bonus": -0.12,
  "heure_bonus_minutes": -7
}
```

**Vendre un forfait**
```
POST /api/forfaits.php?action=vendre
Content-Type: application/json

{
  "client_id": 5,
  "type_forfait_id": 2,
  "paye": false
}
```

Réponse :
```json
{
  "status": "created",
  "forfait_vendu_id": 23
}
```

#### 4.3.5 API Interventions (`api/interventions.php`)

**Clôturer avec forfait**
```
POST /api/interventions.php?action=close_forfait
Content-Type: application/json

{
  "rendez_vous_id": 42,
  "heure_debut": "09:00:00",
  "heure_fin": "10:45:00",
  "appliquer_arrondi": true,
  "force_cloture": false
}
```

Paramètres :
- `rendez_vous_id` : ID du rendez-vous à clôturer
- `heure_debut` (optionnel) : Heure de début réelle
- `heure_fin` (optionnel) : Heure de fin réelle
- `appliquer_arrondi` (défaut: true) : Arrondir au 1/4h supérieur
- `force_cloture` (défaut: false) : Forcer même si forfait insuffisant

Logique de calcul :
1. Calcul du temps réel : `heure_fin - heure_debut`
2. Arrondi au 1/4h supérieur si `appliquer_arrondi = true`
3. Recherche du forfait actif (heures_restantes > 0)
4. Vérification du solde disponible
5. Décompte des heures sur le forfait
6. Calcul de la différence d'arrondi
7. Mise à jour du bonus/malus client
8. Création d'une entrée dans `historique_consommation`
9. Passage du rendez-vous en statut "termine"

Réponse :
```json
{
  "status": "success",
  "message": "Intervention clôturée avec succès",
  "temps_reel": 1.75,
  "temps_arrondi": 2.00,
  "heures_decomptes": 2.00,
  "difference_arrondi": 0.25,
  "forfait_restant": 2.00,
  "bonus_cumule": -0.37
}
```

**Clôturer hors forfait**
```
POST /api/interventions.php?action=close_hors_forfait
Content-Type: application/json

{
  "rendez_vous_id": 43,
  "heure_debut": "14:00:00",
  "heure_fin": "15:20:00"
}
```

Logique de calcul :
1. Calcul de la durée réelle
2. Facturation : 1ère heure pleine + tranches de 30min
3. Exemple : 1h20 → facturé 1h30 (1h + 1×30min)
4. Montant = quantité × tarif_horaire
5. Création d'une entrée dans `facturation_hors_forfait`
6. Passage du rendez-vous en statut "termine"

Réponse :
```json
{
  "status": "success",
  "message": "Intervention facturée hors forfait",
  "duree_reelle": 1.33,
  "quantite_facturee": 1.50,
  "tarif_horaire": 50.00,
  "montant_total": 75.00
}
```

### 4.4 Gestion des erreurs API

Toutes les erreurs renvoient un objet JSON avec un champ `error` :

```json
{
  "error": "Description de l'erreur"
}
```

Codes HTTP utilisés :
- **400** : Paramètres manquants ou invalides
- **404** : Ressource non trouvée
- **405** : Méthode HTTP non autorisée
- **500** : Erreur serveur

---

## 5. Pages Frontend

### 5.1 Page agenda (`agenda.php`)

**URL** : `http://localhost/_Interdo/agenda.php`

**Fonctionnalités** :
- Calendrier FullCalendar mensuel/semaine/jour
- Visualisation des rendez-vous avec couleurs par technicien
- Création de rendez-vous par clic ou glisser-déposer
- Modal de détails avec édition inline
- Recherche et sélection de client (autocomplete)
- Affichage du forfait actif du client
- Création rapide de client dans le modal
- Clôture d'intervention (forfait ou hors forfait)
- Validation obligatoire du technicien

**Bibliothèques** :
- FullCalendar 6.1.15
- Locale française

**API utilisées** :
- `GET api/events.php?action=get_events`
- `POST api/events.php?action=create`
- `POST api/events.php?action=update`
- `POST api/events.php?action=delete`
- `GET api/clients.php?action=list`
- `POST api/clients.php?action=create`
- `GET api/techniciens.php?action=list`
- `GET api/forfaits.php?action=list`
- `POST api/forfaits.php?action=vendre`
- `POST api/interventions.php?action=close_forfait`
- `POST api/interventions.php?action=close_hors_forfait`

**Validations** :
- Titre requis
- Date et heures requises
- Technicien obligatoire (nouveau)
- Vérification du forfait avant clôture

### 5.2 Page clients (`clients.php`)

**URL** : `http://localhost/_Interdo/clients.php`

**Fonctionnalités** :
- Liste de tous les clients
- Recherche par nom/prénom/email
- Création de nouveau client
- Édition des informations client
- Suppression de client
- Affichage du bonus/malus horaire
- Lien vers le tableau de bord client

**API utilisées** :
- `GET api/clients.php?action=list`
- `POST api/clients.php?action=create`
- `POST api/clients.php?action=update`
- `POST api/clients.php?action=delete`

### 5.3 Page techniciens (`techniciens.php`)

**URL** : `http://localhost/_Interdo/techniciens.php`

**Fonctionnalités** :
- Liste de tous les techniciens
- Création de nouveau technicien
- Édition des informations
- Suppression de technicien
- Sélection de couleur pour le calendrier
- Gestion du statut actif/inactif

**API utilisées** :
- `GET api/techniciens.php?action=list`
- `POST api/techniciens.php?action=create`
- `POST api/techniciens.php?action=update`
- `POST api/techniciens.php?action=delete`

### 5.4 Page forfaits (`forfaits.php`)

**URL** : `http://localhost/_Interdo/forfaits.php`

**Fonctionnalités** :
- Liste des types de forfaits
- Création de nouveau type de forfait
- Édition des types existants
- Suppression de types
- Activation/désactivation de types

**API utilisées** :
- `GET api/forfaits.php?action=list_types`
- `POST api/forfaits.php?action=create_type`
- `POST api/forfaits.php?action=update_type`
- `POST api/forfaits.php?action=delete_type`
- `POST api/forfaits.php?action=toggle_type`

### 5.5 Page gestion (`gestion.php`)

**URL** : `http://localhost/_Interdo/gestion.php`

**Fonctionnalités** :
- Liste des forfaits vendus (payés/non payés)
- Liste des interventions hors forfait (payées/non payées)
- Marquage comme payé
- Filtres par statut de paiement
- Calcul des totaux

**API utilisées** :
- Requêtes SQL directes (à migrer vers API)

### 5.6 Page statistiques (`statistiques.php`)

**URL** : `http://localhost/_Interdo/statistiques.php`

**Fonctionnalités** :
- Statistiques globales (clients, techniciens, RDV)
- Historique des consommations
- Historique des facturations hors forfait
- Filtres par période
- Export des données

**API utilisées** :
- Requêtes SQL directes (à migrer vers API)

### 5.7 Tableau de bord client (`client_dashboard.php`)

**URL** : `http://localhost/_Interdo/client_dashboard.php?id=5`

**Fonctionnalités** :
- Informations du client
- Liste des forfaits actifs
- Historique des interventions
- Solde horaire et bonus/malus
- Création de rendez-vous pour ce client

**API utilisées** :
- `GET api/clients.php?action=get`
- `GET api/forfaits.php?action=list`
- `POST api/events.php?action=create`

---

## 6. Fonctionnalités détaillées

### 6.1 Système de forfaits

#### 6.1.1 Cycle de vie d'un forfait

```
1. Création d'un type de forfait (forfaits.php)
   ↓
2. Vente du forfait à un client (agenda.php modal ou API)
   → Insertion dans forfaits_vendus
   → heures_total = nbr_heure_forfait
   → heures_restantes = heures_total
   ↓
3. Consommation lors de clôture d'intervention
   → Calcul du temps réel
   → Arrondi au 1/4h supérieur
   → Décompte sur heures_restantes
   → Enregistrement dans historique_consommation
   ↓
4. Épuisement du forfait
   → heures_restantes = 0
   → Le forfait n'est plus "actif"
```

#### 6.1.2 Règles de gestion

- Un client peut avoir **plusieurs forfaits** simultanément
- Le **forfait actif** est le premier avec `heures_restantes > 0`
- L'arrondi au **quart d'heure supérieur** est appliqué par défaut
- La **différence d'arrondi** est cumulée dans `clients.heure_bonus`
- Les bonus/malus peuvent être utilisés sur demande

### 6.2 Système de bonus/malus

#### 6.2.1 Calcul du bonus

À chaque clôture d'intervention avec forfait :

```
Temps réel = 1h 12min = 1.20h
Temps arrondi = 1h 15min = 1.25h
Différence = 1.25 - 1.20 = 0.05h (3 minutes)

→ client.heure_bonus += -0.05 (malus de 3min)
```

Si le temps réel dépasse l'arrondi (rare) :
```
Temps réel = 1h 48min = 1.80h
Temps arrondi = 2h = 2.00h
Différence = 2.00 - 1.80 = 0.20h (12 minutes)

→ client.heure_bonus += -0.20 (malus de 12min)
```

#### 6.2.2 Utilisation du bonus

Le champ `heure_bonus` est affiché dans :
- Le modal de création/édition de rendez-vous
- La page clients
- Le tableau de bord client

Le système enregistre le cumul mais **ne l'utilise pas automatiquement**.  
L'utilisation des bonus peut être gérée manuellement ou via une future fonctionnalité.

### 6.3 Facturation hors forfait

#### 6.3.1 Règle de facturation

La facturation hors forfait suit cette règle :
- **1ère heure** : facturée pleine
- **Au-delà** : par tranches de 30 minutes

Exemples :
| Durée réelle | Quantité facturée | Calcul |
|--------------|-------------------|--------|
| 0h 45min | 1h 00min | 1ère heure pleine |
| 1h 10min | 1h 30min | 1h + 1×30min |
| 1h 35min | 2h 00min | 1h + 2×30min |
| 2h 20min | 2h 30min | 1h + 3×30min |

#### 6.3.2 Calcul du montant

```
Montant = quantité_facturée × tarif_horaire_client

Exemple :
Durée réelle : 1h 35min
Quantité facturée : 2h
Tarif horaire : 50€/h
Montant total : 2 × 50 = 100€
```

### 6.4 Arrondi au quart d'heure

#### 6.4.1 Logique d'arrondi

Fonction JavaScript utilisée :
```javascript
function roundToQuarterHour(hours) {
    return Math.ceil(hours * 4) / 4;
}
```

Exemples :
| Temps réel | Arrondi | Différence |
|------------|---------|------------|
| 0h 05min (0.08h) | 0h 15min (0.25h) | 0.17h |
| 0h 22min (0.37h) | 0h 30min (0.50h) | 0.13h |
| 1h 02min (1.03h) | 1h 15min (1.25h) | 0.22h |
| 1h 15min (1.25h) | 1h 15min (1.25h) | 0.00h |
| 1h 48min (1.80h) | 2h 00min (2.00h) | 0.20h |

### 6.5 Couleurs par technicien

#### 6.5.1 Attribution

Chaque technicien a un champ `couleur` (code hexadécimal).

Exemples :
```
#3498db → Bleu
#e74c3c → Rouge
#2ecc71 → Vert
#f39c12 → Orange
#9b59b6 → Violet
```

#### 6.5.2 Utilisation dans FullCalendar

Lors de la création/modification d'un événement avec technicien :
```javascript
const tech = techniciensList.find(t => t.id === idTechnicien);
if (tech && tech.couleur) {
    event.setProp('backgroundColor', tech.couleur);
    event.setProp('borderColor', tech.couleur);
}
```

Les événements sans technicien utilisent la couleur par défaut de FullCalendar.

### 6.6 Validation du technicien

Depuis la dernière mise à jour, **un technicien est obligatoire** pour créer ou modifier un rendez-vous.

Validation côté client (JavaScript) :
```javascript
if (!idTechnicien) {
    return alert('Veuillez sélectionner un technicien');
}
```

Message d'erreur affiché si aucun technicien n'est sélectionné.

### 6.7 Système d'avance immédiate

#### 6.7.1 Fonctionnalité

Le système d'**avance immédiate** permet d'identifier visuellement les clients qui bénéficient de ce mode de paiement prioritaire.

**Champ base de données** : `clients.avance_imme` (TINYINT(1))
- `0` : Avance immédiate désactivée (par défaut)
- `1` : Avance immédiate activée

#### 6.7.2 Activation

1. Accéder à la page **Clients** (`clients.php`)
2. Cliquer sur **Modifier** pour le client concerné
3. Cocher la case **💚 Avance immédiate activée**
4. Enregistrer les modifications

#### 6.7.3 Affichage visuel

**Dans le calendrier** :
- Les événements conservent la couleur du technicien assigné
- Emoji 💚 ajouté au titre de l'événement si avance immédiate activée

**Dans le modal de détails (au clic sur l'événement)** :
- **Titre de l'événement** :
  - Fond **VERT** (#4caf50) + texte blanc si `avance_imme = 1`
  - Fond **ROUGE** (#f44336) + texte blanc si `avance_imme = 0` (et client présent)
  - Style par défaut si aucun client associé

- **Nom du client** :
  - Fond **VERT** (#4caf50) + texte blanc si `avance_imme = 1`
  - Fond **ROUGE** (#f44336) + texte blanc si `avance_imme = 0`
  - Texte "💚 Avance immédiate activée" affiché à côté du nom si activé

#### 6.7.4 Exemple visuel

```
┌─────────────────────────────────────────┐
│ [VERT] CHRISTOPHE PITAUT                │ ← Titre en vert
├─────────────────────────────────────────┤
│ 📅 vendredi 28 novembre 2025            │
│ 🕐 02:30 - 03:30                        │
│ 📍 8, rue jean Mermoz                   │
│                                         │
│ 👤 Client                               │
│    [VERT] Christophe Pitaut             │ ← Nom en vert
│           💚 Avance immédiate activée   │
│                                         │
│ 👨‍🔧 Technicien: Jean Dupont             │
└─────────────────────────────────────────┘
```

#### 6.7.5 Migration SQL

Fichier : `migrations/004_add_avance_imme.sql`

```sql
ALTER TABLE clients 
ADD COLUMN avance_imme TINYINT(1) DEFAULT 0 
COMMENT 'Avance immédiate activée (0=non, 1=oui)';

CREATE INDEX idx_avance_imme ON clients(avance_imme);
```

---

## 7. Configuration et déploiement

### 7.1 Prérequis

- **Serveur web** : Apache 2.4+
- **PHP** : 8.0+ avec extensions PDO, PDO_MySQL
- **MySQL** : 5.7+ ou MariaDB 10.3+
- **Navigateur** : Chrome, Firefox, Edge (version récente)

### 7.2 Installation

#### 7.2.1 Étape 1 : Cloner le projet

```bash
cd c:\wamp64\www
git clone <repository-url> _Interdo
```

#### 7.2.2 Étape 2 : Configuration de la base de données

1. Créer la base de données :
```sql
CREATE DATABASE agenda_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

2. Importer le schéma :
```bash
mysql -u root agenda_db < database.sql
```

3. Vérifier les tables :
```sql
USE agenda_db;
SHOW TABLES;
```

#### 7.2.3 Étape 3 : Configuration de l'application

Éditer `config.php` :

```php
define('DB_HOST', 'localhost');
define('DB_NAME', 'agenda_db');
define('DB_USER', 'root');        // Modifier en production
define('DB_PASS', '');            // Modifier en production
define('TIMEZONE', 'Europe/Paris');
define('DEBUG_MODE', true);       // false en production
```

#### 7.2.4 Étape 4 : Permissions

Sur Linux/Mac :
```bash
chmod -R 755 _Interdo/
chown -R www-data:www-data _Interdo/
```

Sur Windows (WAMP) : aucune action requise.

#### 7.2.5 Étape 5 : Vérification

Accéder à : `http://localhost/_Interdo/agenda.php`

### 7.3 Configuration Apache

#### 7.3.1 .htaccess principal (optionnel)

À la racine du projet :
```apache
RewriteEngine On
RewriteBase /_Interdo/

# Rediriger vers agenda.php par défaut
DirectoryIndex agenda.php

# Protection des fichiers sensibles
<FilesMatch "\.(sql|md)$">
    Require all denied
</FilesMatch>
```

#### 7.3.2 .htaccess API (existant)

Dans `/api/.htaccess` :
```apache
# Apache 2.4+
<FilesMatch "\.php$">
    Require all granted
</FilesMatch>
<FilesMatch "^\.">
    Require all denied
</FilesMatch>
```

### 7.4 Variables d'environnement

Pour la production, utiliser des variables d'environnement :

```php
// config.php
define('DB_HOST', getenv('DB_HOST') ?: 'localhost');
define('DB_NAME', getenv('DB_NAME') ?: 'agenda_db');
define('DB_USER', getenv('DB_USER') ?: 'root');
define('DB_PASS', getenv('DB_PASS') ?: '');
```

### 7.5 Déploiement en production

#### 7.5.1 Checklist de sécurité

- [ ] Changer les identifiants de base de données
- [ ] Mettre `DEBUG_MODE` à `false`
- [ ] Activer HTTPS
- [ ] Restreindre les accès aux fichiers `.sql` et `.md`
- [ ] Configurer les sauvegardes automatiques
- [ ] Tester toutes les fonctionnalités

#### 7.5.2 Optimisations

1. **Cache PHP** : Activer OPcache
2. **Compression** : Activer gzip dans Apache
3. **Index MySQL** : Vérifier les index (déjà en place)
4. **CDN** : Héberger FullCalendar sur CDN (optionnel)

---

## 8. Sécurité

### 8.1 Protection contre les injections SQL

Toutes les requêtes utilisent **PDO avec requêtes préparées** :

```php
$stmt = $pdo->prepare("SELECT * FROM clients WHERE id = ?");
$stmt->execute([$id]);
```

**Jamais** de concaténation directe :
```php
// ❌ DANGEREUX
$sql = "SELECT * FROM clients WHERE id = " . $_GET['id'];

// ✅ CORRECT
$stmt = $pdo->prepare("SELECT * FROM clients WHERE id = ?");
$stmt->execute([$_GET['id']]);
```

### 8.2 Protection contre les failles XSS

Échappement systématique en affichage :

```php
echo htmlspecialchars($client['nom'], ENT_QUOTES, 'UTF-8');
```

En JavaScript :
```javascript
element.textContent = value; // Échappement automatique
// Éviter innerHTML avec données utilisateur
```

### 8.3 Validation des entrées

Côté serveur, validation de tous les paramètres :

```php
$id = isset($_POST['id']) ? intval($_POST['id']) : 0;
if ($id <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'ID invalide']);
    exit;
}
```

### 8.4 Authentification (à implémenter)

**Note** : L'application actuelle **n'a pas d'authentification**.

Pour la production, implémenter :
1. Système de login/mot de passe
2. Sessions PHP sécurisées
3. Rôles utilisateur (admin, technicien, client)
4. Protection CSRF

### 8.5 HTTPS

En production, **toujours utiliser HTTPS** :

```apache
# .htaccess
RewriteEngine On
RewriteCond %{HTTPS} off
RewriteRule ^(.*)$ https://%{HTTP_HOST}%{REQUEST_URI} [L,R=301]
```

---

## 9. Maintenance

### 9.1 Sauvegarde de la base de données

#### 9.1.1 Sauvegarde manuelle

```bash
mysqldump -u root agenda_db > backup_$(date +%Y%m%d_%H%M%S).sql
```

#### 9.1.2 Sauvegarde automatique (PowerShell)

```powershell
# backup.ps1
$timestamp = Get-Date -Format "yyyyMMdd_HHmmss"
$backupFile = "C:\backups\agenda_db_$timestamp.sql"
mysqldump -u root agenda_db > $backupFile
Write-Host "Sauvegarde créée: $backupFile"
```

Planifier avec le Planificateur de tâches Windows.

#### 9.1.3 Restauration

```bash
mysql -u root agenda_db < backup_20251130_120000.sql
```

### 9.2 Nettoyage des données

#### 9.2.1 Supprimer les anciens rendez-vous

```sql
-- Supprimer les rendez-vous de plus de 2 ans
DELETE FROM rendez_vous 
WHERE date_rdv < DATE_SUB(CURDATE(), INTERVAL 2 YEAR)
AND statut IN ('termine', 'annule');
```

#### 9.2.2 Archiver l'historique

```sql
-- Créer une table d'archive
CREATE TABLE historique_consommation_archive LIKE historique_consommation;

-- Déplacer les anciennes données
INSERT INTO historique_consommation_archive
SELECT * FROM historique_consommation
WHERE created_at < DATE_SUB(CURDATE(), INTERVAL 1 YEAR);

DELETE FROM historique_consommation
WHERE created_at < DATE_SUB(CURDATE(), INTERVAL 1 YEAR);
```

### 9.3 Optimisation de la base

```sql
-- Analyser les tables
ANALYZE TABLE rendez_vous, clients, techniciens, forfaits_vendus;

-- Optimiser les tables
OPTIMIZE TABLE rendez_vous, clients, techniciens, forfaits_vendus;

-- Vérifier les index
SHOW INDEX FROM rendez_vous;
```

### 9.4 Journalisation

#### 9.4.1 Logs Apache

Emplacement : `C:\wamp64\logs\apache_error.log`

Surveiller les erreurs 500 et les requêtes suspectes.

#### 9.4.2 Logs PHP

Activer en développement dans `config.php` :
```php
if (DEBUG_MODE) {
    ini_set('log_errors', 1);
    ini_set('error_log', __DIR__ . '/logs/php_errors.log');
}
```

### 9.5 Monitoring

#### 9.5.1 Vérifications régulières

- [ ] Taille de la base de données
- [ ] Espace disque disponible
- [ ] Temps de réponse des API
- [ ] Logs d'erreur
- [ ] Sauvegardes fonctionnelles

#### 9.5.2 Script de vérification (PowerShell)

```powershell
# check_health.ps1
Write-Host "=== Vérification de l'application ===" -ForegroundColor Cyan

# Test de l'API
try {
    $result = Invoke-RestMethod -Uri "http://localhost/_Interdo/api/clients.php?action=list" -TimeoutSec 5
    Write-Host "✓ API Clients: OK" -ForegroundColor Green
} catch {
    Write-Host "✗ API Clients: ERREUR" -ForegroundColor Red
}

# Taille de la base
$dbSize = mysql -u root -e "SELECT ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) AS 'Size (MB)' FROM information_schema.TABLES WHERE table_schema = 'agenda_db';"
Write-Host "Taille de la base: $dbSize"

# Vérifier les sauvegardes
$lastBackup = Get-ChildItem -Path "C:\backups\agenda_db_*.sql" | Sort-Object LastWriteTime -Descending | Select-Object -First 1
if ($lastBackup) {
    $age = (Get-Date) - $lastBackup.LastWriteTime
    Write-Host "Dernière sauvegarde: il y a $($age.Days) jours" -ForegroundColor Yellow
} else {
    Write-Host "✗ Aucune sauvegarde trouvée" -ForegroundColor Red
}
```

### 9.6 Migrations de schéma

Les migrations SQL sont stockées dans `/migrations/` :

```
migrations/
├── 001_add_rappel_fields.sql
├── 002_add_facturation_hors_forfait.sql
└── 003_add_id_technicien.sql
```

Pour appliquer une migration :
```bash
mysql -u root agenda_db < migrations/001_add_rappel_fields.sql
```

### 9.7 Mises à jour du code

#### 9.7.1 Procédure de mise à jour

1. **Sauvegarde** : Base de données + fichiers
2. **Test local** : Tester sur environnement de dev
3. **Migration SQL** : Appliquer les modifications de schéma
4. **Déploiement** : Remplacer les fichiers
5. **Vérification** : Tester toutes les fonctionnalités
6. **Monitoring** : Surveiller les logs pendant 24h

#### 9.7.2 Rollback

En cas de problème :
1. Restaurer la sauvegarde de la base
2. Remettre les anciens fichiers
3. Vérifier le fonctionnement
4. Analyser le problème

---

## 10. Annexes

### 10.1 Liste complète des fichiers

**Fichiers principaux** :
- `agenda.php` - Page calendrier (2270 lignes)
- `clients.php` - Gestion clients (450 lignes)
- `techniciens.php` - Gestion techniciens (320 lignes)
- `forfaits.php` - Gestion forfaits (580 lignes)
- `gestion.php` - Gestion financière (720 lignes)
- `statistiques.php` - Statistiques (880 lignes)
- `client_dashboard.php` - Tableau de bord client (650 lignes)
- `config.php` - Configuration (78 lignes)

**API** :
- `api/events.php` - API Événements (326 lignes)
- `api/clients.php` - API Clients (292 lignes)
- `api/techniciens.php` - API Techniciens (208 lignes)
- `api/forfaits.php` - API Forfaits (374 lignes)
- `api/interventions.php` - API Interventions (254 lignes)

**Total code actif** : ~7 200 lignes PHP + ~1 500 lignes JavaScript

### 10.2 Glossaire

| Terme | Définition |
|-------|------------|
| **Forfait** | Package d'heures prépayées par un client |
| **Forfait actif** | Premier forfait du client avec heures_restantes > 0 |
| **Arrondi** | Arrondi au quart d'heure supérieur (0.25h) |
| **Bonus/Malus** | Cumul des différences d'arrondi (négatif = malus) |
| **Hors forfait** | Intervention facturée en dehors d'un forfait |
| **Clôture** | Finalisation d'une intervention avec décompte |
| **RDV** | Rendez-vous / Événement du calendrier |

### 10.3 Codes couleur

**Statuts des rendez-vous** :
- `planifie` : Par défaut (couleur du technicien)
- `en_cours` : Jaune/Orange
- `termine` : Vert
- `annule` : Gris

**Techniciens** (exemples) :
- Pierre Martin : `#3498db` (Bleu)
- Sophie Durand : `#e74c3c` (Rouge)
- Luc Bernard : `#2ecc71` (Vert)

### 10.4 Contacts et support

**Documentation** :
- [FullCalendar Docs](https://fullcalendar.io/docs)
- [PHP PDO](https://www.php.net/manual/fr/book.pdo.php)
- [MySQL Reference](https://dev.mysql.com/doc/)

**Dépôt** : `c:\wamp64\www\_Interdo`  
**Base de données** : `agenda_db`  
**URL locale** : `http://localhost/_Interdo/`

---

## Changelog

### Version 2.0 (30 novembre 2025)
- ✅ Consolidation de l'API (43 → 5 fichiers)
- ✅ Migration complète du frontend
- ✅ Archivage des anciens endpoints
- ✅ Réorganisation des ressources (includes/)
- ✅ Validation obligatoire du technicien
- ✅ Documentation technique complète

### Version 1.0 (Historique)
- ✅ Système de base avec FullCalendar
- ✅ Gestion clients/techniciens/forfaits
- ✅ Système de bonus/malus
- ✅ Facturation hors forfait
- ✅ Statistiques

---

**Fin de la documentation technique**

*Dernière mise à jour : 30 novembre 2025*
