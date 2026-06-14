# 🚀 Guide de Déploiement - Version 3.0

**Application** : Agenda Interdo  
**Version** : 3.0  
**Date** : 21 décembre 2025

---

## 📋 Pré-requis

### Serveur

- **OS** : Linux (Ubuntu 20.04+ recommandé) ou Windows Server
- **Serveur Web** : Apache 2.4+ ou Nginx 1.18+
- **PHP** : 8.3.0 ou supérieur
- **MySQL** : 9.0+ ou MariaDB 10.6+
- **Extensions PHP requises** :
  - `pdo_mysql`
  - `json`
  - `mbstring`
  - `gd` (pour signatures)

### Vérification

```bash
# Vérifier version PHP
php -v

# Vérifier extensions
php -m | grep -E "pdo_mysql|json|mbstring|gd"

# Vérifier MySQL
mysql --version
```

---

## 📥 Installation depuis zéro

### Étape 1 : Cloner le projet

```bash
cd /var/www/html  # Ou C:\wamp64\www sur Windows
git clone <repository-url> _Interdo
cd _Interdo
```

### Étape 2 : Configuration des permissions (Linux)

```bash
# Donner les bonnes permissions
sudo chown -R www-data:www-data /var/www/html/_Interdo
sudo chmod -R 755 /var/www/html/_Interdo

# Permissions spéciales pour les fichiers de config
sudo chmod 640 config.php
```

### Étape 3 : Créer la base de données

```bash
# Se connecter à MySQL
mysql -u root -p

# Dans MySQL
CREATE DATABASE agenda_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'agenda_user'@'localhost' IDENTIFIED BY 'mot_de_passe_fort';
GRANT ALL PRIVILEGES ON agenda_db.* TO 'agenda_user'@'localhost';
FLUSH PRIVILEGES;
EXIT;
```

### Étape 4 : Importer le schéma

```bash
# Importer la structure
mysql -u agenda_user -p agenda_db < structure.sql

# Optionnel : Importer les données de démo
mysql -u agenda_user -p agenda_db < database.sql
```

### ⚠️ Cas fréquent en production : erreur `DEFINER` sur une vue

Si votre dump contient une vue avec `DEFINER=\`root\`@\`localhost\`` (ex: `v_rentabilite_interventions`), l'import peut échouer sur un hébergement mutualisé.

**Symptômes** :
- Erreur SQL lors de l'import de la vue
- `rentabilite.php` / API rentabilité retourne `Table '...v_rentabilite_interventions' doesn't exist`

**Correction rapide après import** :

```bash
mysql -u agenda_user -p agenda_db < migrations/011_fix_view_without_definer.sql
```

Cette migration recrée la vue sans `DEFINER`, compatible avec l'utilisateur SQL courant.

### Étape 5 : Configurer l'application

```bash
# Copier le fichier de config exemple (si existe)
cp config.example.php config.php

# Éditer la configuration
nano config.php
```

**Contenu de config.php** :

```php
<?php
// Configuration Base de Données
define('DB_HOST', 'localhost');
define('DB_NAME', 'agenda_db');
define('DB_USER', 'agenda_user');
define('DB_PASS', 'mot_de_passe_fort');

// Configuration Application
define('TIMEZONE', 'Europe/Paris');
define('DEBUG_MODE', false);  // ⚠️ false en production
date_default_timezone_set(TIMEZONE);

// Couleurs des statuts
define('COULEURS_STATUT', [
    'planifie' => '#3788d8',
    'en_cours' => '#f59e0b',
    'termine' => '#10b981',
    'annule' => '#ef4444'
]);

// Liste des statuts autorisés
define('STATUTS_RDV', ['planifie', 'en_cours', 'termine', 'annule']);

// Configuration erreurs
if (DEBUG_MODE) {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
} else {
    error_reporting(0);
    ini_set('display_errors', 0);
}

// Fonction de connexion PDO
function getDBConnection() {
    try {
        $pdo = new PDO(
            'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
            DB_USER,
            DB_PASS,
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false
            ]
        );
        return $pdo;
    } catch (PDOException $e) {
        if (DEBUG_MODE) {
            die('Erreur de connexion : ' . $e->getMessage());
        } else {
            die('Erreur de connexion à la base de données.');
        }
    }
}
?>
```

### Étape 6 : Appliquer les migrations

```bash
# Migration critique pour version 3.0
mysql -u agenda_user -p agenda_db < migrations/006_forfait_vendu_null.sql

# Vérifier toutes les migrations
for f in migrations/*.sql; do
    echo "Application de $f..."
    mysql -u agenda_user -p agenda_db < "$f"
done
```

### Étape 7 : Configuration Apache

**Créer un VirtualHost** (optionnel mais recommandé) :

```apache
<VirtualHost *:80>
    ServerName agenda.votredomaine.com
    DocumentRoot /var/www/html/_Interdo
    
    <Directory /var/www/html/_Interdo>
        Options -Indexes +FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>
    
    # Protection fichiers sensibles
    <FilesMatch "^(config\.php|database\.sql|structure\.sql)$">
        Require all denied
    </FilesMatch>
    
    # Logs
    ErrorLog ${APACHE_LOG_DIR}/agenda_error.log
    CustomLog ${APACHE_LOG_DIR}/agenda_access.log combined
</VirtualHost>
```

**Activer et redémarrer** :

```bash
sudo a2ensite agenda.votredomaine.com
sudo systemctl restart apache2
```

### Étape 8 : Configuration SSL (Production)

```bash
# Installer Certbot
sudo apt install certbot python3-certbot-apache

# Obtenir certificat
sudo certbot --apache -d agenda.votredomaine.com

# Vérifier renouvellement auto
sudo certbot renew --dry-run
```

### Étape 9 : Test de l'installation

```bash
# Tester la connexion
curl http://localhost/_Interdo/agenda.php

# Tester l'API
curl http://localhost/_Interdo/api/events.php?action=list
```

**Dans le navigateur** :
1. Accéder à `http://localhost/_Interdo/agenda.php`
2. Vérifier que le calendrier s'affiche
3. Créer un rendez-vous test
4. Vérifier les logs Apache si erreur

---

## 🔄 Mise à jour depuis version 2.0

### Étape 1 : Sauvegarde complète

```bash
# Sauvegarder la base de données
mysqldump -u agenda_user -p agenda_db > backup_v2_$(date +%Y%m%d_%H%M%S).sql

# Sauvegarder les fichiers
tar -czf backup_files_v2_$(date +%Y%m%d_%H%M%S).tar.gz /var/www/html/_Interdo
```

### Étape 2 : Mode maintenance (optionnel)

```bash
# Créer un fichier de maintenance
echo "Site en maintenance, retour dans quelques minutes" > maintenance.html

# Rediriger tout le trafic
# Dans .htaccess :
```

```apache
RewriteEngine On
RewriteCond %{REQUEST_URI} !^/maintenance\.html$
RewriteRule ^(.*)$ /maintenance.html [R=503,L]
```

### Étape 3 : Récupérer les nouvelles versions

```bash
cd /var/www/html/_Interdo
git fetch origin
git checkout v3.0
# OU
git pull origin main
```

### Étape 4 : Appliquer la migration critique

```bash
# Migration 006 (OBLIGATOIRE pour v3.0)
mysql -u agenda_user -p agenda_db < migrations/006_forfait_vendu_null.sql
```

**Vérification** :

```sql
-- Vérifier que forfait_vendu_id accepte NULL
DESCRIBE historique_consommation;
-- Résultat attendu : forfait_vendu_id | int | YES
```

### Étape 5 : Vérifier config.php

```bash
# Comparer avec la nouvelle version
diff config.php config.php.new
```

**Nouvelles constantes en v3.0** : Aucune nouvelle constante requise.

### Étape 6 : Correction des données (si nécessaire)

Si vous aviez des clients avec bonus/malus inversés :

```sql
-- ⚠️ ATTENTION : Sauvegarder avant !
-- Cette requête inverse tous les bonus/malus existants

UPDATE clients 
SET heure_bonus = -heure_bonus
WHERE heure_bonus != 0;

-- Vérifier le résultat
SELECT id, nom, prenom, heure_bonus FROM clients WHERE heure_bonus != 0;
```

**Alternative plus sûre** : Recalculer depuis l'historique

```sql
-- Créer une table temporaire avec les bons calculs
CREATE TEMPORARY TABLE temp_bonus AS
SELECT 
    client_id,
    SUM(difference_arrondi) as bonus_correct
FROM historique_consommation
GROUP BY client_id;

-- Mettre à jour
UPDATE clients c
INNER JOIN temp_bonus t ON c.id = t.client_id
SET c.heure_bonus = t.bonus_correct;
```

### Étape 7 : Supprimer les fichiers obsolètes

```bash
# Supprimer les fichiers de test (si présents)
rm -f check_*.php test_*.* verif_*.php analyser_*.php
rm -f corriger_*.php fix_*.php recalcul*.php synchroniser_*.php
rm -f create_missing_historique.php
```

### Étape 8 : Tester la mise à jour

```bash
# Lancer les tests
cd scripts
./test_api_consolidee.ps1  # Windows
# OU
php test_api.php  # Si script PHP créé
```

**Tests manuels essentiels** :
1. ✅ Créer un rendez-vous
2. ✅ Clôturer avec forfait
3. ✅ Clôturer hors forfait
4. ✅ Vérifier historique complet
5. ✅ Vérifier clients à risque (3 niveaux)
6. ✅ Tester navigation calendrier (surtout lundi)
7. ✅ Vérifier affichage en minutes

### Étape 9 : Désactiver maintenance

```bash
# Supprimer le fichier de maintenance
rm maintenance.html

# Retirer les règles .htaccess de maintenance
```

### Étape 10 : Monitoring post-déploiement

```bash
# Surveiller les logs pendant 1h
tail -f /var/log/apache2/agenda_error.log

# Vérifier les requêtes lentes
mysql -u agenda_user -p -e "SHOW FULL PROCESSLIST;"
```

---

## 🔍 Vérifications post-déploiement

### Checklist complète

```bash
# 1. Connexion base de données
php -r "require 'config.php'; $pdo = getDBConnection(); echo 'OK';"

# 2. Permissions fichiers
ls -la config.php
# Doit afficher : -rw-r----- (640)

# 3. Extensions PHP
php -m | grep -E "pdo_mysql|json|mbstring|gd"

# 4. Migration 006 appliquée
mysql -u agenda_user -p agenda_db -e "DESCRIBE historique_consommation;" | grep forfait_vendu_id

# 5. Traçabilité complète
mysql -u agenda_user -p agenda_db -e "
SELECT COUNT(*) as non_traces 
FROM rendez_vous 
WHERE statut = 'termine' 
AND id NOT IN (SELECT rendez_vous_id FROM historique_consommation);"
# Résultat attendu : 0

# 6. Cohérence bonus clients
mysql -u agenda_user -p agenda_db -e "
SELECT c.id, c.nom, c.heure_bonus, SUM(h.difference_arrondi) as calcule
FROM clients c
LEFT JOIN historique_consommation h ON c.id = h.client_id
GROUP BY c.id
HAVING ABS(c.heure_bonus - IFNULL(calcule, 0)) > 0.01;"
# Résultat attendu : 0 lignes
```

### Tests fonctionnels

| Test | URL | Résultat attendu |
|------|-----|------------------|
| Page agenda | /agenda.php | Calendrier affiché |
| API events | /api/events.php?action=list | JSON avec événements |
| API clients | /api/clients.php?action=list | JSON avec clients |
| Statistiques | /statistiques.php | Page avec clients à risque |
| Gestion | /gestion.php | Page forfaits/paiements |

### Performance

```sql
-- Vérifier les index critiques
SHOW INDEX FROM rendez_vous;
SHOW INDEX FROM historique_consommation;
SHOW INDEX FROM forfaits_vendus;

-- Analyser requête statistiques
EXPLAIN SELECT ...  -- Copier requête depuis statistiques.php
```

---

## 🔧 Dépannage

### Problème : "Erreur de connexion à la base"

**Diagnostic** :
```bash
mysql -u agenda_user -p -e "SELECT 1;"
```

**Solutions** :
1. Vérifier identifiants dans `config.php`
2. Vérifier permissions MySQL
3. Vérifier que MySQL est démarré : `systemctl status mysql`

### Problème : "forfait_vendu_id cannot be NULL"

**Diagnostic** :
```sql
DESCRIBE historique_consommation;
-- Vérifier si NULL est autorisé
```

**Solution** :
```bash
mysql -u agenda_user -p agenda_db < migrations/006_forfait_vendu_null.sql
```

### Problème : Bonus/malus incohérents

**Diagnostic** :
```sql
SELECT c.id, c.nom, c.heure_bonus, 
       SUM(h.difference_arrondi) as devrait_etre
FROM clients c
LEFT JOIN historique_consommation h ON c.id = h.client_id
GROUP BY c.id;
```

**Solution** : Recalculer (voir Étape 6 de la mise à jour)

### Problème : Calendrier ne s'affiche pas

**Diagnostic** :
1. Ouvrir console navigateur (F12)
2. Vérifier erreurs JavaScript
3. Vérifier chargement FullCalendar

**Solutions** :
```bash
# Vérifier que les fichiers JS existent
ls -la includes/index.global.min.js
ls -la includes/fr.global.min.js

# Vérifier permissions
chmod 644 includes/*.js
```

### Problème : Erreur 500 Internal Server Error

**Diagnostic** :
```bash
# Activer le mode debug temporairement
nano config.php
# Changer DEBUG_MODE à true

# Consulter les logs
tail -50 /var/log/apache2/error.log
```

---

## 📊 Monitoring production

### Logs à surveiller

```bash
# Logs Apache
tail -f /var/log/apache2/agenda_error.log
tail -f /var/log/apache2/agenda_access.log

# Logs MySQL (requêtes lentes)
tail -f /var/log/mysql/slow-query.log
```

### Configuration slow query log

```sql
-- Dans MySQL
SET GLOBAL slow_query_log = 'ON';
SET GLOBAL long_query_time = 2;  -- 2 secondes
SET GLOBAL slow_query_log_file = '/var/log/mysql/slow-query.log';
```

### Métriques importantes

```sql
-- Nombre de rendez-vous par jour
SELECT DATE(created_at) as date, COUNT(*) as nb_rdv
FROM rendez_vous
WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
GROUP BY DATE(created_at)
ORDER BY date DESC;

-- Taux de clôture
SELECT 
    COUNT(*) as total,
    SUM(CASE WHEN statut = 'termine' THEN 1 ELSE 0 END) as termines,
    ROUND(SUM(CASE WHEN statut = 'termine' THEN 1 ELSE 0 END) * 100 / COUNT(*), 2) as taux_cloture
FROM rendez_vous
WHERE date_rdv >= DATE_SUB(CURDATE(), INTERVAL 30 DAY);

-- Forfaits vs hors forfait
SELECT 'Forfait' as type, COUNT(*) as nb
FROM historique_consommation
WHERE forfait_vendu_id IS NOT NULL
UNION ALL
SELECT 'Hors forfait', COUNT(*)
FROM historique_consommation
WHERE forfait_vendu_id IS NULL;
```

---

## 🔐 Sécurité production

### Checklist sécurité

- [ ] `DEBUG_MODE = false` dans config.php
- [ ] Permissions fichiers correctes (640 pour config.php)
- [ ] SSL/HTTPS activé
- [ ] Sauvegardes automatiques configurées
- [ ] Fichiers sensibles protégés (.htaccess)
- [ ] Mots de passe forts pour MySQL
- [ ] Mise à jour PHP/MySQL régulières
- [ ] Monitoring des logs actif
- [ ] Firewall configuré (ports 80/443 uniquement)

### .htaccess de sécurité

```apache
# Protection fichiers sensibles
<FilesMatch "^(config\.php|database\.sql|structure\.sql|\.git)">
    Require all denied
</FilesMatch>

# Empêcher listing des répertoires
Options -Indexes

# Désactiver signature serveur
ServerSignature Off

# Headers de sécurité
<IfModule mod_headers.c>
    Header set X-Content-Type-Options "nosniff"
    Header set X-Frame-Options "SAMEORIGIN"
    Header set X-XSS-Protection "1; mode=block"
    Header set Referrer-Policy "strict-origin-when-cross-origin"
</IfModule>
```

---

## 📅 Planning de maintenance

### Quotidien
- Sauvegarde automatique de la base
- Vérification logs d'erreurs

### Hebdomadaire
- Nettoyage des sessions PHP
- Vérification espace disque
- Analyse performance base de données

### Mensuel
- Mise à jour de sécurité PHP/MySQL
- Optimisation des tables MySQL
- Revue des métriques d'utilisation
- Test de restauration backup

### Script de sauvegarde automatique

```bash
#!/bin/bash
# /usr/local/bin/backup_agenda.sh

DATE=$(date +%Y%m%d_%H%M%S)
BACKUP_DIR="/backups/agenda"
DB_NAME="agenda_db"
DB_USER="agenda_user"
DB_PASS="mot_de_passe"

# Créer le dossier si nécessaire
mkdir -p $BACKUP_DIR

# Sauvegarde SQL
mysqldump -u $DB_USER -p$DB_PASS $DB_NAME | gzip > $BACKUP_DIR/db_$DATE.sql.gz

# Supprimer les sauvegardes > 30 jours
find $BACKUP_DIR -name "db_*.sql.gz" -mtime +30 -delete

echo "Sauvegarde terminée : db_$DATE.sql.gz"
```

**Cron** :
```cron
# Tous les jours à 2h du matin
0 2 * * * /usr/local/bin/backup_agenda.sh >> /var/log/backup_agenda.log 2>&1
```

---

## 🆘 Contacts et support

**En cas de problème critique** :
1. Consulter cette documentation
2. Vérifier les logs
3. Consulter [CHANGELOG.md](CHANGELOG.md)
4. Consulter [README.md](README.md)

---

**Documentation de déploiement - Version 3.0**  
**Dernière mise à jour** : 21 décembre 2025
