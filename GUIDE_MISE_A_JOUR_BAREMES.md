# Guide : Mise à jour annuelle des barèmes fiscaux

## 📅 Quand mettre à jour ?

Les barèmes kilométriques fiscaux sont publiés chaque année par l'administration fiscale, généralement en **février-mars**.

**Source officielle** : [impots.gouv.fr/bareme-kilometrique](https://www.impots.gouv.fr/bareme-kilometrique)

## 🚀 Méthode recommandée : Interface graphique

### Étape 1 : Accéder à la page des barèmes

1. Ouvrir [bareme_fiscal.php](http://localhost/_Interdo/bareme_fiscal.php)
2. Vous verrez les années disponibles en onglets (2024, 2025, 2026...)

### Étape 2 : Créer la nouvelle année

1. Cliquer sur **"➕ Nouvelle année"**
2. Remplir le formulaire :
   - **Année source** : L'année la plus récente (ex: 2025)
   - **Nouvelle année** : L'année à créer (ex: 2026)
   - **Coefficient** : Taux d'évolution (ex: 1.02 pour +2%, 1.03 pour +3%)
3. Cliquer sur **"✓ Créer"**

**Résultat** : Tous les barèmes de l'année source sont copiés avec le coefficient appliqué.

### Étape 3 : Ajuster les valeurs si nécessaire

1. Cliquer sur le nouvel onglet d'année (ex: 2026)
2. Les valeurs sont **modifiables directement** dans les tableaux :
   - Cliquer dans un champ (coût fixe, variable, formule)
   - Modifier la valeur
   - La modification est enregistrée automatiquement au blur (perte de focus)

### Étape 4 : Vérification

Comparer quelques valeurs clés avec le barème officiel publié :
- Voiture 5 CV, 0-5000 km : vérifier le taux €/km
- Moto 3-5 CV, 5001-20000 km : vérifier la formule
- Etc.

## 🔧 Méthode alternative : SQL direct

Si vous préférez manipuler directement la base de données :

```sql
-- Exemple: Créer 2026 à partir de 2025 avec +2.5%
INSERT INTO bareme_kilometrique 
(annee_fiscale, type_vehicule, puissance_min, puissance_max, 
 distance_min, distance_max, formule_calcul, cout_fixe, cout_variable)
SELECT 
    2026, 
    type_vehicule, puissance_min, puissance_max,
    distance_min, distance_max, formule_calcul,
    ROUND(cout_fixe * 1.025, 2),
    ROUND(cout_variable * 1.025, 4)
FROM bareme_kilometrique
WHERE annee_fiscale = 2025;
```

**Note** : Le coefficient peut être différent pour refléter l'inflation ou les ajustements officiels.

## 🗑️ Suppression d'une année

**⚠️ ATTENTION** : La suppression est irréversible !

### Via l'interface

1. Sélectionner l'année à supprimer
2. Cliquer sur **"🗑️ Supprimer [année]"**
3. Confirmer

**Condition** : Aucune intervention ne doit utiliser ces barèmes (protection automatique).

### Via SQL

```sql
DELETE FROM bareme_kilometrique WHERE annee_fiscale = 2026;
```

## 📊 Impact sur les calculs

### Calcul automatique par année

Le système sélectionne **automatiquement** le barème basé sur :
1. **Année de l'intervention** (date du rendez-vous)
2. Type de véhicule
3. Puissance fiscale
4. Kilométrage annuel estimé

**Exemple** :
- Intervention du 15 mars 2026 → utilise barème 2026
- Intervention du 10 janvier 2025 → utilise barème 2025

### Fallback automatique

Si le barème de l'année n'existe pas, le système utilise **l'année précédente** automatiquement.

**Exemple** :
- Intervention en 2027 mais pas de barème 2027 → utilise barème 2026

### Historique préservé

Chaque intervention conserve une référence au barème utilisé (`rendez_vous.bareme_km_utilise_id`).

**Avantage** : Les anciens calculs restent valides même si les barèmes changent.

## 🎯 Recommandations

### Coefficient d'ajustement

- **+1% à +3%** : Hausse typique annuelle
- **+0%** : Années stables (rare)
- Consulter l'évolution officielle publiée

### Validation

Après création d'une nouvelle année :
1. Vérifier 3-4 valeurs clés contre le barème officiel
2. Corriger manuellement si besoin (clic dans les cellules)
3. Documenter les ajustements spécifiques

### Calendrier

- **Février-Mars** : Publication officielle
- **Mars-Avril** : Mise à jour dans le système
- **Toute l'année** : Les interventions utilisent automatiquement le bon barème

## ❓ FAQ

**Q : Puis-je modifier les années passées ?**  
R : Oui, mais **déconseillé** car cela affectera les calculs historiques. Préférez créer une nouvelle année.

**Q : Que se passe-t-il si je supprime un barème utilisé ?**  
R : **Impossible**. Le système bloque la suppression si des interventions référencent ce barème.

**Q : Comment voir quel barème est utilisé pour une intervention ?**  
R : La colonne `bareme_km_utilise_id` dans `rendez_vous` contient l'ID du barème.

**Q : Le coefficient s'applique-t-il aux formules ?**  
R : Non, les formules sont copiées telles quelles. Seuls les coûts fixes et variables sont multipliés.

## 🔗 Fichiers concernés

- **Interface** : [bareme_fiscal.php](bareme_fiscal.php)
- **API** : [api/bareme.php](api/bareme.php)
- **Calcul** : [api/interventions.php](api/interventions.php) (`calculerCoutBaremeFiscal()`)
- **Documentation** : [DOCUMENTATION_BAREME_FISCAL.md](DOCUMENTATION_BAREME_FISCAL.md)

---

**Dernière mise à jour** : 28 décembre 2025
