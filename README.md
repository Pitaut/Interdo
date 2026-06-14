# Interdo

Application web PHP/MySQL de gestion d'agenda, d'interventions techniques, de forfaits et de clients.

**Version**: 3.0  
**Stack**: PHP 8.3.14 / MySQL 9.1.0 / FullCalendar 6.1.15  
**Dépôt public**: [Pitaut/Interdo](https://github.com/Pitaut/Interdo)

## Vue d'ensemble

Interdo permet de :
- Planifier et suivre des rendez-vous clients
- Gérer les forfaits horaires avec bonus/malus
- Facturer hors forfait de façon automatisée
- Garder une traçabilité complète des interventions
- Gérer les clients et les techniciens
- Suivre des statistiques et des alertes clients à risque
- Valider les clôtures avec signature électronique

## Démarrage rapide

### Prérequis
- WAMP/XAMPP avec PHP 8.3+
- MySQL 9.1+
- Navigateur moderne (Chrome, Firefox, Edge)

### Installation

1. **Cloner le projet**
```bash
git clone <repo-url>
cd _Interdo
```

2. **Configurer la base de données**
```sql
-- Créer la base
CREATE DATABASE agenda_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- Importer le schéma
mysql -u root -p agenda_db < structure.sql
```

3. **Configurer l'application**

Éditer [config.php](config.php) :
```php
define('DB_HOST', 'localhost');
define('DB_NAME', 'agenda_db');
define('DB_USER', 'root');
define('DB_PASS', '');
define('TIMEZONE', 'Europe/Paris');
define('DEBUG_MODE', false); // true en dev uniquement
```

4. **Accéder à l'application**
```
http://localhost/_Interdo/agenda.php
```

---

## 📁 Structure du projet

```
_Interdo/
│
├── api/                          # API REST consolidée (5 contrôleurs)
│   ├── events.php               # CRUD rendez-vous
│   ├── clients.php              # CRUD clients
│   ├── techniciens.php          # CRUD techniciens
│   ├── forfaits.php             # Gestion forfaits + ventes
│   └── interventions.php        # Clôture interventions
│
├── includes/                     # Ressources partagées
│   ├── common_styles.css        # Styles communs
│   ├── style.css                # Styles FullCalendar
│   ├── header.php               # En-tête commun
│   ├── fr.global.min.js         # Locale française
│   ├── index.global.min.js      # FullCalendar 6.1.15
│   ├── signature-pad.js         # Signatures tactiles
│   └── *.md                     # Documentation API
│
├── migrations/                   # Scripts de migration SQL
│   ├── 001_*.sql
│   ├── 006_forfait_vendu_null.sql  # ⭐ Migration critique
│   └── ...
│
├── deprecated/                   # Anciens endpoints (ne pas utiliser)
│
├── scripts/                      # Scripts utilitaires
│   └── test_*.ps1               # Tests automatisés
│
├── agenda.php                    # 🎯 Page principale (calendrier)
├── clients.php                   # Gestion clients
├── techniciens.php               # Gestion techniciens
├── forfaits.php                  # Gestion types de forfaits
├── gestion.php                   # Gestion financière
├── statistiques.php              # Statistiques et rapports
├── client_dashboard.php          # Tableau de bord client
│
├── config.php                    # ⚙️ Configuration globale
├── structure.sql                 # 📊 Schéma complet de la base
├── database.sql                  # Données de démonstration
│
└── *.md                          # Documentation détaillée
```

---

## 🔑 Fonctionnalités principales

### 1. Calendrier interactif (agenda.php)

- **Vue semaine/mois** : Navigation fluide avec mini-calendrier
- **Drag & drop** : Déplacement facile des rendez-vous
- **Codes couleur** : Différenciation par technicien
- **Filtres** : Par technicien, statut, client
- **Recherche** : Recherche rapide de clients

**Nouveautés v3.0** :
- ✨ Navigation mini-calendrier corrigée (gestion du lundi)
- ✨ Affichage des heures en minutes (plus lisible)
- ✨ Badge bonus/malus sur la même ligne que le forfait
- ✨ Affichage du dernier forfait même si épuisé

### 2. Gestion des forfaits

**Création de types de forfaits** (forfaits.php) :
- Nom, durée, prix
- Arrondis automatiques aux multiples de 30 minutes
- Statut actif/inactif

**Vente de forfaits** (via gestion.php) :
- Sélection du type de forfait
- Paiement immédiat ou différé
- Rappel automatique du dernier mode de règlement ⭐
- Multiples forfaits cumulables

**Règles métier** :
- ✅ Heures forfait toujours multiples de 30 minutes
- ✅ Décompte FIFO (premier acheté, premier utilisé)
- ✅ Bonus/malus calculés du point de vue client
- ✅ Mise à jour automatique du solde client

### 3. Clôture d'interventions

**Flux de clôture** :
1. Confirmation des heures de début/fin
2. Proposition d'arrondi (supérieur = bonus client, inférieur = malus)
3. Vérification des heures disponibles
4. Signature électronique du client
5. Décompte automatique ou création facture hors forfait

**Deux modes de clôture** :

#### 🟢 Clôture avec forfait
- Décompte automatique des heures
- Historique créé dans `historique_consommation`
- Mise à jour `heure_bonus` du client
- Signature stockée en base64

#### 🔴 Clôture hors forfait
- Facturation selon règle : 1h + tranches de 30min
- Création entrée dans `facturation_hors_forfait`
- **Traçabilité garantie** : entrée aussi dans `historique_consommation` ⭐
- Signature et paiement enregistrés

**Migration critique v3.0** :
```sql
-- Permet la traçabilité hors forfait
ALTER TABLE historique_consommation 
MODIFY COLUMN forfait_vendu_id INT NULL;
```

### 4. Statistiques et clients à risque

**Statistiques disponibles** (statistiques.php) :
- Chiffre d'affaires mensuel/annuel
- Répartition par type de forfait
- Temps moyen d'intervention
- Taux d'utilisation des techniciens

**Détection clients à risque** ⭐ (Nouveau système 3 niveaux) :

| Niveau | Critère | Action |
|--------|---------|--------|
| 🔴 Critique | Forfait à 0h + pas de rappel depuis 30j | Appel urgent |
| 🟠 Attention | Moins de 2h restantes + pas de rappel depuis 60j | Proposition forfait |
| 🟡 Vigilance | 2h ou plus + pas de rappel depuis 90j | Suivi régulier |

**Nouveautés v3.0** :
- ✨ Modal de rappel intégré avec bouton "Prendre rendez-vous"
- ✨ Ouverture directe de l'agenda dans nouvel onglet
- ✨ Historique des rappels sauvegardé

### 5. Gestion des clients

**Informations stockées** :
- Identité et coordonnées
- Adresse complète avec étage et code d'entrée
- Téléphones fixe et mobile
- Solde bonus/malus (`heure_bonus`)
- Tarif horaire hors forfait (défaut: 50€)
- Option avance immédiate

**Système de rappel** :
- Rappels quotidiens/hebdomadaires/mensuels
- Notes de rappel
- Prochaine date calculée automatiquement

### 6. Signatures électroniques

**Utilisation** :
- Signature tactile (souris/doigt)
- Validation de clôture d'intervention
- Validation de vente de forfait
- Stockage en base64 PNG

**Sécurité** :
- Vérification que la signature n'est pas vide
- Horodatage automatique
- Lié au rendez-vous/forfait

---

## 🔧 API REST

### Architecture

L'API suit une architecture RESTful avec 5 contrôleurs principaux. Tous les endpoints acceptent JSON et retournent du JSON.

**Format de réponse standard** :
```json
{
  "status": "success|error|created|updated|deleted",
  "data": { ... },
  "message": "...",
  "id": 123
}
```

### Endpoints principaux

#### 📅 Événements (api/events.php)

```http
GET  /api/events.php?action=list&start=2025-11-01&end=2025-11-30
POST /api/events.php?action=create
POST /api/events.php?action=update
POST /api/events.php?action=delete
GET  /api/events.php?action=get&id=123
```

#### 👥 Clients (api/clients.php)

```http
GET  /api/clients.php?action=list
POST /api/clients.php?action=create
POST /api/clients.php?action=update
POST /api/clients.php?action=delete
POST /api/clients.php?action=update_rappel
```

#### 🔧 Techniciens (api/techniciens.php)

```http
GET  /api/techniciens.php?action=list
POST /api/techniciens.php?action=create
POST /api/techniciens.php?action=update
POST /api/techniciens.php?action=delete
```

#### 💼 Forfaits (api/forfaits.php)

```http
GET  /api/forfaits.php?action=list_types
POST /api/forfaits.php?action=create_type
POST /api/forfaits.php?action=update_type
POST /api/forfaits.php?action=delete_type
GET  /api/forfaits.php?action=list&client_id=5
POST /api/forfaits.php?action=vendre
POST /api/forfaits.php?action=marquer_paye
GET  /api/forfaits.php?action=dernier_mode_reglement&client_id=5  ⭐ Nouveau
```

#### ⚙️ Interventions (api/interventions.php)

```http
POST /api/interventions.php?action=check_heures
POST /api/interventions.php?action=close_forfait
POST /api/interventions.php?action=close_hors_forfait
```

Documentation complète : [includes/API_CONSOLIDEE.md](includes/API_CONSOLIDEE.md)

---

## 📊 Base de données

### Tables principales

#### rendez_vous
Stocke tous les rendez-vous avec leurs horaires.

**Colonnes clés** :
- `id`, `titre`, `description`
- `date_rdv`, `heure_debut`, `heure_fin`
- `duree_reelle` : Durée réelle en heures (rempli à la clôture)
- `client_id`, `id_technicien`
- `statut` : 'planifie', 'en_cours', 'termine', 'annule'
- `signature_client` : Signature base64 (après clôture)

#### clients
Informations clients et solde bonus/malus.

**Colonnes clés** :
- `id`, `nom`, `prenom`, `email`
- `telephone_fixe`, `telephone_mobile`
- `adresse`, `code_postal`, `ville`, `pays`
- `etage`, `code_entree`
- `heure_bonus` : **Solde bonus/malus en heures** ⭐
- `tarif_horaire` : Prix hors forfait (défaut: 50€)
- `avance_imme` : Avance immédiate activée
- `rappel_*` : Système de rappel

#### forfaits_vendus
Forfaits achetés par les clients.

**Colonnes clés** :
- `id`, `client_id`, `forfait_id`
- `heures_totales`, `heures_restantes` ⭐
- `date_achat`, `date_expiration`
- `statut` : 'actif', 'epuise', 'expire'
- `paye`, `date_paiement`, `mode_reglement`

#### historique_consommation ⭐
**Table critique** : traçabilité complète de toutes les interventions.

**Colonnes clés** :
- `id`, `rendez_vous_id`
- `forfait_vendu_id` : **NULL si hors forfait** (migration 006)
- `client_id`
- `temps_reel` : Durée réelle (ex: 1.30 = 1h18)
- `temps_arrondi` : Durée facturée (ex: 1.50 = 1h30)
- `difference_arrondi` : Bonus/malus du point de vue CLIENT ⭐
  - Positif = bonus client (ex: +0.33 = +20 min)
  - Négatif = malus client (ex: -0.33 = -20 min)
- `heures_decomptes`, `heures_avant`, `heures_apres`
- `date_rdv`, `heure_debut`, `heure_fin`

**Garanties** :
- ✅ Toutes les interventions sont tracées (forfait ET hors forfait)
- ✅ Calculs cohérents (perspective client)
- ✅ Historique complet pour audit

#### facturation_hors_forfait
Factures pour interventions sans forfait.

**Colonnes clés** :
- `id`, `rendez_vous_id`, `client_id`
- `duree_reelle`, `duree_facturee`
- `tarif_horaire`, `montant_total`
- `paye`, `date_paiement`, `mode_reglement`

#### type_forfait
Types de forfaits disponibles.

**Colonnes clés** :
- `id`, `nom_forfait`
- `nbr_heure_forfait` : Toujours multiple de 0.5h ⭐
- `prix`, `actif`

#### techniciens
Techniciens assignables aux interventions.

**Colonnes clés** :
- `id`, `nom`, `prenom`, `email`, `telephone`
- `couleur` : Code hex pour le calendrier
- `actif`

### Schéma complet

Voir [structure.sql](structure.sql) pour le schéma SQL complet.

---

## 🔐 Sécurité

### Mesures implémentées

1. **Requêtes préparées** : PDO avec paramètres bindés
2. **Validation des entrées** : Vérification côté serveur
3. **Échappement des sorties** : `htmlspecialchars()` dans les vues
4. **Protection CSRF** : Token dans les formulaires critiques
5. **Contrôle d'accès** : Vérification des permissions

### Configuration recommandée

**En production** :
```php
// config.php
define('DEBUG_MODE', false);
define('DISPLAY_ERRORS', false);

// Activer HTTPS
// Configurer les en-têtes de sécurité
```

**Fichier .htaccess** :
```apache
# Protection des fichiers sensibles
<FilesMatch "^(config\.php|database\.sql)$">
    Order allow,deny
    Deny from all
</FilesMatch>

# Protection du dossier API
<Directory "api">
    Options -Indexes
</Directory>
```

---

## 🧪 Tests

### Tests automatisés

Scripts PowerShell disponibles dans `scripts/` :

```powershell
# Test complet de l'API
.\scripts\test_api_consolidee.ps1

# Test des endpoints
.\scripts\test_endpoints.ps1

# Test agenda
.\scripts\test_agenda.ps1
```

### Tests manuels

1. **Créer un rendez-vous** : Vérifier création et affichage
2. **Clôturer avec forfait** : Vérifier décompte et signature
3. **Clôturer hors forfait** : Vérifier facturation
4. **Vérifier traçabilité** : Consulter `historique_consommation`
5. **Tester clients à risque** : Vérifier détection 3 niveaux

---

## 📈 Évolutions et corrections (v3.0)

### Corrections critiques

#### 1. Calcul bonus/malus inversé ✅
**Problème** : Le calcul était fait du point de vue entreprise au lieu du client.

**Correction** :
```php
// api/interventions.php ligne 259
// AVANT : $difference_arrondi = $duree_heures - $duree_arrondie;
// APRÈS :
$difference_arrondi = $duree_arrondie - $duree_heures;
```

**Impact** : 
- Ancien : Client achète 1h30 pour 1h10 passé → -20 min ❌
- Nouveau : Client achète 1h30 pour 1h10 passé → +20 min ✅

#### 2. Traçabilité incomplète ✅
**Problème** : Interventions hors forfait non tracées dans `historique_consommation`.

**Correction** :
- Migration 006 : `forfait_vendu_id` accepte NULL
- `handleCloseHorsForfait()` crée maintenant une entrée d'historique
- Mise à jour systématique du bonus client

**Impact** : Traçabilité à 100% garantie.

#### 3. Heures forfait non arrondies ✅
**Problème** : Possibilité de créer forfaits avec heures non multiples de 30 min.

**Correction** :
```php
// api/forfaits.php ligne 350
$heures_forfait = round($type['nbr_heure_forfait'] * 2) / 2;
```

**Impact** : Cohérence garantie (1h, 1h30, 2h, etc.)

#### 4. Navigation calendrier (lundi) ✅
**Problème** : Clic sur un lundi dans le mini-calendrier affichait la mauvaise semaine.

**Correction** :
```javascript
// agenda.php lignes 3377-3395
const dayOfWeek = date.getDay();
const targetDate = (dayOfWeek === 1) 
  ? new Date(date.getTime() + 24*60*60*1000) 
  : date;
window.calendar.changeView('timeGridWeek');
setTimeout(() => { window.calendar.gotoDate(targetDate); }, 50);
```

**Impact** : Navigation fiable tous les jours.

### Nouvelles fonctionnalités

#### 1. Affichage en minutes ✨
- Conversion 0.33h → 20 min
- Affichage cohérent dans toute l'interface
- Plus lisible pour les utilisateurs

#### 2. Rappel mode de règlement ✨
- Nouvel endpoint `dernier_mode_reglement`
- Pré-sélection automatique dans les modals de paiement
- Gain de temps à la saisie

#### 3. Affichage dernier forfait ✨
- Visible même si épuisé ou expiré
- Contexte client amélioré
- Facilite vente de nouveaux forfaits

#### 4. Clients à risque 3 niveaux ✨
```sql
-- Ancien : 0h uniquement
-- Nouveau : 3 niveaux de détection
WHERE (
  (fv.heures_restantes <= 0 AND c.rappel_prochaine_date < DATE_SUB(NOW(), INTERVAL 30 DAY))
  OR
  (fv.heures_restantes < 2 AND c.rappel_prochaine_date < DATE_SUB(NOW(), INTERVAL 60 DAY))
  OR
  (fv.heures_restantes >= 2 AND c.rappel_prochaine_date < DATE_SUB(NOW(), INTERVAL 90 DAY))
)
```

#### 5. Modal rappel intégré ✨
- Bouton "Prendre rendez-vous"
- Ouverture agenda dans nouvel onglet
- Sauvegarde automatique du rappel

---

## 🛠️ Maintenance

### Tâches régulières

1. **Sauvegardes quotidiennes** :
```bash
mysqldump -u root -p agenda_db > backup_$(date +%Y%m%d).sql
```

2. **Nettoyage des logs** :
```bash
# Nettoyer les logs Apache/PHP périodiquement
```

3. **Vérification des forfaits expirés** :
```sql
UPDATE forfaits_vendus 
SET statut = 'expire' 
WHERE date_expiration < CURDATE() 
AND statut = 'actif';
```

4. **Audit de traçabilité** :
```sql
-- Vérifier que tous les rendez-vous terminés ont un historique
SELECT r.id, r.titre, r.date_rdv 
FROM rendez_vous r 
LEFT JOIN historique_consommation h ON r.id = h.rendez_vous_id 
WHERE r.statut = 'termine' 
AND h.id IS NULL;
```

### Monitoring

**Indicateurs à surveiller** :
- Nombre de rendez-vous créés/jour
- Taux de clôture avec/sans forfait
- Chiffre d'affaires mensuel
- Nombre de clients à risque
- Performance des requêtes (slow query log)

---

## 📚 Documentation complémentaire

| Fichier | Description |
|---------|-------------|
| [DOCUMENTATION_TECHNIQUE.md](DOCUMENTATION_TECHNIQUE.md) | Documentation technique détaillée |
| [DOCUMENTATION_DUREE_RENDEZ_VOUS.md](DOCUMENTATION_DUREE_RENDEZ_VOUS.md) | Gestion des durées et arrondis |
| [FLUX_CLOTURE.md](FLUX_CLOTURE.md) | Flux de clôture d'intervention |
| [TRACABILITE_INTERVENTIONS.md](TRACABILITE_INTERVENTIONS.md) | Garanties de traçabilité |
| [includes/API_CONSOLIDEE.md](includes/API_CONSOLIDEE.md) | Documentation API REST |
| [includes/CHANGES.md](includes/CHANGES.md) | Journal des modifications |
| [README_SIGNATURES.md](README_SIGNATURES.md) | Système de signatures |

---

## 🤝 Contribution

### Processus

1. Créer une branche de fonctionnalité
2. Développer et tester localement
3. Mettre à jour la documentation
4. Créer une pull request

### Conventions de code

**PHP** :
- PSR-12 pour le style
- Commentaires en français
- Nommage explicite des variables
- Requêtes préparées obligatoires

**JavaScript** :
- ES6+ moderne
- Camel case pour les fonctions
- Commentaires pour logique complexe
- Gestion des erreurs avec try/catch

**SQL** :
- UPPERCASE pour les mots-clés
- Snake_case pour les colonnes
- Index sur les foreign keys
- Commentaires sur les contraintes métier

---

## 📞 Support

Pour toute question ou problème :

1. Consulter la documentation
2. Vérifier les logs (`config.php` → `DEBUG_MODE = true`)
3. Tester avec les scripts PowerShell
4. Vérifier la base de données

---

## Licence

Ce projet est distribué sous licence MIT. Voir [LICENSE](LICENSE).

---

## 🎯 Changelog

### Version 3.0 (21 décembre 2025)

**Corrections critiques** :
- ✅ Calcul bonus/malus du point de vue client
- ✅ Traçabilité complète (forfait + hors forfait)
- ✅ Heures forfait multiples de 30 minutes
- ✅ Navigation calendrier (cas du lundi)
- ✅ Mise à jour systématique du bonus client

**Nouvelles fonctionnalités** :
- ✨ Affichage en minutes (lisibilité)
- ✨ Rappel du dernier mode de règlement
- ✨ Affichage du dernier forfait acheté
- ✨ Clients à risque 3 niveaux
- ✨ Modal rappel avec bouton "Prendre rendez-vous"
- ✨ Badge bonus/malus sur la même ligne

**Améliorations techniques** :
- Migration 006 appliquée (`forfait_vendu_id NULL`)
- Refactoring `api/interventions.php`
- Optimisation requêtes SQL statistiques
- Correction colonne `created_at` dans statistiques

### Version 2.0 (30 novembre 2025)

- Architecture API consolidée (5 contrôleurs)
- Système de signatures électroniques
- Gestion des forfaits et facturation
- Interface FullCalendar complète

### Version 1.0 (Initial)

- Calendrier de base
- CRUD rendez-vous
- Gestion clients/techniciens

---

**Documentation mise à jour le 21 décembre 2025**
