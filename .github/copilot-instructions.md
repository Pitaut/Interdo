<!-- Instructions destinées aux agents IA travaillant sur ce dépôt -->
# Copilot instructions — Projet Agenda (PHP + FullCalendar)

Objectif : permettre à un agent IA d'être immédiatement productif sur ce projet PHP simple qui sert une UI FullCalendar et des endpoints CRUD.

- **Stack** : PHP (procédural), MySQL (PDO), FullCalendar (front JS minifié : `index.global.min.js`, `fr.global.min.js`), CSS (`style.css`).
- **Emplacement projet** : `c:\wamp64\www\_Interdo` — accès via `http://localhost/_Interdo/` sur WAMP.

Fichiers et flux essentiels
- `config.php` : constantes (`DB_*`, `TIMEZONE`, `DEBUG_MODE`) et la fonction `getDBConnection()` — toujours l'utiliser pour obtenir `$pdo`.
- `database.sql` : schéma `rendez_vous` + données de démonstration.
- `agenda.php` : page principale, configuration FullCalendar, et petites API internes (`?action=get_events`, `?action=get_event_details`). Côté JS, c'est le point d'entrée à modifier pour le comportement utilisateur.
- Endpoints CRUD : `add_event.php`, `update_event.php`, `delete_event.php`, `load_events.php`.

Conventions observées (à respecter)
- Entrées/Sorties JSON : les endpoints renvoient JSON et utilisent `header('Content-Type: application/json')`. Les réponses structurées attendues : `{ status: 'created', id: ... }` ou `{ error: '...' }`.
- Format date/heure : le front envoie des chaînes ISO/FullCalendar (`YYYY-MM-DD` ou `YYYY-MM-DDTHH:MM:SS`). Le backend utilise `substr()` pour séparer `date_rdv`, `heure_debut`, `heure_fin`. Ne pas changer ce format sans mettre à jour JS et PHP.
- Timezone : `TIMEZONE` (dans `config.php`) est synchronisé côté PHP et côté FullCalendar (`timeZone`). Respectez-le.
- Statuts et couleurs : enum SQL `'planifie','en_cours','termine','annule'` et couleurs dans `config.php` (`COULEURS_STATUT`).
- `$pdo` : préférez `getDBConnection()` pour obtenir `$pdo`. J'ai normalisé `load_events.php`, `update_event.php` et `delete_event.php` pour appeler `getDBConnection()` — conservez cette consistance.

Exemples d'appels (réels)
- Charger événements (FullCalendar) :
  - `GET /agenda.php?action=get_events&start=2025-11-01&end=2025-11-30`
- Créer (JSON) :
  - `POST /add_event.php`  Body JSON: `{ "title": "Rdv test", "start": "2025-11-22T09:00:00" }`
- Mettre à jour (JSON) :
  - `POST /update_event.php` Body JSON: `{ "id": 12, "start": "2025-11-22T10:00:00", "end": "2025-11-22T11:00:00", "title": "Nouveau titre" }`
- Supprimer (JSON) :
  - `POST /delete_event.php` Body JSON: `{ "id": 12 }`

Commandes rapides (PowerShell — WAMP/Windows)
```powershell
# Importer la base (exécuter depuis le client MySQL)
# mysql -u root < database.sql

# Exemple POST JSON — créer un RDV
Invoke-RestMethod -Uri 'http://localhost/_Interdo/add_event.php' -Method Post -Body (@{ title='Test'; start='2025-11-22T09:00:00' } | ConvertTo-Json) -ContentType 'application/json'

# Exemple GET — lister événements
Invoke-RestMethod -Uri "http://localhost/_Interdo/agenda.php?action=get_events&start=2025-11-01&end=2025-11-30" -Method Get
```

Gotchas et recommandations pratiques
- Vérifier `config.php` avant toute modification : si `DEBUG_MODE` est `true`, les erreurs s'affichent. En production, mettez `DEBUG_MODE` à `false`.
- Consistance `$pdo` : j'ai ajouté des appels à `getDBConnection()` dans plusieurs endpoints — gardez cette approche. Si vous modifiez un endpoint, assurez-vous que `$pdo` vient bien de `getDBConnection()`.
- Validation d'entrée : les endpoints font des vérifications minimales (titre et date requis). Conserver les messages d'erreur JSON et codes HTTP (400/500) pour rester compatible avec le front.
- JS minifié : préférez modifier `agenda.php` pour le comportement FullCalendar (le JS est intégré et appelle les endpoints). Évitez d'éditer `index.global.min.js` sauf si vous comprenez la minification/build.

Modifications récentes effectuées par l'agent
- Ajout de l'initialisation `$pdo = getDBConnection();` dans `load_events.php`, `update_event.php` et `delete_event.php` pour éviter les usages de `$pdo` non initialisés.

Questions à confirmer avec l'auteur humain
- Identifiants de dev MySQL — actuellement `DB_USER='root'` et `DB_PASS=''` dans `config.php`. Faut‑il conserver ou remplacer ?
- Voulez‑vous que je standardise `getDBConnection()` dans tous les fichiers (ex. partout où `$pdo` est utilisé) ?
- Souhaitez‑vous des scripts de test (PowerShell / curl) commençant dans le repo pour valider les endpoints automatiquement ?

Fin.
