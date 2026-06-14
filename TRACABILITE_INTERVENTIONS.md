# Traçabilité des Interventions - Garantie Complète

**Date**: 20 décembre 2025  
**Version**: 2.1  
**Statut**: ✅ Production

---

## Vue d'ensemble

Ce document garantit que **toutes les interventions terminées** apparaîtront dans l'historique, quel que soit le flux de clôture utilisé.

---

## Flux de clôture implémentés

### 1. Clôture avec forfait (close_forfait)

**Endpoint**: `POST api/interventions.php?action=close_forfait`

**Cas d'usage**:
- Client avec heures de forfait disponibles
- Clôture normale ou forcée (bouton "Clôturer quand même")

**Traçabilité**:
```sql
-- Crée TOUJOURS une entrée dans historique_consommation
INSERT INTO historique_consommation (
    rendez_vous_id,
    forfait_vendu_id,  -- ID du forfait OU NULL si clôture forcée
    client_id,
    temps_reel,
    temps_arrondi,
    difference_arrondi,
    heures_decomptes,
    heures_avant,
    heures_apres,
    date_rdv,
    heure_debut,
    heure_fin
) VALUES (...)
```

**Comportement selon disponibilité**:
- ✅ **Avec forfait**: `forfait_vendu_id` = ID du forfait utilisé
- ✅ **Sans forfait** (clôture forcée): `forfait_vendu_id` = NULL

---

### 2. Clôture hors forfait (close_hors_forfait)

**Endpoint**: `POST api/interventions.php?action=close_hors_forfait`

**Cas d'usage**:
- Client choisit de payer hors forfait
- Facturation à l'heure selon règle métier (1h + tranches de 30min)

**Traçabilité**:
```sql
-- 1. Crée une entrée de facturation
INSERT INTO facturation_hors_forfait (...)

-- 2. Crée AUSSI un historique pour traçabilité
INSERT INTO historique_consommation (
    rendez_vous_id,
    forfait_vendu_id,  -- NULL (hors forfait)
    client_id,
    temps_reel,
    temps_arrondi,     -- Quantité facturée
    difference_arrondi, -- 0 (pas de bonus/malus)
    heures_decomptes,  -- 0 (pas de décompte)
    heures_avant,      -- 0
    heures_apres,      -- 0
    date_rdv,
    heure_debut,
    heure_fin
) VALUES (...)
```

---

## Structure de la table historique_consommation

```sql
CREATE TABLE historique_consommation (
    id INT AUTO_INCREMENT PRIMARY KEY,
    rendez_vous_id INT NOT NULL,
    forfait_vendu_id INT NULL,              -- ✅ PEUT ÊTRE NULL
    client_id INT NOT NULL,
    temps_reel DECIMAL(10,2) NOT NULL,
    temps_arrondi DECIMAL(10,2) NOT NULL,
    difference_arrondi DECIMAL(10,2) NOT NULL,
    heures_decomptes DECIMAL(10,2) NOT NULL,
    heures_avant DECIMAL(10,2) NOT NULL,
    heures_apres DECIMAL(10,2) NOT NULL,
    date_rdv DATE NOT NULL,
    heure_debut TIME NOT NULL,
    heure_fin TIME NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
```

**Migration appliquée**: `006_allow_null_forfait_id_historique.sql`

---

## Scénarios couverts

| Scénario | Forfait dispo | Action utilisateur | forfait_vendu_id | Historique créé |
|----------|---------------|-------------------|------------------|-----------------|
| A | ✅ Oui | Clôture normale | ID du forfait | ✅ Oui |
| B | ❌ Non | "Clôturer quand même" | NULL | ✅ Oui |
| C | N/A | Payer hors forfait | NULL | ✅ Oui |

---

## Requêtes de vérification

### Lister toutes les interventions terminées

```sql
SELECT 
    rv.id,
    rv.titre,
    rv.date_rdv,
    rv.statut,
    hc.id as historique_id,
    fhf.id as facturation_id
FROM rendez_vous rv
LEFT JOIN historique_consommation hc ON rv.id = hc.rendez_vous_id
LEFT JOIN facturation_hors_forfait fhf ON rv.id = fhf.rendez_vous_id
WHERE rv.statut = 'termine'
ORDER BY rv.date_rdv DESC;
```

### Trouver les interventions sans historique (ne devrait rien retourner)

```sql
SELECT rv.*
FROM rendez_vous rv
LEFT JOIN historique_consommation hc ON rv.id = hc.rendez_vous_id
WHERE rv.statut = 'termine' AND hc.id IS NULL;
```

---

## Garanties

### ✅ Ce qui est garanti

1. **Toute intervention terminée** a une entrée dans `historique_consommation`
2. **Forfait NULL accepté** : interventions hors forfait ou forcées sont tracées
3. **Double traçabilité** : interventions hors forfait dans 2 tables :
   - `facturation_hors_forfait` → pour facturation
   - `historique_consommation` → pour historique complet

### ⚠️ Cas particuliers

**Interventions anciennes** (avant le 20 décembre 2025):
- Peuvent avoir un historique manquant si clôturées avant la correction
- Script de correction disponible : `create_missing_historique.php`

---

## Tests

### Script de test complet

```bash
php test_tracabilite_complete.php
```

Vérifie:
- Nombre d'interventions terminées
- Nombre d'historiques créés
- Interventions sans historique (liste)
- Cohérence entre les tables

### Test manuel

1. Créer une intervention pour un client sans forfait
2. Essayer de la clôturer → Message "Heures insuffisantes"
3. Cliquer "Clôturer quand même"
4. Vérifier dans la base :
   ```sql
   SELECT * FROM historique_consommation 
   WHERE rendez_vous_id = [ID];
   -- Doit retourner 1 ligne avec forfait_vendu_id = NULL
   ```

---

## Maintenance

### Vérification périodique

Exécuter mensuellement :
```sql
-- Doit retourner 0
SELECT COUNT(*) as interventions_sans_historique
FROM rendez_vous rv
LEFT JOIN historique_consommation hc ON rv.id = hc.rendez_vous_id
WHERE rv.statut = 'termine' AND hc.id IS NULL;
```

### En cas d'anomalie

Si une intervention terminée n'a pas d'historique :
1. Identifier l'intervention : noter l'ID
2. Vérifier si c'est une facturation hors forfait
3. Créer l'historique manuellement si nécessaire
4. Analyser pourquoi l'historique n'a pas été créé automatiquement

---

## Changelog

| Date | Version | Modification |
|------|---------|--------------|
| 20/12/2025 | 2.1 | ✅ Garantie de traçabilité complète |
| 20/12/2025 | 2.0 | Migration forfait_vendu_id NULL autorisé |
| 20/12/2025 | 1.1 | Ajout historique pour close_hors_forfait |
| 20/12/2025 | 1.0 | Correction calcul bonus/malus client |

---

**✅ TOUTES LES FUTURES INTERVENTIONS APPARAÎTRONT DANS L'HISTORIQUE**
