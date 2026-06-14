# Migration vers le système de styles communs

## Date : 25 novembre 2025

## Fichiers créés

### 1. `common_styles.css` (485 lignes)
Feuille de style centralisée contenant :
- Reset CSS et styles de base
- Système de boutons (`.btn`, `.btn-primary`, `.btn-secondary`, `.btn-success`, `.btn-danger`, `.btn-warning`, `.btn-info`)
- Tables standardisées
- Formulaires et inputs
- Modals (`.modal`, `.modal-content`, `.modal-header`, `.modal-body`, `.modal-footer`)
- Badges (`.badge`, `.badge-success`, `.badge-danger`, `.badge-warning`, `.badge-info`, `.badge-secondary`)
- Grilles (`.grid-2`, `.grid-3`, `.grid-4`, `.stats-grid`)
- Sections (`.section`)
- Stat cards (`.stat-card`)
- Utilitaires (`.text-center`, `.mt-10/20`, `.mb-10/20`, `.p-10/20`)
- Responsive (breakpoints mobiles)

### 2. `includes/header.php` (95 lignes)
Header de navigation commun avec :
- Logo de l'application
- Menu de navigation avec 6 liens :
  - 📅 Agenda (violet)
  - 👨‍💼 Techniciens (vert)
  - 👥 Clients (bleu)
  - 📦 Forfaits (orange)
  - 💼 Gestion (violet)
  - 📈 Statistiques (teal)
- Design responsive

## Fichiers migrés

### ✅ `statistiques.php` (100%)
- Remplacé `style.css` par `common_styles.css`
- Ajouté `<?php include 'includes/header.php'; ?>`
- Supprimé le lien "← Retour à l'agenda" (remplacé par le header)
- Conservé les styles spécifiques à la page (subsection, mini-grid, stat-box)

### ✅ `clients.php` (100%)
- Remplacé `style.css` par `common_styles.css`
- Ajouté le header commun
- Mis à jour les boutons :
  - `btn-small btn-add` → `btn btn-success`
  - `btn-small` → `btn btn-secondary`
  - `btn-small btn-edit` → `btn btn-warning btn-small`
  - `btn-small btn-delete` → `btn btn-danger btn-small`
- Conservé `.btn-small { padding: 6px 12px; font-size: 13px; }` pour les boutons inline
- Wrappé le contenu dans `.section`

### ✅ `techniciens.php` (100%)
- Remplacé `style.css` par `common_styles.css`
- Ajouté le header commun
- Mis à jour les boutons :
  - `btn-add btn-small` → `btn btn-success`
- Mis à jour la structure du modal :
  - Ajouté `.modal-content`
  - Ajouté `.modal-header`
  - Ajouté `.modal-body`
  - Ajouté `.modal-footer`
  - Boutons du footer : `btn btn-success` et `btn btn-secondary`
- Conservé les styles spécifiques au color picker (palette)

### ✅ `forfaits.php` (100%)
- Remplacé `style.css` par `common_styles.css`
- Ajouté le header commun
- Supprimé le bouton "← Retour à l'agenda"
- Mis à jour les boutons :
  - Bouton "Nouveau forfait" : `btn btn-success`
  - Boutons inline :
    - `btn-icon btn-edit` → `btn btn-warning btn-icon`
    - `btn-icon btn-toggle` → `btn btn-info btn-icon`
    - `btn-icon btn-delete` → `btn btn-danger btn-icon`
  - Modal footer :
    - `btn-cancel` → `btn btn-secondary`
    - `btn-primary` → `btn btn-success`
- Conservé les styles spécifiques (header rouge #e53935, .btn-icon)
- Changé `.forfaits-table` → `.section`

### ✅ `gestion.php` (100%)
- Remplacé `style.css` par `common_styles.css`
- Ajouté le header commun
- Supprimé le bouton "← Retour à l'agenda"
- Changé la couleur du thème : #e53935 (rouge) → #667eea (violet)
- Mis à jour les boutons :
  - `btn-action btn-cloturer` → `btn btn-success`
  - `btn-action btn-paye` → `btn btn-primary`
- Changé `.section-card` → `.section`
- Conservé les styles spécifiques (status-badge, count-badge, montant, total-row)

### ✅ `client_dashboard.php` (100%)
- Ajouté `common_styles.css` (pas de style.css avant)
- Ajouté le header commun
- Supprimé les styles redondants (reset, container, buttons, tables, badges, modals)
- Conservé les styles spécifiques :
  - `.header` (gradient violet)
  - `.header-info` (infos client)
  - `.back-link` (lien retour inline dans le header)
- Les `.stat-card`, `.badge`, `.modal`, `.form-control`, etc. utilisent maintenant `common_styles.css`

### ✅ `agenda.php` (100%)
- Remplacé `style.css` par `common_styles.css`
- Ajouté le header commun
- Supprimé la navigation locale (Techniciens, Clients, Forfaits, Gestion, Statistiques)
- Supprimé `.header-calendar`, `.header-left`, `.logo-section`, `.menu-icon`
- Conservé les styles spécifiques à FullCalendar :
  - `.calendar-container`
  - `.modal-event-*` (modal événements personnalisée)
  - `.edit-field`
  - `.time-group`
- Déplacé l'info timezone en haut à droite avec style inline

## Classes de boutons standardisées

### Boutons principaux
- `.btn` : Base pour tous les boutons
- `.btn-primary` : Bleu (#667eea) - Actions principales
- `.btn-secondary` : Gris (#6c757d) - Actions secondaires, annuler
- `.btn-success` : Vert (#4caf50) - Créer, enregistrer, valider
- `.btn-danger` : Rouge (#dc3545) - Supprimer, actions destructives
- `.btn-warning` : Orange (#ff9800) - Modifier, éditer
- `.btn-info` : Cyan (#17a2b8) - Informations, toggles

### Modificateurs
- `.btn-small` : Petit bouton (padding: 6px 12px, font-size: 13px)
- `.btn-icon` : Bouton icône (padding: 6px 12px, font-size: 12px)

## Classes de badges standardisées

- `.badge` : Base pour tous les badges
- `.badge-success` : Vert - Statuts positifs (Actif, Payé, etc.)
- `.badge-danger` : Rouge - Statuts négatifs (Inactif, Erreur, etc.)
- `.badge-warning` : Orange - Attention, avertissement
- `.badge-info` : Bleu - Information neutre
- `.badge-secondary` : Gris - Statut neutre

## Structure de modal standardisée

```html
<div class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2 class="modal-title">Titre du modal</h2>
        </div>
        <div class="modal-body">
            <!-- Contenu du formulaire -->
        </div>
        <div class="modal-footer">
            <button class="btn btn-secondary">Annuler</button>
            <button class="btn btn-success">Enregistrer</button>
        </div>
    </div>
</div>
```

## Sections de contenu

Utiliser `.section` pour wrapper les blocs de contenu :

```html
<div class="section">
    <!-- Tableau, formulaire, ou autre contenu -->
</div>
```

## Rollback

En cas de problème, pour revenir en arrière :

1. Remplacer `<link rel="stylesheet" href="common_styles.css">` par `<link rel="stylesheet" href="style.css">`
2. Supprimer `<?php include 'includes/header.php'; ?>`
3. Restaurer les liens de navigation locaux
4. Restaurer les classes de boutons originales

## Fichiers non modifiés

- `add_event.php` (endpoint API, pas d'UI)
- `update_event.php` (endpoint API)
- `delete_event.php` (endpoint API)
- `load_events.php` (endpoint API)
- `add_client.php` (endpoint API)
- `update_client_rappel.php` (endpoint API)
- `config.php` (configuration)
- `database.sql` (schéma DB)
- Fichiers JS minifiés FullCalendar

## Notes importantes

- Le fichier `style.css` original est conservé pour référence mais n'est plus utilisé
- Toutes les pages utilisent maintenant un design cohérent
- La navigation est centralisée dans le header
- Les couleurs et espacements sont standardisés
- Le système est responsive (breakpoint mobile : 768px)

## Vérifications effectuées

✅ Aucune erreur PHP détectée
✅ Toutes les pages compilent correctement
✅ Les structures HTML sont valides
✅ Les classes CSS sont cohérentes

## Prochaines étapes recommandées

1. Tester chaque page dans le navigateur
2. Vérifier la compatibilité mobile
3. Valider les interactions JavaScript (modals, boutons)
4. Vérifier les fonctionnalités CRUD (créer, modifier, supprimer)
5. Tester FullCalendar (drag & drop, événements)
6. Éventuellement supprimer `style.css` après validation complète
