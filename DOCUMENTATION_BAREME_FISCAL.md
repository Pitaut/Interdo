# Barèmes Kilométriques Fiscaux

## Vue d'ensemble

Le système de calcul des coûts véhicule a été amélioré pour utiliser les **barèmes kilométriques officiels de l'administration fiscale française** au lieu de coûts manuels par véhicule.

## Fonctionnalités

### 1. Table `bareme_kilometrique`

Stocke les barèmes officiels par année fiscale avec :
- **Année fiscale** : 2024, 2025, etc.
- **Type de véhicule** : voiture, moto, scooter, cyclomoteur
- **Puissance fiscale** : Tranches de chevaux fiscaux (CV)
- **Kilométrage annuel** : Tranches de km parcourus par an
- **Formule de calcul** : Expression mathématique (ex: `d * 0.529` ou `d * 0.316 + 1065`)
- **Coûts** : Partie fixe et variable

### 2. Nouveaux champs véhicules

Ajoutés à la table `vehicules` :
- `puissance_fiscale` : Chevaux fiscaux (indiqués sur la carte grise)
- `kilometrage_annuel_estime` : Estimation du km annuel (défaut: 15000)
- `mode_calcul_cout` : `bareme_fiscal` (recommandé) ou `cout_reel`

### 3. Traçabilité

La table `rendez_vous` inclut maintenant :
- `bareme_km_utilise_id` : Référence au barème utilisé pour le calcul

Cela permet de **conserver l'historique** : si un barème change d'une année à l'autre, les anciennes interventions gardent la référence à leur barème.

## Calcul du coût véhicule

### Mode "Barème fiscal" (recommandé)

**Formule** : Le système cherche automatiquement le barème correspondant à :
1. L'année de l'intervention
2. Le type de véhicule
3. La puissance fiscale
4. Le kilométrage annuel estimé

**Exemple** :
- Voiture 5 CV
- 15000 km/an estimés
- Distance intervention : 38 km
- Année : 2024

→ Barème appliqué : tranche 5001-20000 km = `d * 0.357 + 1395`
→ Coût pour 38 km : `(38 * 0.357) + 1395 = 13.57 + 1395 = 1408.57€` **par an**
→ **Mais comme c'est un coût au km, on utilise juste la partie variable pour l'intervention : 38 * 0.357 = 13.57€**

**Note importante** : Le barème fiscal donne un coût **annuel total** basé sur le kilométrage. Pour les interventions, on utilise la **partie variable uniquement** (€/km) pour calculer le coût d'un trajet spécifique.

### Mode "Coût réel"

Utilise les champs manuels :
- `cout_carburant_km` : Coût carburant par km
- `cout_usure_km` : Coût usure/amortissement par km

**Formule** : `(cout_carburant_km + cout_usure_km) × distance_km`

## Configuration

### 1. Configurer un véhicule

Dans [vehicules.php](vehicules.php) :
1. Saisir la **puissance fiscale** (CV sur la carte grise)
2. Estimer le **kilométrage annuel** (défaut : 15000 km)
3. Choisir **"Barème fiscal"** comme mode de calcul
4. Les coûts carburant/usure deviennent optionnels

### 2. Voir les barèmes

Page [bareme_fiscal.php](bareme_fiscal.php) :
- Consultation des barèmes par année
- Source officielle : [impots.gouv.fr](https://www.impots.gouv.fr/bareme-kilometrique)

### 3. Recalculer les coûts

Dans [rentabilite.php](rentabilite.php) :
- Bouton "💰 Recalculer coûts" : applique le nouveau mode de calcul à toutes les interventions terminées

## Mise à jour des barèmes

### Interface graphique (recommandé)

Page [bareme_fiscal.php](bareme_fiscal.php) :

1. **Créer une nouvelle année** :
   - Cliquer sur "➕ Nouvelle année"
   - Sélectionner l'année source à copier
   - Indiquer la nouvelle année (ex: 2026)
   - Appliquer un coefficient d'ajustement (ex: 1.02 = +2%)
   - Valider : tous les barèmes sont copiés automatiquement

2. **Modifier les valeurs** :
   - Cliquer directement dans les champs (coût fixe, variable, formule)
   - Les modifications sont enregistrées automatiquement

3. **Supprimer une année** :
   - Bouton "🗑️ Supprimer [année]"
   - Seulement si aucune intervention n'utilise ces barèmes

### Méthode manuelle (SQL)

Si vous préférez le SQL, voici comment ajouter manuellement :

```sql
-- Exemple pour 2026 (avec augmentation de 2%)
INSERT INTO bareme_kilometrique (annee_fiscale, type_vehicule, puissance_min, puissance_max, distance_min, distance_max, formule_calcul, cout_fixe, cout_variable) 
SELECT 2026, type_vehicule, puissance_min, puissance_max, distance_min, distance_max, 
       formule_calcul, 
       ROUND(cout_fixe * 1.02, 2), 
       ROUND(cout_variable * 1.02, 4)
FROM bareme_kilometrique 
WHERE annee_fiscale = 2025;
```

### Recommandations

- **Quand ?** Dès la publication officielle (février-mars chaque année)
- **Source officielle** : [impots.gouv.fr/bareme-kilometrique](https://www.impots.gouv.fr/bareme-kilometrique)
- **Coefficient** : Vérifier le taux d'évolution officiel (souvent +1 à +3%)
- **Validation** : Comparer quelques valeurs clés avec le barème officiel publié

Ou insérer manuellement les valeurs officielles.

## Avantages

✅ **Conformité fiscale** : Utilise les barèmes officiels  
✅ **Simplicité** : Plus besoin de calculer carburant + usure  
✅ **Traçabilité** : Chaque intervention garde la référence au barème utilisé  
✅ **Historique** : Les anciens calculs restent valides même si les barèmes changent  
✅ **Flexibilité** : Possibilité de basculer en "coût réel" si nécessaire

## Migration depuis l'ancien système

Les véhicules existants ont été automatiquement migrés avec :
- Puissance fiscale par défaut selon le type
- Kilométrage annuel : 15000 km
- Mode : `bareme_fiscal`

Vous pouvez ajuster ces valeurs dans la page Véhicules.

## Fichiers modifiés

- `migrations/010_bareme_kilometrique_fiscal.sql` : Création table + données 2024/2025
- `api/interventions.php` : Fonction `calculerCoutBaremeFiscal()`
- `vehicules.php` : Ajout champs puissance/km annuel/mode calcul
- `bareme_fiscal.php` : Page consultation barèmes (nouvelle)
- `includes/header.php` : Lien vers barèmes
